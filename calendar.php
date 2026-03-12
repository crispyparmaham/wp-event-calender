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

define( 'TC_PATH',    plugin_dir_path( __FILE__ ) );
define( 'TC_URL',     plugin_dir_url( __FILE__ ) );
define( 'TC_VERSION', '2.0.7' );

require_once TC_PATH . 'includes/cpt.php';
require_once TC_PATH . 'includes/ajax.php';
require_once TC_PATH . 'includes/settings.php';
require_once TC_PATH . 'includes/admin-page.php';
require_once TC_PATH . 'includes/shortcode-calendar.php';
require_once TC_PATH . 'includes/shortcode-price-bar.php';
require_once TC_PATH . 'includes/registration.php';
require_once TC_PATH . 'includes/registration-admin-page.php';
require_once TC_PATH . 'includes/shortcode-registration.php';
require_once TC_PATH . 'includes/events-overview.php';

// ── Tabelle beim Aktivieren des Plugins anlegen ────────────────
register_activation_hook( __FILE__, 'tc_run_db_setup' );

function tc_run_db_setup() {
    require_once TC_PATH . 'includes/registration.php';
    tc_create_registrations_table();
}