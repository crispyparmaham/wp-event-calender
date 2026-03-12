<?php
/**
 * Plugin Name:  Drag & Drop Event Calendar
 * Description:  Kalenderübersicht für Trainings & Seminare mit Drag & Drop Funktion.
 * Version:      2.1.2
 * Requires PHP: 8.2
 * Author:       Lucas Dühr | more than ads
 * Author URI:   https://www.morethanads.de
 * Text Domain:  training-calendar
 * Update URI:   https://github.com/crispyparmaham/wp-event-calender
 *
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 */

defined( 'ABSPATH' ) || exit;

define( 'TC_PATH',        plugin_dir_path( __FILE__ ) );
define( 'TC_URL',         plugin_dir_url( __FILE__ ) );
define( 'TC_VERSION',     '2.1.2' );

// ── GitHub Auto-Updater ────────────────────────────────────────
if ( file_exists( TC_PATH . 'vendor/autoload.php' ) ) {
    require_once TC_PATH . 'vendor/autoload.php';

    $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/crispyparmaham/wp-event-calender',
        __FILE__,
        'training-calendar'
    );
    // Optionaler GitHub-Token für höhere API-Rate-Limits (aus config.local.php)
    if ( defined( 'TC_GITHUB_TOKEN' ) && TC_GITHUB_TOKEN !== '' ) {
        $updateChecker->setAuthentication( TC_GITHUB_TOKEN );
    }
}

require_once TC_PATH . 'includes/admin/settings.php';
require_once TC_PATH . 'includes/admin/dashboard.php';
require_once TC_PATH . 'includes/admin/admin-page.php';
require_once TC_PATH . 'includes/admin/events-overview.php';
require_once TC_PATH . 'includes/admin/categories.php';

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
    require_once TC_PATH . 'includes/admin/categories.php';
    tc_create_registrations_table();
    tc_create_categories_table();
    tc_install_default_categories();
    // iCal-Rewrite-Regeln flush
    tc_flush_rewrite_rules_for_ical();
}

// ── Cron beim Deaktivieren abmelden ───────────────────────────
register_deactivation_hook( __FILE__, 'tc_deactivate_reminder_cron' );
