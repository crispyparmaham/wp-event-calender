<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Settings-Seite registrieren
// ─────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=time_event',
        'Einstellungen',
        'Einstellungen',
        'administrator',
        'time-calendar-settings',
        'tc_render_settings_page'
    );
} );

// ─────────────────────────────────────────────
// Einstellungen registrieren
// ─────────────────────────────────────────────
add_action( 'admin_init', function () {
    register_setting( 'tc_settings_group', 'tc_settings', array(
        'sanitize_callback' => 'tc_sanitize_settings',
        'default'           => array(
            'calendar_mode'           => 'light',
            'registration_email'      => get_option( 'admin_email' ),
            'reminder_enabled'        => '0',
            'primary_color'           => '#4f46e5',
            'default_view'            => 'timeGridWeek',
            'week_starts_on'          => 'monday',
            'frontend_week_only'      => '0',
            'show_event_list'         => '0',
            'event_list_title'        => 'Unsere Events',
            'mobile_calendar_view'    => 'optimized',
            'mobile_hint_box'         => '1',
            'time_column_label'       => 'hours',
            'event_time_display'      => 'none',
            'week_plan_time_position' => 'standard',
            // Design Tokens
            'token_bg_light'              => '',
            'token_bg_dark'               => '',
            'token_bg_secondary_light'    => '',
            'token_bg_secondary_dark'     => '',
            'token_surface_light'         => '',
            'token_surface_dark'          => '',
            'token_text_light'            => '',
            'token_text_dark'             => '',
            'token_text_muted_light'      => '',
            'token_text_muted_dark'       => '',
            'token_border_light'          => '',
            'token_border_dark'           => '',
            'token_radius'                => '',
            'token_font_family'           => '',
            'token_custom_css'            => '',
            // SEO
            'schema_enabled'          => '1',
        ),
    ) );
} );

// ─────────────────────────────────────────────
// Sanitize Callback
// ─────────────────────────────────────────────
function tc_sanitize_settings( $input ) {
    $clean = array();

    $clean['calendar_mode'] = isset( $input['calendar_mode'] ) && $input['calendar_mode'] === 'dark'
        ? 'dark'
        : 'light';

    $clean['registration_email'] = isset( $input['registration_email'] )
        ? sanitize_email( $input['registration_email'] )
        : get_option( 'admin_email' );
    if ( ! is_email( $clean['registration_email'] ) ) {
        $clean['registration_email'] = get_option( 'admin_email' );
    }

    $clean['reminder_enabled']   = ! empty( $input['reminder_enabled'] )   ? '1' : '0';
    $clean['frontend_week_only'] = ! empty( $input['frontend_week_only'] ) ? '1' : '0';
    $clean['show_event_list']    = ! empty( $input['show_event_list'] )    ? '1' : '0';
    $clean['mobile_hint_box']    = ! empty( $input['mobile_hint_box'] )    ? '1' : '0';

    $clean['event_list_title'] = isset( $input['event_list_title'] )
        ? sanitize_text_field( $input['event_list_title'] )
        : 'Unsere Events';
    if ( empty( $clean['event_list_title'] ) ) {
        $clean['event_list_title'] = 'Unsere Events';
    }

    $color = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '';
    $clean['primary_color'] = $color ?: '#4f46e5';

    $valid_views = array( 'dayGridMonth', 'timeGridWeek', 'listMonth' );
    $clean['default_view'] = isset( $input['default_view'] ) && in_array( $input['default_view'], $valid_views, true )
        ? $input['default_view']
        : 'timeGridWeek';

    $valid_week_start = array( 'monday', 'sunday' );
    $clean['week_starts_on'] = isset( $input['week_starts_on'] ) && in_array( $input['week_starts_on'], $valid_week_start, true )
        ? $input['week_starts_on']
        : 'monday';

    $valid_mobile = array( 'slider', 'optimized', 'scaled', 'desktop' );
    $clean['mobile_calendar_view'] = isset( $input['mobile_calendar_view'] ) && in_array( $input['mobile_calendar_view'], $valid_mobile, true )
        ? $input['mobile_calendar_view']
        : 'optimized';

    $valid_col_label = array( 'hours', 'groups', 'both' );
    $clean['time_column_label'] = isset( $input['time_column_label'] ) && in_array( $input['time_column_label'], $valid_col_label, true )
        ? $input['time_column_label']
        : 'hours';

    $valid_evt_time = array( 'none', 'normal', 'prominent' );
    $clean['event_time_display'] = isset( $input['event_time_display'] ) && in_array( $input['event_time_display'], $valid_evt_time, true )
        ? $input['event_time_display']
        : 'none';

    // Abwärtskompatibilität: alte zusammengefasste Einstellung
    $raw_time = isset( $input['week_plan_time_position'] ) ? $input['week_plan_time_position'] : 'standard';
    if ( $raw_time === 'left' ) $raw_time = 'standard';
    $valid_time_pos = array( 'standard', 'compact', 'above' );
    $clean['week_plan_time_position'] = in_array( $raw_time, $valid_time_pos, true ) ? $raw_time : 'standard';

    // Design Tokens — Farbpaare Light / Dark
    $color_token_keys = array(
        'token_bg_light',          'token_bg_dark',
        'token_bg_secondary_light','token_bg_secondary_dark',
        'token_surface_light',     'token_surface_dark',
        'token_text_light',        'token_text_dark',
        'token_text_muted_light',  'token_text_muted_dark',
        'token_border_light',      'token_border_dark',
    );
    foreach ( $color_token_keys as $k ) {
        $clean[ $k ] = isset( $input[ $k ] ) ? ( sanitize_hex_color( $input[ $k ] ) ?: '' ) : '';
    }

    $raw_radius = isset( $input['token_radius'] ) ? sanitize_text_field( $input['token_radius'] ) : '';
    $r_val      = (int) filter_var( $raw_radius, FILTER_SANITIZE_NUMBER_INT );
    $clean['token_radius'] = $r_val > 0 ? $r_val . 'px' : '';

    $clean['token_font_family'] = isset( $input['token_font_family'] )
        ? sanitize_text_field( $input['token_font_family'] )
        : '';

    // Custom CSS: --tc-* Deklarationen, .tc-dark{}-Blöcke und /* Kommentare */ erlaubt
    $raw_css = isset( $input['token_custom_css'] ) ? wp_unslash( $input['token_custom_css'] ) : '';
    $raw_css = preg_replace( '/<[^>]*>/i',           '', $raw_css ); // kein HTML
    $raw_css = preg_replace( '/\burl\s*\(/i',         '', $raw_css ); // kein url()
    $raw_css = preg_replace( '/\bexpression\s*\(/i',  '', $raw_css ); // kein expression()
    $raw_css = preg_replace( '/javascript\s*:/i',     '', $raw_css ); // kein JS
    preg_match_all(
        '/--tc-[a-z][a-z0-9\-]*\s*:\s*[^;]+;|\.tc-dark\s*\{[^}]*\}|\/\*[^*]*(?:\*(?!\/)[^*]*)*\*\//',
        $raw_css,
        $css_matches
    );
    $clean['token_custom_css'] = trim( implode( "\n", $css_matches[0] ) );

    // SEO
    $clean['schema_enabled'] = ! empty( $input['schema_enabled'] ) ? '1' : '0';

    return $clean;
}

// ─────────────────────────────────────────────
// Helper: aktuelle Einstellung lesen
// ─────────────────────────────────────────────
function tc_get_setting( $key, $default = '' ) {
    $settings = get_option( 'tc_settings', array() );
    return $settings[ $key ] ?? $default;
}

// ─────────────────────────────────────────────
// Helper: Primärfarbe
// ─────────────────────────────────────────────
function tc_get_primary_color() {
    $color = tc_get_setting( 'primary_color', '#4f46e5' );
    return $color ?: '#4f46e5';
}

function tc_hex_to_rgb( $hex ) {
    $hex = ltrim( $hex, '#' );
    if ( strlen( $hex ) === 3 ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    return array(
        'r' => hexdec( substr( $hex, 0, 2 ) ),
        'g' => hexdec( substr( $hex, 2, 2 ) ),
        'b' => hexdec( substr( $hex, 4, 2 ) ),
    );
}

function tc_primary_rgba( $opacity ) {
    $rgb = tc_hex_to_rgb( tc_get_primary_color() );
    return 'rgba(' . $rgb['r'] . ',' . $rgb['g'] . ',' . $rgb['b'] . ',' . $opacity . ')';
}

function tc_primary_darken( $percent ) {
    $rgb    = tc_hex_to_rgb( tc_get_primary_color() );
    $factor = 1 - ( $percent / 100 );
    $r      = max( 0, (int) round( $rgb['r'] * $factor ) );
    $g      = max( 0, (int) round( $rgb['g'] * $factor ) );
    $b      = max( 0, (int) round( $rgb['b'] * $factor ) );
    return sprintf( '#%02x%02x%02x', $r, $g, $b );
}

// ─────────────────────────────────────────────
// CSS Custom Properties im <head> ausgeben
// ─────────────────────────────────────────────
add_action( 'wp_head', function () {
    $primary            = tc_get_primary_color();
    $primary_dark       = tc_primary_darken( 15 );
    $primary_light      = tc_primary_rgba( 0.12 );
    $primary_light_dark = tc_primary_rgba( 0.25 );
    ?>
<style id="tc-primary-color">
:root {
    --tc-primary: <?php echo esc_attr( $primary ); ?>;
    --tc-primary-dark: <?php echo esc_attr( $primary_dark ); ?>;
    --tc-primary-light: <?php echo $primary_light; ?>;
}
.tc-dark {
    --tc-primary-light: <?php echo $primary_light_dark; ?>;
}
</style>
    <?php
} );

// ─────────────────────────────────────────────
// Design Tokens als wp_add_inline_style nach design-system.css
// Priorität 20 = nach Enqueue von tc-design-system (Prio 5)
// → .tc-dark{} in der gleichen Stylesheet-Quelle → korrekte Spezifität
// ─────────────────────────────────────────────
function tc_build_token_css(): string {
    $color_map = array(
        'token_bg_light'           => '--tc-bg',
        'token_bg_secondary_light' => '--tc-bg-secondary',
        'token_surface_light'      => '--tc-surface',
        'token_text_light'         => '--tc-text',
        'token_text_muted_light'   => '--tc-text-muted',
        'token_border_light'       => '--tc-border',
    );
    $dark_map = array(
        'token_bg_dark'            => '--tc-bg',
        'token_bg_secondary_dark'  => '--tc-bg-secondary',
        'token_surface_dark'       => '--tc-surface',
        'token_text_dark'          => '--tc-text',
        'token_text_muted_dark'    => '--tc-text-muted',
        'token_border_dark'        => '--tc-border',
    );

    $radius      = tc_get_setting( 'token_radius',      '' );
    $font_family = tc_get_setting( 'token_font_family', '' );
    $custom_css  = tc_get_setting( 'token_custom_css',  '' );

    $root_lines = array();
    $dark_lines = array();

    foreach ( $color_map as $key => $prop ) {
        $val = tc_get_setting( $key, '' );
        if ( $val ) $root_lines[] = '  ' . $prop . ': ' . $val . ';';
    }

    if ( $radius ) {
        $r            = max( 0, (int) filter_var( $radius, FILTER_SANITIZE_NUMBER_INT ) );
        $sm           = max( 2, (int) round( $r * 0.6 ) ) . 'px';
        $lg           = (int) round( $r * 1.6 ) . 'px';
        $root_lines[] = '  --tc-radius: ' . $r . 'px;';
        $root_lines[] = '  --tc-radius-sm: ' . $sm . ';';
        $root_lines[] = '  --tc-radius-lg: ' . $lg . ';';
    }

    if ( $font_family ) {
        $root_lines[] = '  --tc-font-family: ' . $font_family . ';';
    }

    foreach ( $dark_map as $key => $prop ) {
        $val = tc_get_setting( $key, '' );
        if ( $val ) $dark_lines[] = '  ' . $prop . ': ' . $val . ';';
    }

    $css = '';
    if ( $root_lines ) {
        $css .= ":root {\n" . implode( "\n", $root_lines ) . "\n}\n";
    }
    if ( $dark_lines ) {
        $css .= ".tc-dark {\n" . implode( "\n", $dark_lines ) . "\n}\n";
    }
    if ( $custom_css ) {
        $css .= $custom_css . "\n";
    }

    return $css;
}

add_action( 'wp_enqueue_scripts', function () {
    $css = tc_build_token_css();
    if ( $css ) {
        wp_add_inline_style( 'tc-design-system', $css );
    }
}, 20 );

// ─────────────────────────────────────────────
// Settings-Seite rendern
// ─────────────────────────────────────────────
function tc_render_settings_page() {
    $updated = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true';

    // Aktuelle Werte
    $primary_color           = tc_get_primary_color();
    $calendar_mode           = tc_get_setting( 'calendar_mode', 'light' );
    $token_bg_light              = tc_get_setting( 'token_bg_light',              '' );
    $token_bg_dark               = tc_get_setting( 'token_bg_dark',               '' );
    $token_bg_secondary_light    = tc_get_setting( 'token_bg_secondary_light',    '' );
    $token_bg_secondary_dark     = tc_get_setting( 'token_bg_secondary_dark',     '' );
    $token_surface_light         = tc_get_setting( 'token_surface_light',         '' );
    $token_surface_dark          = tc_get_setting( 'token_surface_dark',          '' );
    $token_text_light            = tc_get_setting( 'token_text_light',            '' );
    $token_text_dark             = tc_get_setting( 'token_text_dark',             '' );
    $token_text_muted_light      = tc_get_setting( 'token_text_muted_light',      '' );
    $token_text_muted_dark       = tc_get_setting( 'token_text_muted_dark',       '' );
    $token_border_light          = tc_get_setting( 'token_border_light',          '' );
    $token_border_dark           = tc_get_setting( 'token_border_dark',           '' );
    $token_radius                = tc_get_setting( 'token_radius',                '' );
    $token_font_family           = tc_get_setting( 'token_font_family',           '' );
    $token_custom_css            = tc_get_setting( 'token_custom_css',            '' );
    $schema_enabled          = tc_get_setting( 'schema_enabled',    '1' );
    $default_view            = tc_get_setting( 'default_view', 'timeGridWeek' );
    $week_starts_on          = tc_get_setting( 'week_starts_on', 'monday' );
    $week_only               = tc_get_setting( 'frontend_week_only', '0' );
    $show_event_list         = tc_get_setting( 'show_event_list', '0' );
    $event_list_title        = tc_get_setting( 'event_list_title', 'Unsere Events' ) ?: 'Unsere Events';
    $reg_email               = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );
    $reminder                = tc_get_setting( 'reminder_enabled', '0' );
    $mobile_calendar_view    = tc_get_setting( 'mobile_calendar_view', 'optimized' );
    $mobile_hint_box         = tc_get_setting( 'mobile_hint_box', '1' );
    $time_column_label       = tc_get_setting( 'time_column_label', 'hours' );
    $event_time_display      = tc_get_setting( 'event_time_display', 'none' );
    $week_plan_time_position = tc_get_setting( 'week_plan_time_position', 'standard' );
    if ( $week_plan_time_position === 'left' ) $week_plan_time_position = 'standard';
    ?>
    <div class="wrap tc-stg-wrap">

        <!-- ── Toast ──────────────────────────────────────── -->
        <div class="tc-stg-toast" id="tc-stg-toast" <?php echo $updated ? 'data-show="true"' : ''; ?>>
            <span class="dashicons dashicons-yes-alt"></span>
            Einstellungen wurden gespeichert.
        </div>

        <!-- ── Header ─────────────────────────────────────── -->
        <div class="tc-stg-header">
            <span class="dashicons dashicons-calendar-alt tc-stg-header-icon"></span>
            <div>
                <h1>Time Calendar</h1>
                <p>Version <strong><?php echo esc_html( TC_VERSION ); ?></strong> &nbsp;·&nbsp; Plugin-Einstellungen</p>
            </div>
        </div>

        <!-- ── Tab-Navigation ─────────────────────────────── -->
        <nav class="tc-stg-tabs" role="tablist">
            <button class="tc-stg-tab" data-tab="design"   role="tab">🎨 Design</button>
            <button class="tc-stg-tab" data-tab="kalender" role="tab">📅 Kalender</button>
            <button class="tc-stg-tab" data-tab="email"    role="tab">📧 E-Mails &amp; Anmeldungen</button>
            <button class="tc-stg-tab" data-tab="seo"      role="tab">🔍 SEO</button>
            <button class="tc-stg-tab" data-tab="updates"  role="tab">🔄 Updates</button>
        </nav>

        <!-- ── Einzel-Form mit allen Feldern ──────────────── -->
        <form method="post" action="options.php" class="tc-stg-form">
            <?php settings_fields( 'tc_settings_group' ); ?>

            <!-- ═══ Tab: Design ═══════════════════════════════ -->
            <div class="tc-stg-pane" data-tab="design">
                <div class="tc-stg-card">

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Primärfarbe</strong>
                            <span>Hauptfarbe für alle interaktiven Elemente (Buttons, Akzente, Fokus-Ringe).</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <div class="tc-stg-color-wrap">
                                <input
                                    type="color"
                                    id="tc-primary-color-input"
                                    name="tc_settings[primary_color]"
                                    value="<?php echo esc_attr( $primary_color ); ?>"
                                >
                                <code id="tc-primary-color-label"><?php echo esc_html( $primary_color ); ?></code>
                            </div>
                            <p class="tc-stg-hint">Standard: <code>#4f46e5</code> (Indigo)</p>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Farbmodus</strong>
                            <span>Gilt für alle <code>[time_calendar]</code> Shortcodes im Frontend.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-toggle">
                                <input
                                    type="checkbox"
                                    name="tc_settings[calendar_mode]"
                                    value="dark"
                                    <?php checked( $calendar_mode, 'dark' ); ?>
                                >
                                <span class="tc-toggle-track"></span>
                                <span class="tc-toggle-label" id="tc-mode-label">
                                    <?php echo $calendar_mode === 'dark' ? 'Dark Mode' : 'Light Mode'; ?>
                                </span>
                            </label>
                        </div>
                    </div>

                </div><!-- .tc-stg-card -->

                <!-- ── Design Token Editor ────────────────────── -->
                <div class="tc-stg-card">
                    <h3 class="tc-stg-section-title">Design Tokens</h3>
                    <p class="tc-stg-card-desc">
                        Leer gelassene Felder überschreiben nichts — die Fallback-Werte aus <code>design-system.css</code> gelten dann automatisch.
                    </p>

                    <?php
                    // Hilfsfunktion für eine Token-Zeile (Light + Dark Picker)
                    $tc_token_row = function( $label, $css_var, $key_light, $key_dark, $default_light, $default_dark )
                        use ( $token_bg_light, $token_bg_dark,
                              $token_bg_secondary_light, $token_bg_secondary_dark,
                              $token_surface_light, $token_surface_dark,
                              $token_text_light, $token_text_dark,
                              $token_text_muted_light, $token_text_muted_dark,
                              $token_border_light, $token_border_dark ) {
                        $val_l = $$key_light ?: '';
                        $val_d = $$key_dark  ?: '';
                        ?>
                        <div class="tc-token-grid-row">
                            <div class="tc-token-label">
                                <strong><?php echo esc_html( $label ); ?></strong>
                                <code><?php echo esc_html( $css_var ); ?></code>
                            </div>
                            <div class="tc-stg-color-wrap">
                                <input type="color"
                                       name="tc_settings[<?php echo esc_attr( $key_light ); ?>]"
                                       id="tc-<?php echo esc_attr( $key_light ); ?>"
                                       value="<?php echo esc_attr( $val_l ?: $default_light ); ?>">
                                <code id="tc-<?php echo esc_attr( $key_light ); ?>-lbl">
                                    <?php echo esc_html( $val_l ?: $default_light ); ?>
                                </code>
                            </div>
                            <div class="tc-stg-color-wrap">
                                <input type="color"
                                       name="tc_settings[<?php echo esc_attr( $key_dark ); ?>]"
                                       id="tc-<?php echo esc_attr( $key_dark ); ?>"
                                       value="<?php echo esc_attr( $val_d ?: $default_dark ); ?>">
                                <code id="tc-<?php echo esc_attr( $key_dark ); ?>-lbl">
                                    <?php echo esc_html( $val_d ?: $default_dark ); ?>
                                </code>
                            </div>
                        </div>
                        <?php
                    };
                    ?>

                    <!-- Spaltenüberschriften -->
                    <div class="tc-token-grid-head">
                        <div class="tc-token-col-head">Token</div>
                        <div class="tc-token-col-head">&#9728; Light Mode</div>
                        <div class="tc-token-col-head">&#9790; Dark Mode</div>
                    </div>

                    <?php
                    $tc_token_row( 'Hintergrund',         '--tc-bg',           'token_bg_light',           'token_bg_dark',           '#ffffff', '#151515' );
                    $tc_token_row( 'Hintergrund (sek.)',  '--tc-bg-secondary', 'token_bg_secondary_light', 'token_bg_secondary_dark', '#f8fafc', '#1e293b' );
                    $tc_token_row( 'Fläche',              '--tc-surface',      'token_surface_light',      'token_surface_dark',      '#f1f5f9', '#1a1a1a' );
                    $tc_token_row( 'Text',                '--tc-text',         'token_text_light',         'token_text_dark',         '#0f172a', '#f1f5f9' );
                    $tc_token_row( 'Text gedämpft',       '--tc-text-muted',   'token_text_muted_light',   'token_text_muted_dark',   '#64748b', '#8e8e8e' );
                    $tc_token_row( 'Rahmen',              '--tc-border',       'token_border_light',       'token_border_dark',       '#e2e8f0', '#2d2d2d' );
                    ?>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Ecken-Radius</strong>
                            <span>Überschreibt <code>--tc-radius</code> (gilt für beide Modi).<br>
                            <code>--tc-radius-sm</code> (60&nbsp;%) und <code>--tc-radius-lg</code> (160&nbsp;%) werden automatisch berechnet.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <input type="text" name="tc_settings[token_radius]"
                                   value="<?php echo esc_attr( $token_radius ); ?>"
                                   class="tc-stg-input tc-stg-input--narrow"
                                   placeholder="z.B. 10px">
                            <p class="tc-stg-hint">Standard: <code>10px</code>. Nur numerischer px-Wert.</p>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Schriftfamilie</strong>
                            <span>Überschreibt <code>--tc-font-family</code> (gilt für beide Modi). Leer lassen für Systemschrift.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <input type="text" name="tc_settings[token_font_family]"
                                   value="<?php echo esc_attr( $token_font_family ); ?>"
                                   class="tc-stg-input"
                                   placeholder="'Inter', sans-serif">
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Experten-CSS</strong>
                            <span>Freie <code>--tc-*</code> Überschreibungen.<br>
                            Erlaubt: <code>--tc-name: wert;</code> Deklarationen,<br>
                            <code>.tc-dark { --tc-name: wert; }</code> Blöcke und <code>/* Kommentare */</code>.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <textarea name="tc_settings[token_custom_css]"
                                      class="tc-stg-input tc-stg-input--code"
                                      rows="8"
                                      placeholder="/* Light Mode */
--tc-shadow: none;
--tc-surface-raised: #ffffff;

/* Dark Mode */
.tc-dark { --tc-shadow: none; }"><?php echo esc_textarea( $token_custom_css ); ?></textarea>
                            <p class="tc-stg-hint">Kein <code>&lt;style&gt;</code>, kein JavaScript, keine anderen Selektoren.</p>
                        </div>
                    </div>

                </div><!-- .tc-stg-card Design Tokens -->

                <div class="tc-stg-actions">
                    <button type="submit" class="tc-stg-save">Einstellungen speichern</button>
                </div>
            </div><!-- .tc-stg-pane[design] -->

            <!-- ═══ Tab: Kalender ═════════════════════════════ -->
            <div class="tc-stg-pane" data-tab="kalender">

                <!-- ── Abschnitt 1: Allgemein ──────────────────── -->
                <div class="tc-stg-card">
                    <h3 class="tc-stg-section-title">Allgemein</h3>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Standardansicht</strong>
                            <span>Welche Ansicht soll beim Laden des Kalenders angezeigt werden?</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <select name="tc_settings[default_view]" class="tc-stg-select">
                                <option value="timeGridWeek" <?php selected( $default_view, 'timeGridWeek' ); ?>>Woche</option>
                                <option value="dayGridMonth" <?php selected( $default_view, 'dayGridMonth' ); ?>>Monat</option>
                                <option value="listMonth"    <?php selected( $default_view, 'listMonth' ); ?>>Liste</option>
                            </select>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Woche beginnt am</strong>
                            <span>Erster Tag der Woche im Kalender.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <select name="tc_settings[week_starts_on]" class="tc-stg-select">
                                <option value="monday" <?php selected( $week_starts_on, 'monday' ); ?>>Montag</option>
                                <option value="sunday" <?php selected( $week_starts_on, 'sunday' ); ?>>Sonntag</option>
                            </select>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Nur aktuelle Woche anzeigen</strong>
                            <span>Blendet Navigation und Ansichtswechsel aus. Der Nutzer sieht immer nur die aktuelle Woche.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-toggle">
                                <input type="checkbox" name="tc_settings[frontend_week_only]" value="1"
                                    <?php checked( $week_only, '1' ); ?>>
                                <span class="tc-toggle-track"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ── Abschnitt 2: Desktop-Ansicht ────────────── -->
                <div class="tc-stg-card">
                    <h3 class="tc-stg-section-title">Desktop-Ansicht</h3>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Zeitspalte links</strong>
                            <span>Was in der Zeitspalte der Wochenansicht angezeigt wird.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[time_column_label]"
                                    value="hours" <?php checked( $time_column_label, 'hours' ); ?>>
                                Uhrzeiten
                            </label>
                            <p class="tc-stg-hint tc-stg-col-hint" data-for="hours"
                               <?php echo $time_column_label !== 'hours' ? 'style="display:none"' : ''; ?>>
                                08:00, 09:00, 10:00… jede Stunde
                            </p>

                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[time_column_label]"
                                    value="groups" <?php checked( $time_column_label, 'groups' ); ?>>
                                Tagesgruppen
                            </label>
                            <p class="tc-stg-hint tc-stg-col-hint" data-for="groups"
                               <?php echo $time_column_label !== 'groups' ? 'style="display:none"' : ''; ?>>
                                Vormittag, Nachmittag, Abend
                            </p>

                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[time_column_label]"
                                    value="both" <?php checked( $time_column_label, 'both' ); ?>>
                                Beides
                            </label>
                            <p class="tc-stg-hint tc-stg-col-hint" data-for="both"
                               <?php echo $time_column_label !== 'both' ? 'style="display:none"' : ''; ?>>
                                Tagesgruppe klein über der Uhrzeit
                            </p>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Zeitstempel im Event</strong>
                            <span>Ob und wie die Uhrzeit im Event-Block angezeigt wird.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[event_time_display]"
                                    value="none" <?php checked( $event_time_display, 'none' ); ?>>
                                Kein Zeitstempel
                            </label>
                            <p class="tc-stg-hint tc-stg-evt-hint" data-for="none"
                               <?php echo $event_time_display !== 'none' ? 'style="display:none"' : ''; ?>>
                                Nur Titel im Event-Block
                            </p>

                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[event_time_display]"
                                    value="normal" <?php checked( $event_time_display, 'normal' ); ?>>
                                Normal
                            </label>
                            <p class="tc-stg-hint tc-stg-evt-hint" data-for="normal"
                               <?php echo $event_time_display !== 'normal' ? 'style="display:none"' : ''; ?>>
                                Uhrzeit klein über dem Titel
                            </p>

                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[event_time_display]"
                                    value="prominent" <?php checked( $event_time_display, 'prominent' ); ?>>
                                Prominent
                            </label>
                            <p class="tc-stg-hint tc-stg-evt-hint" data-for="prominent"
                               <?php echo $event_time_display !== 'prominent' ? 'style="display:none"' : ''; ?>>
                                Uhrzeit groß und fett über dem Titel
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ── Abschnitt 3: Mobile-Ansicht ─────────────── -->
                <div class="tc-stg-card">
                    <h3 class="tc-stg-section-title">Mobile-Ansicht</h3>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Mobile Darstellung</strong>
                            <span>Wie der Kalender auf Mobilgeräten dargestellt wird.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[mobile_calendar_view]"
                                    value="optimized" <?php checked( $mobile_calendar_view, 'optimized' ); ?>>
                                Optimiert für Mobil
                            </label>
                            <p class="tc-stg-hint tc-stg-mobile-hint" data-for="optimized"
                               <?php echo $mobile_calendar_view !== 'optimized' ? 'style="display:none"' : ''; ?>>
                                Automatisch angepasste Ansicht für kleine Bildschirme.
                            </p>
                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[mobile_calendar_view]"
                                    value="slider" <?php checked( $mobile_calendar_view, 'slider' ); ?>>
                                Tages-Slider
                            </label>
                            <p class="tc-stg-hint tc-stg-mobile-hint" data-for="slider"
                               <?php echo $mobile_calendar_view !== 'slider' ? 'style="display:none"' : ''; ?>>
                                Zeigt 2 Tage nebeneinander, horizontal scrollbar.
                                Zeitspalte bleibt links fixiert.
                            </p>
                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[mobile_calendar_view]"
                                    value="scaled" <?php checked( $mobile_calendar_view, 'scaled' ); ?>>
                                Desktop-Ansicht skaliert
                            </label>
                            <p class="tc-stg-hint tc-stg-mobile-hint" data-for="scaled"
                               <?php echo $mobile_calendar_view !== 'scaled' ? 'style="display:none"' : ''; ?>>
                                Kalender wird wie auf dem Desktop dargestellt und automatisch auf die Bildschirmbreite verkleinert.
                            </p>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row tc-stg-conditional" id="tc-hint-box-row"
                         <?php echo $mobile_calendar_view !== 'slider' ? 'style="display:none"' : ''; ?>>
                        <div class="tc-stg-row-left">
                            <strong>Hinweis-Box anzeigen</strong>
                            <span>Zeigt einen Swipe- und Querformat-Hinweis auf Mobile.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-toggle">
                                <input type="checkbox" name="tc_settings[mobile_hint_box]" value="1"
                                    <?php checked( $mobile_hint_box, '1' ); ?>>
                                <span class="tc-toggle-track"></span>
                            </label>
                            <p class="tc-stg-hint">Erscheint nur auf Geräten mit max. 768px Breite.</p>
                        </div>
                    </div>
                </div>

                <!-- ── Abschnitt 4: Event-Liste ────────────────── -->
                <div class="tc-stg-card">
                    <h3 class="tc-stg-section-title">Event-Liste unter Kalender</h3>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Event-Übersicht anzeigen</strong>
                            <span>Zeigt alle Events als klickbare Karten unterhalb des Kalenders.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-toggle">
                                <input type="checkbox" name="tc_settings[show_event_list]" value="1"
                                    id="tc-show-event-list" <?php checked( $show_event_list, '1' ); ?>>
                                <span class="tc-toggle-track"></span>
                            </label>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row tc-stg-conditional" id="tc-event-list-title-row"
                         <?php echo $show_event_list !== '1' ? 'style="display:none"' : ''; ?>>
                        <div class="tc-stg-row-left">
                            <strong>Überschrift der Liste</strong>
                            <span>Abschnittsüberschrift über der Event-Kartenansicht.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <input type="text" name="tc_settings[event_list_title]"
                                value="<?php echo esc_attr( $event_list_title ); ?>"
                                class="tc-stg-input" placeholder="z.B. Unsere Events">
                        </div>
                    </div>
                </div>

                <div class="tc-stg-actions">
                    <button type="submit" class="tc-stg-save">Einstellungen speichern</button>
                </div>
            </div><!-- .tc-stg-pane[kalender] -->

            <!-- ═══ Tab: E-Mails & Anmeldungen ════════════════ -->
            <div class="tc-stg-pane" data-tab="email">
                <div class="tc-stg-card">

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Bestätigungs-E-Mail Empfänger</strong>
                            <span>Diese Adresse empfängt Anmeldebestätigungen. Die Absenderadresse wird durch Fluent SMTP bestimmt.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <input
                                type="email"
                                name="tc_settings[registration_email]"
                                value="<?php echo esc_attr( $reg_email ); ?>"
                                class="tc-stg-input"
                                required
                            >
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Erinnerungsmail (3 Tage vorher)</strong>
                            <span>Sendet automatisch 3 Tage vor jedem Event eine Erinnerungsmail an alle Teilnehmer mit Status <em>Bestätigt</em>. Bereits gesendete Erinnerungen werden nicht erneut verschickt.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-toggle">
                                <input
                                    type="checkbox"
                                    name="tc_settings[reminder_enabled]"
                                    value="1"
                                    <?php checked( $reminder, '1' ); ?>
                                >
                                <span class="tc-toggle-track"></span>
                            </label>
                        </div>
                    </div>

                </div><!-- .tc-stg-card -->
                <div class="tc-stg-actions">
                    <button type="submit" class="tc-stg-save">Einstellungen speichern</button>
                </div>
            </div><!-- .tc-stg-pane[email] -->

            <!-- ═══ Tab: SEO ══════════════════════════════════ -->
            <div class="tc-stg-pane" data-tab="seo">
                <div class="tc-stg-card">
                    <h3 class="tc-stg-section-title">Strukturierte Daten</h3>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Schema.org JSON-LD</strong>
                            <span>Gibt auf Event-Detailseiten (<code>single-time_event</code>) automatisch strukturierte Daten aus. Verbessert die Darstellung in Google-Suchergebnissen.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-toggle">
                                <input type="checkbox" name="tc_settings[schema_enabled]" value="1"
                                    <?php checked( $schema_enabled, '1' ); ?>>
                                <span class="tc-toggle-track"></span>
                            </label>
                            <p class="tc-stg-hint">Standard: aktiviert. Gibt JSON-LD Event Markup im <code>&lt;head&gt;</code> aus.</p>
                        </div>
                    </div>

                </div><!-- .tc-stg-card -->
                <div class="tc-stg-actions">
                    <button type="submit" class="tc-stg-save">Einstellungen speichern</button>
                </div>
            </div><!-- .tc-stg-pane[seo] -->

            <!-- ═══ Tab: Updates ══════════════════════════════ -->
            <div class="tc-stg-pane" data-tab="updates">
                <div class="tc-stg-card">

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Plugin-Version</strong>
                            <span>Prüft auf neue Releases im
                                <a href="https://github.com/crispyparmaham/wp-event-calender/releases" target="_blank" rel="noopener">
                                    GitHub-Repository ↗
                                </a>.
                            </span>
                        </div>
                        <div class="tc-stg-row-right tc-stg-row-right--update">
                            <span class="tc-stg-version"><?php echo esc_html( TC_VERSION ); ?></span>
                            <a
                                href="<?php echo esc_url( admin_url( 'update-core.php?force-check=1' ) ); ?>"
                                class="tc-stg-update-btn"
                            >
                                <span class="dashicons dashicons-update"></span>
                                Jetzt auf Updates prüfen
                            </a>
                        </div>
                    </div>

                </div><!-- .tc-stg-card -->
            </div><!-- .tc-stg-pane[updates] -->

        </form>

        <!-- ── Inline-Scripts ──────────────────────────────── -->
        <script>
        (function () {
            // ── Color pickers ──────────────────────────────────
            function bindColorPicker(inputId, labelId) {
                var inp = document.getElementById(inputId);
                var lbl = document.getElementById(labelId);
                if (inp && lbl) {
                    inp.addEventListener('input', function () { lbl.textContent = inp.value; });
                }
            }
            bindColorPicker('tc-primary-color-input', 'tc-primary-color-label');
            // Design Token Farbpaare
            var tokenColorIds = [
                'token_bg_light',          'token_bg_dark',
                'token_bg_secondary_light','token_bg_secondary_dark',
                'token_surface_light',     'token_surface_dark',
                'token_text_light',        'token_text_dark',
                'token_text_muted_light',  'token_text_muted_dark',
                'token_border_light',      'token_border_dark'
            ];
            tokenColorIds.forEach(function(id) {
                bindColorPicker('tc-' + id, 'tc-' + id + '-lbl');
            });

            // ── Dark-Mode Label ────────────────────────────────
            var modeCb    = document.querySelector('input[name="tc_settings[calendar_mode]"]');
            var modeLabel = document.getElementById('tc-mode-label');
            if (modeCb && modeLabel) {
                modeCb.addEventListener('change', function () {
                    modeLabel.textContent = modeCb.checked ? 'Dark Mode' : 'Light Mode';
                });
            }

            // ── Tab-Switching ──────────────────────────────────
            var TABS_KEY  = 'tc_settings_active_tab';
            var tabBtns   = document.querySelectorAll('.tc-stg-tab');
            var tabPanes  = document.querySelectorAll('.tc-stg-pane');
            var validTabs = ['design', 'kalender', 'email', 'seo', 'updates'];

            function activateTab(id) {
                if (validTabs.indexOf(id) === -1) id = 'design';
                tabBtns.forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.dataset.tab === id);
                    btn.setAttribute('aria-selected', btn.dataset.tab === id ? 'true' : 'false');
                });
                tabPanes.forEach(function (pane) {
                    pane.classList.toggle('is-active', pane.dataset.tab === id);
                });
                try { localStorage.setItem(TABS_KEY, id); } catch (e) {}
                if (history.replaceState) {
                    history.replaceState(null, '', location.pathname + location.search + '#' + id);
                }
            }

            tabBtns.forEach(function (btn) {
                btn.addEventListener('click', function () { activateTab(btn.dataset.tab); });
            });

            // Initialer Tab: Hash → localStorage → 'design'
            var hash  = (location.hash || '').replace('#', '');
            var saved = '';
            try { saved = localStorage.getItem(TABS_KEY) || ''; } catch (e) {}
            activateTab(
                (validTabs.indexOf(hash)  !== -1 ? hash  : null) ||
                (validTabs.indexOf(saved) !== -1 ? saved : null) ||
                'design'
            );

            // ── Mobile-View Hints + Conditional Hint-Box ─────────
            var mobileRadios  = document.querySelectorAll('input[name="tc_settings[mobile_calendar_view]"]');
            var mobileHints   = document.querySelectorAll('.tc-stg-mobile-hint');
            var hintBoxRow    = document.getElementById('tc-hint-box-row');
            if (mobileHints.length) {
                mobileRadios.forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        mobileHints.forEach(function (h) {
                            h.style.display = h.dataset.for === radio.value && radio.checked ? '' : 'none';
                        });
                        if (hintBoxRow) {
                            hintBoxRow.style.display = radio.value === 'slider' ? '' : 'none';
                        }
                    });
                });
            }

            // ── Column-Label Hints ───────────────────────────────
            function bindRadioHints(radioName, hintClass) {
                var radios = document.querySelectorAll('input[name="' + radioName + '"]');
                var hints  = document.querySelectorAll('.' + hintClass);
                if (!hints.length) return;
                radios.forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        hints.forEach(function (h) {
                            h.style.display = h.dataset.for === radio.value && radio.checked ? '' : 'none';
                        });
                    });
                });
            }
            bindRadioHints('tc_settings[time_column_label]',  'tc-stg-col-hint');
            bindRadioHints('tc_settings[event_time_display]', 'tc-stg-evt-hint');

            // ── Event-List Conditional ───────────────────────────
            var eventListCb  = document.getElementById('tc-show-event-list');
            var eventListRow = document.getElementById('tc-event-list-title-row');
            if (eventListCb && eventListRow) {
                eventListCb.addEventListener('change', function () {
                    eventListRow.style.display = eventListCb.checked ? '' : 'none';
                });
            }

            // ── Toast ──────────────────────────────────────────
            var toast = document.getElementById('tc-stg-toast');
            if (toast && toast.dataset.show === 'true') {
                toast.classList.add('is-visible');
                setTimeout(function () { toast.classList.remove('is-visible'); }, 4500);
            }
        })();
        </script>

    </div><!-- .tc-stg-wrap -->
    <?php
}

// ─────────────────────────────────────────────
// Field-Callbacks (für WP-Kompatibilität, nicht
// direkt in der neuen Seite aufgerufen)
// ─────────────────────────────────────────────
function tc_field_primary_color() {
    $color = tc_get_primary_color();
    echo '<input type="color" name="tc_settings[primary_color]" value="' . esc_attr( $color ) . '">';
}
function tc_field_calendar_mode() {
    $mode = tc_get_setting( 'calendar_mode', 'light' );
    echo '<input type="checkbox" name="tc_settings[calendar_mode]" value="dark"' . checked( $mode, 'dark', false ) . '>';
}
function tc_field_week_only() {
    $v = tc_get_setting( 'frontend_week_only', '0' );
    echo '<input type="checkbox" name="tc_settings[frontend_week_only]" value="1"' . checked( $v, '1', false ) . '>';
}
function tc_field_show_event_list() {
    $v = tc_get_setting( 'show_event_list', '0' );
    echo '<input type="checkbox" name="tc_settings[show_event_list]" value="1"' . checked( $v, '1', false ) . '>';
}
function tc_field_event_list_title() {
    $v = tc_get_setting( 'event_list_title', 'Unsere Events' );
    echo '<input type="text" name="tc_settings[event_list_title]" value="' . esc_attr( $v ) . '" class="regular-text">';
}
function tc_field_registration_email() {
    $email = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );
    echo '<input type="email" name="tc_settings[registration_email]" value="' . esc_attr( $email ) . '" class="regular-text">';
}
function tc_field_reminder_enabled() {
    $v = tc_get_setting( 'reminder_enabled', '0' );
    echo '<input type="checkbox" name="tc_settings[reminder_enabled]" value="1"' . checked( $v, '1', false ) . '>';
}

// ─────────────────────────────────────────────
// Settings-CSS laden
// ─────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'time_event_page_time-calendar-settings' ) return;
    wp_enqueue_style(
        'tc-settings',
        TC_URL . 'assets/css/admin/settings.css',
        array(),
        TC_VERSION
    );
} );
