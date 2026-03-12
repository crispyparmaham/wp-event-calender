<?php
defined( 'ABSPATH' ) || exit;

// ── GitHub Auto-Updater ────────────────────────────────────────
// Nutzt plugin-update-checker (yahnis-elsts/plugin-update-checker)
// um WordPress-Updates direkt aus GitHub Releases zu beziehen.
// Installation: composer install (einmalig auf dem Server)
// ──────────────────────────────────────────────────────────────
if ( ! file_exists( TC_PATH . 'vendor/autoload.php' ) ) {
    return;
}

require_once TC_PATH . 'vendor/autoload.php';

$updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/crispyparmaham/wp-event-calender',
    TC_PATH . 'functions.php',
    'training-calendar'
);

// Optionaler GitHub-Token für höhere API-Rate-Limits (aus config.local.php)
if ( defined( 'TC_GITHUB_TOKEN' ) && TC_GITHUB_TOKEN !== '' ) {
    $updateChecker->setAuthentication( TC_GITHUB_TOKEN );
}
