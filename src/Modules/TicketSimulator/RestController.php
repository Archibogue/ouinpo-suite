<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class RestController
{
    public const NS = 'ouinpo-ticket-simulator/v1';
    public static function init(): void { add_action('rest_api_init', [self::class, 'register']); }
    public static function permission(\WP_REST_Request $request)
    {
        if (!is_user_logged_in() || !wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new \WP_Error('ticket_auth', 'Connexion ou nonce invalide.', ['status' => 403]);
        }
        return true;
    }
    private static function route(string $path, string $method, callable $handler): void
    {
        register_rest_route(self::NS, $path, [
            'methods' => $method, 'permission_callback' => [self::class, 'permission'],
            'callback' => static function (\WP_REST_Request $r) use ($handler) {
                try {
                    if (strlen($r->get_body()) > 1100000) { throw new \InvalidArgumentException('Requête trop volumineuse.'); }
                    $response = new \WP_REST_Response($handler($r));
                    $response->header('Cache-Control', 'private, no-store');
                    return $response;
                } catch (\InvalidArgumentException | \DomainException $e) {
                    return new \WP_Error('ticket_validation', $e->getMessage(), ['status' => 400]);
                } catch (\RuntimeException $e) {
                    $code = in_array($e->getCode(), [403,404,409], true) ? $e->getCode() : 500;
                    return new \WP_Error('ticket_error', $code === 500 ? 'Enregistrement impossible. Réessayez.' : $e->getMessage(), ['status' => $code]);
                } catch (\Throwable $e) {
                    return new \WP_Error('ticket_error', 'Requête invalide ou service indisponible.', ['status' => 400]);
                }
            },
        ]);
    }
    public static function register(): void
    {
        self::route('/scenarios', 'GET', static fn() => (new ScenarioRepository())->listing());
        self::route('/scenarios', 'POST', static fn($r) => self::save($r, 0));
        self::route('/scenarios/(?P<id>\d+)', 'GET', static function ($r) {
            $s = (new ScenarioRepository())->get((int) $r['id']); PermissionService::require(PermissionService::scenario($s)); return $s;
        });
        self::route('/scenarios/(?P<id>\d+)', 'PATCH', static fn($r) => self::save($r, (int) $r['id']));
        self::route('/scenarios/(?P<id>\d+)', 'DELETE', static function ($r) {
            $revision = $r->get_param('revision');
            if ($r->get_param('confirm_delete') !== true || !is_int($revision) || $revision < 1) {
                throw new \InvalidArgumentException('Confirmation explicite et révision du scénario requises.');
            }
            return (new ScenarioRepository())->delete((int) $r['id'], $revision);
        });
        self::route('/demo', 'GET', static function () {
            PermissionService::require(PermissionService::manage());
            return require __DIR__ . '/demo.php';
        });
        self::route('/targets', 'GET', static fn($r) => (new AssignmentService())->targets(sanitize_text_field((string) $r->get_param('search'))));
        self::route('/scenarios/(?P<id>\d+)/assignments', 'GET', static fn($r) => (new AssignmentService())->listing((int) $r['id']));
        self::route('/scenarios/(?P<id>\d+)/assignments', 'POST', static function ($r) {
            $p = self::strings($r, ['type','target']);
            if (!isset($p['type'], $p['target'])) { throw new \InvalidArgumentException('Type et cible requis.'); }
            (new AssignmentService())->save((int) $r['id'], $p['type'], $p['target'], $r->get_param('active') !== false);
            return ['ok' => true];
        });
        self::route('/assignments', 'GET', static function () {
            PermissionService::require(\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::TICKET_PRACTICE));
            return PermissionService::practice() ? (new AssignmentService())->listing() : [];
        });
        self::route('/assignments/(?P<id>\d+)/attempts', 'POST', static fn($r) => ['id' => (new AttemptRepository())->start((int) $r['id'])]);
        self::route('/attempts', 'GET', static function () {
            PermissionService::require(\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::TICKET_PRACTICE) || PermissionService::manage() || \Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::TICKET_OBSERVE));
            return (new AttemptRepository())->listing();
        });
        self::route('/attempts', 'DELETE', static function ($r) {
            PermissionService::require(PermissionService::all());
            if ($r->get_param('confirm_delete_all') !== true) {
                throw new \InvalidArgumentException('Confirmation de suppression de toutes les tentatives requise.');
            }
            return AttemptCleanup::deleteAll();
        });
        self::route('/students/(?P<id>\d+)/attempts', 'DELETE', static function ($r) {
            PermissionService::require(PermissionService::all());
            if ($r->get_param('confirm_delete_student') !== true) { throw new \InvalidArgumentException('Confirmation de suppression des tentatives de cet élève requise.'); }
            return AttemptCleanup::deleteStudent((int) $r['id']);
        });
        self::route('/attempts/(?P<id>\d+)', 'GET', static fn($r) => self::view((int) $r['id']));
        // Older open pages still download via GET: include feedback there too.
        self::route('/attempts/(?P<id>\d+)/summary', 'GET', static fn($r) => AttemptAdvice::download((int) $r['id']));
        self::route('/attempts/(?P<id>\d+)/summary/plain', 'GET', static fn($r) => AttemptMarkdown::download((int) $r['id']));
        self::route('/attempts/(?P<id>\d+)/summary', 'POST', static fn($r) => AttemptAdvice::download((int) $r['id']));
        self::route('/attempts/(?P<id>\d+)/events', 'GET', static function ($r) {
            PermissionService::require(PermissionService::view((new AttemptRepository())->get((int) $r['id'])));
            return (new EventRepository())->listing((int) $r['id'], max(0, (int) $r->get_param('after')));
        });
        $base = '/attempts/(?P<id>\d+)/tickets/(?P<ticket>[a-zA-Z0-9_-]+)';
        self::route($base . '/reset', 'POST', static fn($r) => self::mutate($r, 'reset_ticket', ['confirm_reset'=>$r->get_param('confirm_reset')]));
        self::route($base, 'PATCH', static fn($r) => self::mutate($r, 'qualify', self::strings($r, ['nature','category','subcategory','impact','urgency','priority','priority_justification','it_service','assignee'])));
        self::route($base . '/notes', 'POST', static fn($r) => self::mutate($r, 'note', self::strings($r, ['message'])));
        self::route($base . '/actions/(?P<action>[a-zA-Z0-9_-]+)', 'POST', static fn($r) => self::mutate($r, 'action', ['action_id' => (string) $r['action']] + self::strings($r, ['message','cause','solution','tests','result'])));
        self::route($base . '/resources/(?P<resource>[a-zA-Z0-9_-]+)', 'POST', static function ($r) {
            $id = (int) $r['id']; $a = (new AttemptRepository())->get($id);
            PermissionService::require(PermissionService::view($a));
            $states = (new AttemptRepository())->states($id); $state = $states[$r['ticket']] ?? [];
            PermissionService::require(in_array($r['resource'], $state['visible_resources'] ?? [], true));
            if (!PermissionService::observe($a) && PermissionService::edit($a)) {
                self::mutate($r, 'resource', ['resource_id' => $r['resource']]);
            }
            $s = json_decode($a['snapshot'], true);
            return ['resource' => CodeWorkspace::resource(TicketScenario::index($s['resources'])[$r['resource']], $state), 'attempt' => self::view($id)];
        });
        self::route($base . '/resources/(?P<resource>[a-zA-Z0-9_-]+)/code', 'PATCH', static function ($r) {
            $content = $r->get_param('content');
            if (!is_string($content) || strlen($content) > 100000 || str_contains($content, "\0")) {
                throw new \InvalidArgumentException('Extrait invalide (100 Ko maximum).');
            }
            // Preserve PHP/HTML as data. sanitize_textarea_field would destroy code tags.
            return self::mutate($r, 'code', ['resource_id'=>(string) $r['resource'], 'content'=>$content]);
        });
        foreach (['archive','reset'] as $op) {
            self::route('/attempts/(?P<id>\d+)/' . $op, 'POST', static fn($r) => ['id' => (new AttemptRepository())->archive((int) $r['id'], $op === 'reset')]);
        }
    }
    private static function strings(\WP_REST_Request $r, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = $r->get_param($key);
            if ($value === null) { continue; }
            if (!is_string($value) || strlen($value) > 10000) { throw new \InvalidArgumentException('Texte invalide : ' . $key); }
            $out[$key] = sanitize_textarea_field($value);
        }
        return $out;
    }
    private static function save(\WP_REST_Request $r, int $id): array
    {
        $definition = $r->get_param('definition');
        if (!is_array($definition)) { throw new \InvalidArgumentException('Définition du scénario requise.'); }
        $id = (new ScenarioRepository())->save($id, $definition, (string) $r->get_param('status'), (int) $r->get_param('revision'));
        return (new ScenarioRepository())->get($id);
    }
    private static function mutate(\WP_REST_Request $r, string $operation, array $input): array
    {
        $revision = $r->get_param('revision');
        if (!is_int($revision) || $revision < 0) { throw new \InvalidArgumentException('Révision requise.'); }
        (new AttemptRepository())->mutate((int) $r['id'], (string) $r['ticket'], $revision, $operation, $input);
        return self::view((int) $r['id']);
    }
    private static function view(int $id): array
    {
        $repo = new AttemptRepository(); $a = $repo->get($id);
        PermissionService::require(PermissionService::view($a));
        $view = StudentView::build($a, $repo->states($id), PermissionService::observe($a));
        $view['read_only'] = $view['read_only'] || !PermissionService::edit($a);
        $view['events'] = (new EventRepository())->listing($id);
        return $view;
    }
}
