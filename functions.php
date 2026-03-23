<?php
/**
 * Plugin Name:  Drag & Drop Event Calendar
 * Description:  Kalenderübersicht für Termine mit Drag & Drop Funktion.
 * Version:      3.2.0
 * Requires PHP: 8.2
 * Author:       Lucas Dühr | more than ads
 * Author URI:   https://www.morethanads.de
 * Text Domain:  time-calendar
 * Update URI:   https://github.com/crispyparmaham/wp-event-calender
 *
 * 
 * This plugin is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 */

defined( 'ABSPATH' ) || exit;

define( 'TC_PATH',        plugin_dir_path( __FILE__ ) );
define( 'TC_URL',         plugin_dir_url( __FILE__ ) );
define( 'TC_VERSION',     '3.2.0' );

// ── Wiederholungs- & Rate-Limit-Grenzen ───────────────────────
define( 'TC_RECURRING_LIMIT',    104 ); // Max. ~5 Jahre wöchentlich
define( 'TC_RATE_LIMIT_COUNT',   5   ); // Anmeldungen pro Zeitfenster
define( 'TC_RATE_LIMIT_SECONDS', 900 ); // Zeitfenster: 15 Minuten (in Sek.)
define( 'TC_EVENTS_CACHE_TTL',   300 ); // Events-Cache: 5 Minuten (in Sek.)

// ── AJAX-Action-Namen ─────────────────────────────────────────
define( 'TC_AJAX_GET_EVENTS',          'tc_get_events' );
define( 'TC_AJAX_CREATE_EVENT',        'tc_create_event' );
define( 'TC_AJAX_UPDATE_EVENT',        'tc_update_event' );
define( 'TC_AJAX_SUBMIT_REGISTRATION', 'tc_submit_registration' );
define( 'TC_AJAX_GET_EVENT_DETAILS',   'tc_get_event_details' );
define( 'TC_AJAX_CANCEL_REGISTRATION', 'tc_cancel_registration' );
define( 'TC_HOOK_REGISTRATION_SUBMITTED', 'tc_registration_submitted' );

require_once TC_PATH . 'includes/admin/updater.php';
require_once TC_PATH . 'includes/admin/settings.php';
require_once TC_PATH . 'includes/admin/dashboard.php';
require_once TC_PATH . 'includes/admin/admin-page.php';
require_once TC_PATH . 'includes/admin/events-overview.php';
require_once TC_PATH . 'includes/admin/categories.php';
require_once TC_PATH . 'includes/admin/migration.php';
require_once TC_PATH . 'includes/admin/migration-date-type.php';

require_once TC_PATH . 'includes/post-type/cpt.php';
require_once TC_PATH . 'includes/post-type/schema.php';

require_once TC_PATH . 'includes/ajax.php';

require_once TC_PATH . 'includes/shortcodes/shortcode-calendar.php';
require_once TC_PATH . 'includes/shortcodes/shortcode-events.php';
require_once TC_PATH . 'includes/shortcodes/shortcode-registration.php';
require_once TC_PATH . 'includes/shortcodes/shortcode-price-bar.php';
require_once TC_PATH . 'includes/shortcodes/ical.php';

require_once TC_PATH . 'includes/registration/registration.php';
require_once TC_PATH . 'includes/registration/registration-admin-page.php';
require_once TC_PATH . 'includes/registration/cancel.php';
require_once TC_PATH . 'includes/registration/export.php';
require_once TC_PATH . 'includes/registration/waitlist.php';
require_once TC_PATH . 'includes/registration/reminder.php';

// ── Helper: Dark-Mode-Klasse ────────────────────────────────────
function tc_dark_class() {
    return tc_get_setting( 'calendar_mode', 'light' ) === 'dark' ? 'tc-dark' : '';
}

// ── Design System CSS global laden ──────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'tc-design-system',
        TC_URL . 'assets/css/design-system.css',
        [],
        TC_VERSION
    );

    // Single-Event-Template-Styles (nur auf Detailseiten)
    if ( is_singular( 'time_event' ) ) {
        wp_enqueue_style(
            'tc-single-event',
            TC_URL . 'assets/css/frontend/single-event.css',
            [ 'tc-design-system' ],
            TC_VERSION
        );
    }
}, 5 ); // Priorität 5 = vor allen anderen Plugin-Styles

// ── Tabelle beim Aktivieren des Plugins anlegen ────────────────
register_activation_hook( __FILE__, 'tc_run_db_setup' );

function tc_run_db_setup() {
    require_once TC_PATH . 'includes/registration/registration.php';
    require_once TC_PATH . 'includes/admin/categories.php';
    tc_create_registrations_table();
    tc_create_categories_table();
    tc_install_default_categories();
    // iCal-Rewrite-Regeln flush
    tc_flush_rewrite_rules_for_ical();
}

// ── Cron beim Deaktivieren abmelden ───────────────────────────
register_deactivation_hook( __FILE__, 'tc_deactivate_reminder_cron' );
