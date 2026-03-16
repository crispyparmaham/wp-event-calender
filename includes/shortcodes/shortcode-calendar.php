<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Shortcode: [time_calendar]
//
// Attribute:
//   type      = "all" | "training" | "seminar"
//   view      = "dayGridMonth" | "timeGridWeek" | "listMonth"
//   week_only = "true" | "false"
//
// URL-Parameter für Menü-Links:
//   ?tc_type=training  → Gruppentraining vorausgewählt
//   ?tc_type=seminar   → Seminare vorausgewählt
//   ?tc_type=all       → Alle (default)
// ─────────────────────────────────────────────
add_shortcode( 'time_calendar', function ( $atts ) {

    $atts = shortcode_atts( array(
        'type'      => 'all',
        'view'      => 'dayGridMonth',
        'week_only' => '',
    ), $atts, 'time_calendar' );

    $categories    = tc_get_all_categories();
    $allowed_types = array_merge( array( 'all' ), array_column( $categories, 'slug' ) );
    $url_type      = isset( $_GET['tc_type'] )
        ? sanitize_text_field( $_GET['tc_type'] )
        : null;
    $active_type   = ( $url_type && in_array( $url_type, $allowed_types, true ) )
        ? $url_type
        : $atts['type'];

    // Harter Filter: wenn type != 'all' (und kein URL-Override), nur diese Kategorie anzeigen
    $locked_type = ( $active_type !== 'all' ) ? $active_type : '';

    $valid_views = array( 'dayGridMonth', 'timeGridWeek', 'listMonth' );
    $view        = in_array( $atts['view'], $valid_views, true ) ? $atts['view'] : 'dayGridMonth';

    // Farbmodus aus Plugin-Einstellungen
    $dark = tc_get_setting( 'calendar_mode', 'light' ) === 'dark';

    // "Nur aktuelle Woche" – Shortcode-Attribut hat Vorrang vor globaler Einstellung
    $global_week_only = tc_get_setting( 'frontend_week_only', '0' ) === '1';
    if ( $atts['week_only'] === 'true' ) {
        $week_only = true;
    } elseif ( $atts['week_only'] === 'false' ) {
        $week_only = false;
    } else {
        $week_only = $global_week_only;
    }

    // Event-Übersicht
    $show_event_list  = tc_get_setting( 'show_event_list', '0' ) === '1';
    $event_list_title = tc_get_setting( 'event_list_title', 'Unsere Events' ) ?: 'Unsere Events';

    static $instance = 0;
    $instance++;
    $uid = 'tc-frontend-' . $instance;

    tc_enqueue_calendar_assets();

    ob_start(); ?>
    <div class="tc-frontend-wrap<?php echo $dark ? ' tc-dark' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>-wrap">

        <div class="tc-filter-bar" role="tablist" aria-label="Event-Typ filtern">
            <button class="tc-filter-btn <?php echo $active_type === 'all' ? 'is-active' : ''; ?>"
                    data-type="all" role="tab">Alle</button>
            <?php foreach ( $categories as $cat ) : ?>
            <button class="tc-filter-btn <?php echo $active_type === $cat['slug'] ? 'is-active' : ''; ?>"
                    data-type="<?php echo esc_attr( $cat['slug'] ); ?>" role="tab">
                <span class="tc-dot" style="background:<?php echo esc_attr( $cat['color'] ); ?>;"></span>
                <?php echo esc_html( $cat['name'] ); ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="tc-view-toggle">
            <button class="tc-view-btn is-active" data-tc-view="calendar">Kalender</button>
            <button class="tc-view-btn" data-tc-view="week-plan">Wochenplan</button>
        </div>

        <div class="tc-loader" id="<?php echo esc_attr( $uid ); ?>-loader" style="display:none;">
            <div class="tc-loader-spinner"></div>
            <span>Events werden geladen…</span>
        </div>

        <div class="tc-frontend-calendar"
             id="<?php echo esc_attr( $uid ); ?>"
             data-type="<?php echo esc_attr( $active_type ); ?>"
             data-view="<?php echo esc_attr( $view ); ?>"
             data-week-only="<?php echo $week_only ? '1' : '0'; ?>"
             data-show-event-list="<?php echo $show_event_list ? '1' : '0'; ?>"
             data-event-list-title="<?php echo esc_attr( $event_list_title ); ?>">
        </div>

        <div class="tc-week-plan" id="<?php echo esc_attr( $uid ); ?>-week-plan" style="display:none;">
            <div class="tc-week-plan-nav">
                <button class="tc-week-plan-prev" aria-label="Vorherige Woche">&#8592; Vorherige Woche</button>
                <span class="tc-week-plan-label"></span>
                <button class="tc-week-plan-next" aria-label="Nächste Woche">Nächste Woche &#8594;</button>
            </div>
            <div class="tc-week-plan-body"></div>
        </div>

        <?php if ( $show_event_list ) : ?>
        <div class="tc-event-overview"></div>
        <?php endif; ?>

        <div class="tc-popover" id="<?php echo esc_attr( $uid ); ?>-popover"
             style="display:none;" role="dialog" aria-modal="true">
            <button class="tc-popover-close" aria-label="Schließen">&times;</button>
            <div class="tc-popover-body"></div>
        </div>
        <div class="tc-popover-backdrop"
             id="<?php echo esc_attr( $uid ); ?>-backdrop" style="display:none;"></div>

    </div>
    <?php
    return ob_get_clean();
} );

// ─────────────────────────────────────────────
// Assets Kalender – früh einreihen damit
// Oxygen Builder die Styles im <head> ausgibt.
// ─────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'tc-calendar-frontend',
        TC_URL . 'assets/css/frontend/calendar-frontend.css',
        array(),
        TC_VERSION
    );
    wp_enqueue_style(
        'tc-event-list',
        TC_URL . 'assets/css/frontend/event-list.css',
        array(),
        TC_VERSION
    );
} );

function tc_enqueue_calendar_assets() {
    static $loaded = false;
    if ( $loaded ) return;
    $loaded = true;

    wp_enqueue_script(
        'fullcalendar',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
        array(),
        '6.1.15',
        true
    );

    wp_enqueue_script(
        'tc-frontend',
        TC_URL . 'assets/js/frontend/calendar-frontend.js',
        array( 'fullcalendar' ),
        TC_VERSION,
        true
    );

    wp_localize_script( 'tc-frontend', 'TC_Frontend', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'tc_nonce' ),
    ) );
}
