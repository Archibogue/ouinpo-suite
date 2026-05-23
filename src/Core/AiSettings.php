<?php

namespace Ouinpo\Suite\Core;

defined('ABSPATH') || exit;

final class AiSettings
{
    public const DEFAULT_PROVIDER = 'albert';
    public const PROVIDERS = ['albert', 'openai'];

    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_notices', [self::class, 'render_public_access_migration_notice']);
    }

    public static function defaults(): array
    {
        return [
            'ouinpo_ai_enabled' => 0,
            'ouinpo_ai_public_enabled' => 0,
            'ouinpo_ai_usage_chat_rag' => 1,
            'ouinpo_ai_usage_exercise_help' => 1,
            'ouinpo_ai_usage_exercise_correction' => 1,
            'ouinpo_ai_usage_gate_validation' => 0,
            'ouinpo_ai_usage_practical_correction' => 1,
            'ouinpo_ai_usage_feedback_generation' => 1,
            'ouinpo_ai_usage_pedagogical_suggestions' => 1,
            'ouinpo_ai_default_provider' => self::DEFAULT_PROVIDER,
            'ouinpo_ai_public_provider' => 'albert',
            'ouinpo_ai_logged_provider' => 'albert',
            'ouinpo_ai_api_base_url' => 'https://albert.api.etalab.gouv.fr/v1',
            'ouinpo_ai_api_key' => '',
            'ouinpo_ai_chat_model' => 'openai/gpt-oss-120b',
            'ouinpo_ai_code_model' => 'openweight-code',
            'ouinpo_ai_embedding_model' => 'BAAI/bge-m3',
            'ouinpo_ai_timeout' => 45,
            'ouinpo_ai_max_tokens' => 800,
            'ouinpo_ai_temperature' => 0.3,
            'ouinpo_ai_top_p' => 1.0,
            'ouinpo_ai_frequency_penalty' => 0.0,
            'ouinpo_ai_presence_penalty' => 0.0,
            'ouinpo_ai_user_daily_limit' => 200,
            'ouinpo_ai_public_daily_limit' => 10,
            'ouinpo_ai_disabled_message' => 'L\'assistant IA est desactive pour le moment.',
            'ouinpo_ai_privacy_notice' => 'IA pedagogique : n\'ecris pas de donnees personnelles. Les reponses peuvent contenir des erreurs et doivent etre verifiees.',
            'ouinpo_ai_persona_general' => self::default_general_persona(),
            'ouinpo_ai_persona_public' => 'Tu aides un visiteur anonyme en NSI/SNT. Reste bref, prudent, sans memoire utilisateur ni donnee personnelle.',
            'ouinpo_ai_persona_student' => 'Tu aides un eleve en NSI/SNT avec bienveillance. Guide par etapes sans faire le travail a sa place.',
            'ouinpo_ai_persona_teacher' => 'Tu aides un enseignant a preparer des pistes pedagogiques sobres, verifiables et adaptees au programme.',
            'ouinpo_ai_rag_system_prompt' => self::default_rag_prompt(),
            'ouinpo_ai_exercise_correction_prompt' => self::default_exercise_prompt(),
            'ouinpo_ai_practical_correction_prompt' => self::default_practical_prompt(),
            'ouinpo_ai_suggestions_prompt' => 'Propose des suggestions pedagogiques courtes, adaptees au niveau et aux competences disponibles. N\'invente pas de ressource absente du contexte.',
            'ouinpo_ai_out_of_program_guardrails' => self::default_guardrails(),
            'ouinpo_ai_anonymous_default_school_level' => 'premiere',
            'ouinpo_ai_show_rag_sources' => 1,
            'ouinpo_ai_debug_logs' => 0,
            'ouinpo_public_exercises_enabled' => 0,
            'ouinpo_public_hints_enabled' => 0,
            'ouinpo_public_solutions_enabled' => 0,
            'ouinpo_public_practical_subjects_enabled' => 0,
            'ouinpo_public_practical_files_enabled' => 0,
            'ouinpo_sf_openai_api_key' => '',
            'ouinpo_sf_model' => 'gpt-5-mini',
            'ouinpo_sf_embed_model' => 'text-embedding-3-large',
            'ouinpo_sf_memory_days' => 30,
            'ouinpo_sf_members_only' => 0,
            'ouinpo_sf_wxr_path' => '',
            'ouinpo_sf_albert_enabled' => 0,
            'ouinpo_sf_public_albert_enabled' => 0,
            'ouinpo_sf_albert_api_key' => '',
            'ouinpo_sf_albert_base_url' => 'https://albert.api.etalab.gouv.fr/v1',
            'ouinpo_sf_albert_model' => 'openai/gpt-oss-120b',
            'ouinpo_sf_albert_code_model' => 'openweight-code',
            'ouinpo_sf_albert_embedding_model' => 'BAAI/bge-m3',
            'ouinpo_sf_albert_reranker_model' => 'BAAI/bge-reranker-v2-m3',
            'ouinpo_sf_rag_embedding_provider' => 'openai',
            'ouinpo_sf_public_hourly_limit' => 5,
            'ouinpo_sf_public_daily_limit' => 100,
            'ouinpo_sf_rag_rerank_candidates' => 40,
            'ouinpo_sf_max_embeddings_run' => 120,
            'ouinpo_sf_ai_notice_url' => '',
            'ouinpo_sf_ai_notice_public' => 'Assistant IA public : n\'ecris pas de nom, prenom, note, adresse ou information personnelle. Les reponses peuvent contenir des erreurs.',
            'ouinpo_sf_ai_notice_logged' => 'IA pedagogique : n\'ecris pas de donnees personnelles. Les reponses proposees par l\'assistant doivent etre verifiees et ne remplacent pas le professeur.',
        ];
    }

    public static function register_settings(string $group = 'ouinpo_sf'): void
    {
        foreach (self::schema() as $option => $type) {
            register_setting($group, $option, [
                'default' => self::defaults()[$option] ?? '',
                'sanitize_callback' => [self::class, 'sanitize_' . $type],
            ]);
        }
    }

    public static function get(string $option)
    {
        $defaults = self::defaults();
        return get_option($option, $defaults[$option] ?? '');
    }

    public static function enabled_for_usage(string $usage): bool
    {
        if ((int) self::get('ouinpo_ai_enabled') !== 1) {
            return false;
        }

        return (int) self::get('ouinpo_ai_usage_' . $usage) === 1;
    }

    public static function public_enabled(): bool
    {
        return (int) self::get('ouinpo_ai_enabled') === 1
            && (int) self::get('ouinpo_ai_public_enabled') === 1;
    }

    public static function public_access_enabled(string $option): bool
    {
        return (int) self::get($option) === 1;
    }

    public static function debug_logs_enabled(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG && (int) self::get('ouinpo_ai_debug_logs') === 1;
    }

    public static function debug_log(string $message, array $context = []): void
    {
        if (!self::debug_logs_enabled()) {
            return;
        }

        $safe = [];
        foreach ($context as $key => $value) {
            if (preg_match('/key|secret|token|prompt|response|answer|content/i', (string) $key)) {
                continue;
            }
            $safe[] = sanitize_key((string) $key) . '=' . substr(sanitize_text_field((string) $value), 0, 120);
        }

        error_log('[OuInPo IA/RAG] ' . sanitize_text_field($message) . ($safe ? ' | ' . implode(' ', $safe) : ''));
    }

    public static function migrate_public_access_for_existing_site(string $installed_version): void
    {
        if (version_compare($installed_version, '0.5.0', '>=')) {
            return;
        }

        $legacyPublicOptions = [
            'ouinpo_sf_public_albert_enabled',
        ];

        $legacyExplicitOptIn = false;
        foreach ($legacyPublicOptions as $legacyOption) {
            if ((int) get_option($legacyOption, 0) === 1) {
                $legacyExplicitOptIn = true;
                break;
            }
        }

        foreach ([
            'ouinpo_public_exercises_enabled',
            'ouinpo_public_hints_enabled',
            'ouinpo_public_solutions_enabled',
            'ouinpo_public_practical_subjects_enabled',
            'ouinpo_public_practical_files_enabled',
        ] as $option) {
            if (get_option($option, null) === null) {
                update_option($option, $legacyExplicitOptIn ? 1 : 0, false);
            }
        }

        if (get_option('ouinpo_ai_public_enabled', null) === null) {
            update_option('ouinpo_ai_public_enabled', $legacyExplicitOptIn ? 1 : 0, false);
        }

        update_option('ouinpo_public_access_migration_notice', 1, false);
    }

    public static function consumePublicRateLimit(
        string $scope,
        int $hourlyLimit,
        int $dailyLimit,
        int $globalDailyLimit = 0
    ) {
        if (is_user_logged_in()) {
            return true;
        }

        $scope = sanitize_key($scope);
        if ($scope === '') {
            $scope = 'public_ai';
        }

        $hash = self::publicClientHash();
        $hourlyLimit = max(1, min(10000, $hourlyLimit));
        $dailyLimit = max(1, min(10000, $dailyLimit));
        $globalDailyLimit = max(0, min(100000, $globalDailyLimit));

        $hourKey = 'ouinpo_rl_' . $scope . '_h_' . gmdate('YmdH') . '_' . $hash;
        $dayKey = 'ouinpo_rl_' . $scope . '_d_' . gmdate('Ymd') . '_' . $hash;
        $globalDayKey = 'ouinpo_rl_' . $scope . '_gd_' . gmdate('Ymd');

        $hourUsed = (int) get_transient($hourKey);
        if ($hourUsed >= $hourlyLimit) {
            return new \WP_Error(
                'ouinpo_public_quota_hour',
                'Limite atteinte pour cette heure. Réessaie un peu plus tard.',
                ['status' => 429]
            );
        }

        $dayUsed = (int) get_transient($dayKey);
        if ($dayUsed >= $dailyLimit) {
            return new \WP_Error(
                'ouinpo_public_quota_day',
                'Limite quotidienne atteinte pour cet accès public.',
                ['status' => 429]
            );
        }

        if ($globalDailyLimit > 0) {
            $globalDayUsed = (int) get_transient($globalDayKey);
            if ($globalDayUsed >= $globalDailyLimit) {
                return new \WP_Error(
                    'ouinpo_public_quota_global_day',
                    'Limite quotidienne globale atteinte pour ce service public.',
                    ['status' => 429]
                );
            }
        }

        set_transient($hourKey, $hourUsed + 1, HOUR_IN_SECONDS + 120);
        set_transient($dayKey, $dayUsed + 1, DAY_IN_SECONDS + 120);

        if ($globalDailyLimit > 0) {
            set_transient($globalDayKey, ((int) get_transient($globalDayKey)) + 1, DAY_IN_SECONDS + 120);
        }

        return true;
    }

    public static function publicClientHash(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        $ip = preg_replace('/[^0-9a-fA-F:\.]/', '', $ip) ?: 'unknown';
        $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_SALT') ? AUTH_SALT : 'ouinpo');

        return substr(hash('sha256', $ip . '|' . $salt), 0, 24);
    }

    public static function render_public_access_migration_notice(): void
    {
        if ((int) get_option('ouinpo_public_access_migration_notice', 0) !== 1) {
            return;
        }

        if (!current_user_can(Capabilities::MANAGE_SETTINGS) && !current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['ouinpo_dismiss_public_access_notice'])) {
            delete_option('ouinpo_public_access_migration_notice');
            return;
        }

        $dismissUrl = add_query_arg(
            ['ouinpo_dismiss_public_access_notice' => '1'],
            admin_url('admin.php?page=ouinpo-suite-settings&tab=ai')
        );

        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>OuInPo Suite :</strong>
                les accès publics aux exercices, indices, solutions, fichiers pratiques et à l'IA restent désactivés par défaut après migration.
                Vérifiez volontairement ces réglages avant d'ouvrir le site à des visiteurs anonymes.
                <a href="<?php echo esc_url($dismissUrl); ?>">Masquer cet avis</a>
            </p>
        </div>
        <?php
    }

    private static function schema(): array
    {
        return [
            'ouinpo_ai_enabled' => 'bool',
            'ouinpo_ai_public_enabled' => 'bool',
            'ouinpo_ai_usage_chat_rag' => 'bool',
            'ouinpo_ai_usage_exercise_help' => 'bool',
            'ouinpo_ai_usage_exercise_correction' => 'bool',
            'ouinpo_ai_usage_gate_validation' => 'bool',
            'ouinpo_ai_usage_practical_correction' => 'bool',
            'ouinpo_ai_usage_feedback_generation' => 'bool',
            'ouinpo_ai_usage_pedagogical_suggestions' => 'bool',
            'ouinpo_ai_default_provider' => 'provider',
            'ouinpo_ai_public_provider' => 'provider',
            'ouinpo_ai_logged_provider' => 'provider',
            'ouinpo_ai_api_base_url' => 'url',
            'ouinpo_ai_api_key' => 'secret',
            'ouinpo_ai_chat_model' => 'model',
            'ouinpo_ai_code_model' => 'model',
            'ouinpo_ai_embedding_model' => 'model',
            'ouinpo_ai_timeout' => 'timeout',
            'ouinpo_ai_max_tokens' => 'max_tokens',
            'ouinpo_ai_temperature' => 'temperature',
            'ouinpo_ai_top_p' => 'top_p',
            'ouinpo_ai_frequency_penalty' => 'penalty',
            'ouinpo_ai_presence_penalty' => 'penalty',
            'ouinpo_ai_user_daily_limit' => 'quota',
            'ouinpo_ai_public_daily_limit' => 'quota',
            'ouinpo_ai_disabled_message' => 'text',
            'ouinpo_ai_privacy_notice' => 'long_text',
            'ouinpo_ai_persona_general' => 'long_text',
            'ouinpo_ai_persona_public' => 'long_text',
            'ouinpo_ai_persona_student' => 'long_text',
            'ouinpo_ai_persona_teacher' => 'long_text',
            'ouinpo_ai_rag_system_prompt' => 'long_text',
            'ouinpo_ai_exercise_correction_prompt' => 'long_text',
            'ouinpo_ai_practical_correction_prompt' => 'long_text',
            'ouinpo_ai_suggestions_prompt' => 'long_text',
            'ouinpo_ai_out_of_program_guardrails' => 'long_text',
            'ouinpo_ai_anonymous_default_school_level' => 'key',
            'ouinpo_ai_show_rag_sources' => 'bool',
            'ouinpo_ai_debug_logs' => 'bool',
            'ouinpo_public_exercises_enabled' => 'bool',
            'ouinpo_public_hints_enabled' => 'bool',
            'ouinpo_public_solutions_enabled' => 'bool',
            'ouinpo_public_practical_subjects_enabled' => 'bool',
            'ouinpo_public_practical_files_enabled' => 'bool',
            'ouinpo_sf_openai_api_key' => 'secret',
            'ouinpo_sf_model' => 'model',
            'ouinpo_sf_embed_model' => 'model',
            'ouinpo_sf_memory_days' => 'memory_days',
            'ouinpo_sf_members_only' => 'bool',
            'ouinpo_sf_wxr_path' => 'path',
            'ouinpo_sf_albert_enabled' => 'bool',
            'ouinpo_sf_public_albert_enabled' => 'bool',
            'ouinpo_sf_albert_api_key' => 'secret',
            'ouinpo_sf_albert_base_url' => 'url',
            'ouinpo_sf_albert_model' => 'model',
            'ouinpo_sf_albert_code_model' => 'model',
            'ouinpo_sf_albert_embedding_model' => 'model',
            'ouinpo_sf_albert_reranker_model' => 'model',
            'ouinpo_sf_rag_embedding_provider' => 'provider',
            'ouinpo_sf_public_hourly_limit' => 'quota',
            'ouinpo_sf_public_daily_limit' => 'quota',
            'ouinpo_sf_rag_rerank_candidates' => 'rerank_candidates',
            'ouinpo_sf_max_embeddings_run' => 'embedding_budget',
            'ouinpo_sf_ai_notice_url' => 'url_or_path',
            'ouinpo_sf_ai_notice_public' => 'long_text',
            'ouinpo_sf_ai_notice_logged' => 'long_text',
        ];
    }

    public static function sanitize_bool($value): int
    {
        return in_array($value, [1, '1', true, 'true', 'on'], true) ? 1 : 0;
    }

    public static function sanitize_provider($value): string
    {
        $provider = sanitize_key((string) $value);
        return in_array($provider, self::PROVIDERS, true) ? $provider : self::DEFAULT_PROVIDER;
    }

    public static function sanitize_url($value): string
    {
        $url = esc_url_raw(trim((string) $value));
        return $url !== '' ? rtrim($url, '/') : '';
    }

    public static function sanitize_url_or_path($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $value)) {
            return esc_url_raw($value);
        }
        return sanitize_text_field($value);
    }

    public static function sanitize_secret($value): string
    {
        return trim(sanitize_text_field((string) $value));
    }

    public static function sanitize_model($value): string
    {
        $value = trim(sanitize_text_field((string) $value));
        return preg_match('/^[A-Za-z0-9._:\/-]{1,120}$/', $value) ? $value : '';
    }

    public static function sanitize_timeout($value): int
    {
        return max(5, min(120, (int) $value));
    }

    public static function sanitize_max_tokens($value): int
    {
        return max(128, min(8000, (int) $value));
    }

    public static function sanitize_temperature($value): float
    {
        return max(0.0, min(2.0, (float) $value));
    }

    public static function sanitize_top_p($value): float
    {
        return max(0.0, min(1.0, (float) $value));
    }

    public static function sanitize_penalty($value): float
    {
        return max(-2.0, min(2.0, (float) $value));
    }

    public static function sanitize_quota($value): int
    {
        return max(0, min(10000, (int) $value));
    }

    public static function sanitize_memory_days($value): int
    {
        return max(0, min(365, (int) $value));
    }

    public static function sanitize_rerank_candidates($value): int
    {
        return max(10, min(80, (int) $value));
    }

    public static function sanitize_embedding_budget($value): int
    {
        return max(10, min(5000, (int) $value));
    }

    public static function sanitize_text($value): string
    {
        return sanitize_text_field((string) $value);
    }

    public static function sanitize_key($value): string
    {
        return substr(sanitize_key((string) $value), 0, 40);
    }

    public static function sanitize_path($value): string
    {
        return sanitize_text_field((string) $value);
    }

    public static function sanitize_long_text($value): string
    {
        $value = wp_unslash((string) $value);
        $value = wp_kses($value, []);
        return trim($value);
    }

    private static function default_general_persona(): string
    {
        return 'Tu es un assistant pedagogique pour la NSI et la SNT. Tu expliques sobrement, tu guides par etapes, tu respectes le programme et tu refuses les demandes hors cadre sans inventer de ressource.';
    }

    private static function default_rag_prompt(): string
    {
        return 'Utilise en priorite le contexte RAG fourni. Ne cite que des sources presentes dans le contexte. Si une information manque, dis-le clairement sans inventer.';
    }

    private static function default_exercise_prompt(): string
    {
        return 'Corrige une reponse d\'eleve avec bienveillance. Ne donne pas le corrige complet. Retourne un JSON valide avec verdict, feedback, next_steps, confidence et safe_to_mark_solved.';
    }

    private static function default_practical_prompt(): string
    {
        return 'Corrige un sujet pratique NSI avec prudence. Evalue la logique, les cas limites attendus et la conformite a la consigne. Retourne uniquement un JSON valide.';
    }

    private static function default_guardrails(): string
    {
        return 'Si la question depasse le niveau scolaire indique, signale que la notion n\'est pas exigible, donne seulement une intuition courte et ne propose pas d\'exercice hors programme.';
    }
}
