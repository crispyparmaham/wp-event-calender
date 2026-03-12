<?php
/**
 * Plugin Name:  Drag & Drop Event Calendar
 * Description:  Kalenderübersicht für Trainings & Seminare mit Drag & Drop Funktion.
 * Version:      2.0.7
 * Author:       Lucas Dühr | more than ads
 * Author URI:   https://www.morethanads.de
 * Text Domain:  training-calendar
 */

defined( 'ABSPATH' ) || exit;

define( 'TC_PATH',        plugin_dir_path( __FILE__ ) );
define( 'TC_URL',         plugin_dir_url( __FILE__ ) );
define( 'TC_VERSION',     '2.0.7' );
define( 'TC_GITHUB_USER', 'crispyparmaham' );
define( 'TC_GITHUB_REPO', 'wp-event-calender' );

require_once TC_PATH . 'includes/admin/settings.php';
require_once TC_PATH . 'includes/admin/dashboard.php';
require_once TC_PATH . 'includes/admin/admin-page.php';
require_once TC_PATH . 'includes/admin/events-overview.php';

// ── Auto-Updater via GitHub Releases ──────────────────────────
if ( is_admin() ) {
    require_once TC_PATH . 'includes/admin/updater.php';
    new TC_Plugin_Updater( array(
        'github_user'     => 'crispyparmaham', // ← hier anpassen
        'github_repo'     => 'wp-event-calender',
        'plugin_file'     => __FILE__,
        'current_version' => TC_VERSION,
        'access_token'    => '', // leer lassen für public repos
    ) );
}

require_once TC_PATH . 'includes/post-type/cpt.php';

require_once TC_PATH . 'includes/ajax.php';

require_once TC_PATH . 'includes/shortcodes/shortcode-calendar.php';
require_once TC_PATH . 'includes/shortcodes/shortcode-registration.php';
require_once TC_PATH . 'includes/shortcodes/shortcode-price-bar.php';
require_once TC_PATH . 'includes/shortcodes/ical.php';

require_once TC_PATH . 'includes/registration/registration.php';
require_once TC_PATH . 'includes/registration/registration-admin-page.php';
require_once TC_PATH . 'includes/registration/export.php';
require_once TC_PATH . 'includes/registration/waitlist.php';
require_once TC_PATH . 'includes/registration/reminder.php';

// ── Tabelle beim Aktivieren des Plugins anlegen ────────────────
register_activation_hook( __FILE__, 'tc_run_db_setup' );

function tc_run_db_setup() {
    require_once TC_PATH . 'includes/registration/registration.php';
    tc_create_registrations_table();
    // iCal-Rewrite-Regeln flush
    tc_flush_rewrite_rules_for_ical();
}

// ── Cron beim Deaktivieren abmelden ───────────────────────────
register_deactivation_hook( __FILE__, 'tc_deactivate_reminder_cron' );
