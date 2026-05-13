<?php
namespace Ouinpo\Exercises;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

class AccessGate {

  /**
   * Pages élèves / utilisateurs connectés.
   */
  private const PRIVATE_PAGES = [
    'mes-badges',
    'la-route-de-briques-jaunes',
    'depot-eleve',
    'registre-des-apprentis-satrapes-et-para-satrapes',
  ];

  /**
   * Pages professeur : nécessitent un compte avec droits prof/admin.
   */
  private const TEACHER_PAGES = [
    'suivi-des-eleves',
  ];

  public static function init() {
    add_action('template_redirect', [self::class, 'block_pages']);
    add_action('wp_head', [self::class, 'noindex'], 1);
  }

  public static function block_pages() {
    if (is_admin()) {
      return;
    }

    /*
     * Pages privées simples : il faut être connecté.
     */
    if (is_page(self::PRIVATE_PAGES)) {
      if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(get_permalink()));
        exit;
      }
      return;
    }

    /*
     * Pages professeur : il faut être connecté + avoir les droits.
     */
    if (is_page(self::TEACHER_PAGES)) {
      if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(get_permalink()));
        exit;
      }

      if (!Capabilities::can(Capabilities::VIEW_STUDENT_DATA)) {
        status_header(403);
        nocache_headers();
        wp_die(
          'Cette page est réservée aux enseignants.',
          'Accès refusé',
          ['response' => 403]
        );
      }

      return;
    }
  }

  public static function noindex() {
    if (
      is_page(self::PRIVATE_PAGES)
      || is_page(self::TEACHER_PAGES)
    ) {
      echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
    }
  }
}
