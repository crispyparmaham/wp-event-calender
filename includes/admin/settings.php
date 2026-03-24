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
            // Texte & Labels
            'anrede_mode'                    => 'sie',
            'label_form_title'               => 'Anmelden',
            'label_form_title_trial'         => 'Kostenloses Probetraining anfragen',
            'label_submit_btn'               => 'Anmeldung absenden',
            'label_submit_btn_trial'         => 'Probetraining anfragen',
            'label_waitlist_btn'             => 'Auf Warteliste eintragen',
            'label_request_btn'              => 'Jetzt anfragen',
            'label_request_notice'           => 'Für weitere Informationen oder eine Buchungsanfrage kontaktieren Sie uns gerne direkt.',
            'label_full_notice'              => 'Diese Veranstaltung ist leider ausgebucht.',
            'label_full_subtext'             => 'Tragen Sie sich auf die Warteliste ein – wir benachrichtigen Sie, sobald ein Platz frei wird.',
            'label_success_msg'              => 'Vielen Dank! Ihre Anmeldung wurde erfolgreich gespeichert.',
            'label_success_msg_trial'        => 'Vielen Dank! Deine Anfrage für ein Probetraining wurde erfolgreich übermittelt. Wir melden uns zeitnah bei dir.',
            'label_duplicate_msg'            => 'Sie sind bereits für diese Veranstaltung angemeldet.',
            'label_duplicate_msg_trial'      => 'Du hast bereits eine Probetraining-Anfrage für diese Veranstaltung gestellt.',
            'label_price_bar_full'           => 'Ausgebucht',
            'label_price_bar_full_sub'       => 'Leider keine Plätze mehr verfügbar.',
            'label_price_bar_free'           => 'Kostenlos',
            'label_price_bar_request_headline' => 'Neugierig geworden?',
            'label_price_bar_request_teaser'   => 'Dann melde dich jetzt für ein kostenloses Probetraining an.',
            'label_price_bar_cta_full'       => 'Ausgebucht',
            'label_price_bar_cta_request'    => 'Probetraining anfragen',
            // Mail-Templates
            'mail_thankyou_subject'          => 'Vielen Dank für {{anrede_possessiv}} Anmeldung – {{event_title}}',
            'mail_thankyou_preview'          => 'Wir haben {{anrede_possessiv}} Anmeldung erhalten und melden uns zeitnah.',
            'mail_thankyou_body'             => '',
            'mail_confirm_subject'           => '{{anrede_possessiv}} Anmeldung ist bestätigt – {{event_title}}',
            'mail_confirm_preview'           => 'Wir freuen uns auf {{anrede_akkusativ}}!',
            'mail_confirm_body'              => '',
            'mail_cancel_subject'            => '{{anrede_possessiv}} Anmeldung konnte leider nicht bestätigt werden – {{event_title}}',
            'mail_cancel_preview'            => '',
            'mail_cancel_body'               => '',
            'mail_waitlist_subject'          => '{{anrede}} stehen auf der Warteliste – {{event_title}}',
            'mail_waitlist_preview'          => 'Wir benachrichtigen {{anrede_akkusativ}}, sobald ein Platz frei wird.',
            'mail_waitlist_body'             => '',
            'mail_waitlist_slot_subject'     => 'Ein Platz ist frei geworden – {{event_title}}',
            'mail_waitlist_slot_preview'     => '',
            'mail_waitlist_slot_body'        => '',
            'mail_reminder_subject'          => 'Erinnerung: {{event_title}} – in 3 Tagen',
            'mail_reminder_preview'          => '',
            'mail_reminder_body'             => '',
            'mail_admin_subject'             => 'Neue Anmeldung: {{event_title}} – {{firstname}} {{lastname}}',
            'mail_admin_preview'             => '',
            'mail_admin_body'               => '',
        ),
    ) );
} );

// ─────────────────────────────────────────────
// Helper: Custom CSS Sanitize (zeilenweiser Parser)
// ─────────────────────────────────────────────
function tc_sanitize_custom_css( string $input ): string {
    if ( empty( $input ) ) return '';

    $lines    = explode( "\n", $input );
    $allowed  = array();
    $in_block = false;
    $block    = '';

    foreach ( $lines as $line ) {
        $trimmed = trim( $line );

        // Einzeiliger Kommentar /* ... */
        if ( preg_match( '/^\/\*[^*]*\*+(?:[^\/][^*]*\*+)*\/$/', $trimmed ) ) {
            $allowed[] = $line;
            continue;
        }

        // .tc-dark { Block öffnen
        if ( preg_match( '/^\.tc-dark\s*\{/', $trimmed ) ) {
            $in_block = true;
            $block    = $line . "\n";
            continue;
        }

        // Block schließen
        if ( $in_block && $trimmed === '}' ) {
            $block    .= $line;
            $allowed[] = $block;
            $in_block  = false;
            $block     = '';
            continue;
        }

        // Zeile innerhalb .tc-dark Block
        if ( $in_block ) {
            if ( preg_match( '/^\s*--tc-[a-z][a-z0-9\-]*\s*:\s*[^;{}]+;/', $line ) ) {
                $block .= $line . "\n";
            }
            continue;
        }

        // Einzelne --tc-* Deklaration außerhalb eines Blocks
        if ( preg_match( '/^--tc-[a-z][a-z0-9\-]*\s*:\s*[^;{}]+;$/', $trimmed ) ) {
            $allowed[] = $line;
        }
    }

    return implode( "\n", $allowed );
}

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

    // Custom CSS: zeilenweiser Parser — erlaubt --tc-* Deklarationen,
    // .tc-dark { } Blöcke und einzeilige /* Kommentare */
    $raw_css  = isset( $input['token_custom_css'] ) ? wp_unslash( $input['token_custom_css'] ) : '';
    $clean['token_custom_css'] = tc_sanitize_custom_css( $raw_css );

    // SEO
    $clean['schema_enabled'] = ! empty( $input['schema_enabled'] ) ? '1' : '0';

    // ── Anrede-Modus ──────────────────────────────────────────
    $clean['anrede_mode'] = ( isset( $input['anrede_mode'] ) && $input['anrede_mode'] === 'du' ) ? 'du' : 'sie';

    // ── UI-Labels ─────────────────────────────────────────────
    $label_keys = array(
        'label_form_title', 'label_form_title_trial', 'label_submit_btn', 'label_submit_btn_trial',
        'label_waitlist_btn', 'label_request_btn', 'label_request_notice',
        'label_full_notice', 'label_full_subtext',
        'label_success_msg', 'label_success_msg_trial',
        'label_duplicate_msg', 'label_duplicate_msg_trial',
        'label_price_bar_full', 'label_price_bar_full_sub', 'label_price_bar_free',
        'label_price_bar_request_headline', 'label_price_bar_request_teaser', 'label_price_bar_cta_full', 'label_price_bar_cta_request',
    );
    foreach ( $label_keys as $k ) {
        $clean[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( wp_unslash( $input[ $k ] ) ) : '';
    }

    // ── Mail-Templates ────────────────────────────────────────
    $mail_ids = array( 'thankyou', 'confirm', 'cancel', 'waitlist', 'waitlist_slot', 'reminder', 'admin' );
    foreach ( $mail_ids as $id ) {
        $clean[ 'mail_' . $id . '_subject' ] = isset( $input[ 'mail_' . $id . '_subject' ] )
            ? sanitize_text_field( wp_unslash( $input[ 'mail_' . $id . '_subject' ] ) ) : '';
        $clean[ 'mail_' . $id . '_preview' ] = isset( $input[ 'mail_' . $id . '_preview' ] )
            ? sanitize_text_field( wp_unslash( $input[ 'mail_' . $id . '_preview' ] ) ) : '';
        $clean[ 'mail_' . $id . '_body' ] = isset( $input[ 'mail_' . $id . '_body' ] )
            ? wp_kses_post( wp_unslash( $input[ 'mail_' . $id . '_body' ] ) ) : '';
    }

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
            <button class="tc-stg-tab" data-tab="texte"    role="tab">✏️ Texte &amp; Labels</button>
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

            <!-- ═══ Tab: Texte & Labels ═══════════════════════ -->
            <div class="tc-stg-pane" data-tab="texte">

                <!-- ── Card: Allgemein ───────────────────────────── -->
                <div class="tc-stg-card">
                    <h3 class="tc-stg-section-title">Allgemein</h3>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Anrede (Du / Sie)</strong>
                            <span>Steuert alle automatischen Platzhalter: <code>{{anrede}}</code>, <code>{{anrede_possessiv}}</code>, <code>{{anrede_dativ}}</code> usw.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <?php $anrede_mode = tc_get_setting( 'anrede_mode', 'sie' ); ?>
                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[anrede_mode]" value="sie" <?php checked( $anrede_mode, 'sie' ); ?>>
                                <strong>Sie</strong>
                                <span class="tc-stg-hint">&nbsp;— Ihre, Ihnen …</span>
                            </label>
                            <label class="tc-stg-radio-label">
                                <input type="radio" name="tc_settings[anrede_mode]" value="du" <?php checked( $anrede_mode, 'du' ); ?>>
                                <strong>Du</strong>
                                <span class="tc-stg-hint">&nbsp;— deine, dir, dich …</span>
                            </label>
                            <p class="tc-stg-hint">Standard: <strong>Sie</strong></p>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Formulartitel (Standard)</strong>
                            <span>Überschrift des Anmeldeformulars.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <input type="text" name="tc_settings[label_form_title]" class="tc-stg-input"
                                value="<?php echo esc_attr( tc_get_setting( 'label_form_title', 'Anmelden' ) ); ?>">
                            <p class="tc-stg-hint">Standard: Anmelden</p>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Formulartitel (Probetraining)</strong>
                            <span>Bei <code>event_price_type = request</code>.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <input type="text" name="tc_settings[label_form_title_trial]" class="tc-stg-input"
                                value="<?php echo esc_attr( tc_get_setting( 'label_form_title_trial', 'Kostenloses Probetraining anfragen' ) ); ?>">
                            <p class="tc-stg-hint">Standard: Kostenloses Probetraining anfragen</p>
                        </div>
                    </div>
                </div><!-- .tc-stg-card -->

                <!-- ── Card: Buttons & Status-Texte ─────────────── -->
                <div class="tc-stg-card">
                    <h3 class="tc-stg-section-title">Buttons &amp; Status-Texte</h3>
                    <?php
                    $tc_text_row = function( $label, $hint, $key, $default ) {
                        $val = tc_get_setting( $key, $default ); ?>
                        <div class="tc-stg-row">
                            <div class="tc-stg-row-left">
                                <strong><?php echo esc_html( $label ); ?></strong>
                                <?php if ( $hint ) echo '<span>' . esc_html( $hint ) . '</span>'; ?>
                            </div>
                            <div class="tc-stg-row-right">
                                <input type="text" name="tc_settings[<?php echo esc_attr( $key ); ?>]"
                                    class="tc-stg-input" value="<?php echo esc_attr( $val ); ?>">
                                <p class="tc-stg-hint">Standard: <?php echo esc_html( $default ); ?></p>
                            </div>
                        </div>
                        <div class="tc-stg-divider"></div>
                    <?php };

                    $tc_text_row( 'Absenden-Button', '', 'label_submit_btn', 'Anmeldung absenden' );
                    $tc_text_row( 'Absenden-Button (Probetraining)', 'Bei event_price_type = request.', 'label_submit_btn_trial', 'Probetraining anfragen' );
                    $tc_text_row( 'Wartelisten-Button', '', 'label_waitlist_btn', 'Auf Warteliste eintragen' );
                    $tc_text_row( 'Anfragen-Button (Modus „Auf Anfrage")', 'Wenn registration_mode = request.', 'label_request_btn', 'Jetzt anfragen' );
                    $tc_text_row( 'Hinweistext (Modus „Auf Anfrage")', '', 'label_request_notice', 'Für weitere Informationen oder eine Buchungsanfrage kontaktieren Sie uns gerne direkt.' );
                    $tc_text_row( 'Ausgebucht – Hauptzeile', '', 'label_full_notice', 'Diese Veranstaltung ist leider ausgebucht.' );
                    $tc_text_row( 'Ausgebucht – Untertext', '', 'label_full_subtext', 'Tragen Sie sich auf die Warteliste ein – wir benachrichtigen Sie, sobald ein Platz frei wird.' );
                    $tc_text_row( 'Erfolgsmeldung', 'Nach dem Absenden des Formulars.', 'label_success_msg', 'Vielen Dank! Ihre Anmeldung wurde erfolgreich gespeichert.' );
                    $tc_text_row( 'Erfolgsmeldung (Probetraining)', '', 'label_success_msg_trial', 'Vielen Dank! Deine Anfrage für ein Probetraining wurde erfolgreich übermittelt. Wir melden uns zeitnah bei dir.' );
                    $tc_text_row( 'Duplikat-Hinweis', 'E-Mail-Adresse bereits angemeldet.', 'label_duplicate_msg', 'Sie sind bereits für diese Veranstaltung angemeldet.' );
                    $tc_text_row( 'Duplikat-Hinweis (Probetraining)', '', 'label_duplicate_msg_trial', 'Du hast bereits eine Probetraining-Anfrage für diese Veranstaltung gestellt.' );
                    ?>

                    <p class="tc-stg-sub-label">Preisleiste (Price Bar)</p>
                    <div class="tc-stg-divider"></div>

                    <?php
                    $tc_text_row( 'Ausgebucht – Label', '', 'label_price_bar_full', 'Ausgebucht' );
                    $tc_text_row( 'Ausgebucht – Untertext', '', 'label_price_bar_full_sub', 'Leider keine Plätze mehr verfügbar.' );
                    $tc_text_row( 'Kostenlos – Text', 'Bei event_price_type = free.', 'label_price_bar_free', 'Kostenlos' );
                    $tc_text_row( 'Probetraining – Headline', 'Fett, in Primärfarbe. Z. B. „Neugierig geworden?"', 'label_price_bar_request_headline', 'Neugierig geworden?' );
                    $tc_text_row( 'Probetraining – Teaser-Text', 'Erscheint nach der Headline.', 'label_price_bar_request_teaser', 'Dann melde dich jetzt für ein kostenloses Probetraining an.' );
                    $tc_text_row( 'CTA-Button – Ausgebucht', '', 'label_price_bar_cta_full', 'Ausgebucht' );
                    $tc_text_row( 'CTA-Button – Probetraining', '', 'label_price_bar_cta_request', 'Probetraining anfragen' );
                    ?>
                </div><!-- .tc-stg-card -->

                <!-- ── Card: Platzhalter-Referenz ────────────────── -->
                <div class="tc-stg-card">
                    <h3 class="tc-stg-section-title">Verfügbare Platzhalter</h3>
                    <p class="tc-stg-card-desc">Können in Betreff, Vorschautext und Body der Mail-Templates verwendet werden. Werden beim Versand automatisch ersetzt.</p>
                    <div style="padding:0 28px 20px;">
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb;">
                                    <th style="padding:8px 14px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Platzhalter</th>
                                    <th style="padding:8px 14px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Beschreibung</th>
                                    <th style="padding:8px 14px;text-align:left;font-weight:700;color:#374151;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Beispiel (Sie)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ph_rows = array(
                                    array( '{{firstname}}',        'Vorname des Teilnehmers',             'Maria' ),
                                    array( '{{lastname}}',         'Nachname des Teilnehmers',            'Mustermann' ),
                                    array( '{{event_title}}',      'Veranstaltungstitel',                 'Yoga Grundkurs' ),
                                    array( '{{event_date}}',       'Datum &amp; Uhrzeit',                 '10.04.2025 um 10:00 Uhr' ),
                                    array( '{{event_location}}',   'Veranstaltungsort',                   'Studio 1, Hauptstr. 5' ),
                                    array( '{{storno_url}}',       'Stornierungslink (vollständige URL)', 'https://…/?tc_cancel=…' ),
                                    array( '{{blogname}}',         'Website-Name',                        esc_html( get_option('blogname') ) ),
                                    array( '{{anrede}}',           'Anrede',                              'Sie' ),
                                    array( '{{anrede_possessiv}}', 'Possessiv',                           'Ihre' ),
                                    array( '{{anrede_akkusativ}}', 'Akkusativ',                           'Sie' ),
                                    array( '{{anrede_dativ}}',     'Dativ',                               'Ihnen' ),
                                    array( '{{anrede_imperativ}}', 'Imperativ',                           'Bitte melden Sie sich' ),
                                );
                                foreach ( $ph_rows as $i => $row ) :
                                    $bg = $i % 2 !== 0 ? 'background:#f9fafb;' : '';
                                ?>
                                <tr style="<?php echo $bg; ?>border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:8px 14px;"><code style="background:#eef2ff;color:#4f46e5;padding:2px 8px;border-radius:4px;font-size:12px;"><?php echo $row[0]; ?></code></td>
                                    <td style="padding:8px 14px;color:#374151;"><?php echo $row[1]; ?></td>
                                    <td style="padding:8px 14px;color:#9ca3af;font-style:italic;"><?php echo $row[2]; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div><!-- .tc-stg-card -->

                <!-- ── Cards: E-Mail-Templates (7×) ─────────────── -->
                <?php
                $mail_meta = array(
                    'thankyou'      => array(
                        'label' => 'Dankes-Mail',
                        'hint'  => 'Geht direkt nach der Anmeldung an den Teilnehmer.',
                        'body'  => '<h2 style="color:#0066cc;margin-top:0;">Vielen Dank für {{anrede_possessiv}} Anmeldung!</h2>' . "\n"
                                 . '<p>Hallo {{firstname}} {{lastname}},</p>' . "\n"
                                 . '<p>wir haben {{anrede_possessiv}} Anmeldung erhalten und melden uns zeitnah mit einer Bestätigung bei {{anrede_dativ}}.</p>' . "\n"
                                 . '<p><strong>{{event_title}}</strong><br>Datum: {{event_date}}<br>Ort: {{event_location}}</p>' . "\n"
                                 . '<p>Bei Fragen stehen wir {{anrede_dativ}} gerne zur Verfügung.</p>' . "\n"
                                 . '<p style="font-size:13px;color:#6b7280;">Anmeldung stornieren: <a href="{{storno_url}}">{{storno_url}}</a></p>',
                    ),
                    'confirm'       => array(
                        'label' => 'Bestätigungs-Mail',
                        'hint'  => 'Wird nach der Admin-Freigabe einer Anmeldung versendet.',
                        'body'  => '<h2 style="color:#059669;margin-top:0;">{{anrede_possessiv}} Anmeldung ist bestätigt ✓</h2>' . "\n"
                                 . '<p>Hallo {{firstname}} {{lastname}},</p>' . "\n"
                                 . '<p>wir freuen uns, {{anrede_possessiv}} Anmeldung hiermit offiziell zu bestätigen. Wir sehen uns beim Termin!</p>' . "\n"
                                 . '<p><strong>{{event_title}}</strong><br>Datum: {{event_date}}<br>Ort: {{event_location}}</p>' . "\n"
                                 . '<p>Bei Fragen stehen wir {{anrede_dativ}} gerne zur Verfügung.</p>' . "\n"
                                 . '<p style="font-size:13px;color:#6b7280;">Anmeldung stornieren: <a href="{{storno_url}}">{{storno_url}}</a></p>',
                    ),
                    'cancel'        => array(
                        'label' => 'Absage-Mail',
                        'hint'  => 'Wird nach der Admin-Stornierung einer Anmeldung versendet.',
                        'body'  => '<h2 style="color:#dc2626;margin-top:0;">{{anrede_possessiv}} Anmeldung konnte nicht bestätigt werden</h2>' . "\n"
                                 . '<p>Hallo {{firstname}} {{lastname}},</p>' . "\n"
                                 . '<p>leider müssen wir {{anrede_dativ}} mitteilen, dass {{anrede_possessiv}} Anmeldung nicht bestätigt werden konnte.</p>' . "\n"
                                 . '<p><strong>{{event_title}}</strong><br>Datum: {{event_date}}<br>Ort: {{event_location}}</p>' . "\n"
                                 . '<p>Melden {{anrede}} sich gerne bei uns, wenn {{anrede}} einen alternativen Termin buchen möchten.</p>',
                    ),
                    'waitlist'      => array(
                        'label' => 'Warteliste – Eintragsbestätigung',
                        'hint'  => 'Wenn ein Teilnehmer auf die Warteliste gesetzt wird.',
                        'body'  => '<h2 style="color:#d97706;margin-top:0;">{{anrede}} stehen auf der Warteliste</h2>' . "\n"
                                 . '<p>Hallo {{firstname}} {{lastname}},</p>' . "\n"
                                 . '<p>vielen Dank für {{anrede_possessiv}} Interesse! {{anrede}} wurden auf die Warteliste für folgende Veranstaltung eingetragen:</p>' . "\n"
                                 . '<p><strong>{{event_title}}</strong><br>Datum: {{event_date}}<br>Ort: {{event_location}}</p>' . "\n"
                                 . '<p>Wir benachrichtigen {{anrede_akkusativ}} umgehend, sobald ein Platz frei wird.</p>',
                    ),
                    'waitlist_slot' => array(
                        'label' => 'Warteliste – Platz frei',
                        'hint'  => 'Wenn eine Stornierung einen Wartelistenplatz freigibt.',
                        'body'  => '<h2 style="color:#059669;margin-top:0;">Ein Platz ist frei geworden!</h2>' . "\n"
                                 . '<p>Hallo {{firstname}} {{lastname}},</p>' . "\n"
                                 . '<p>gute Neuigkeit! Für folgende Veranstaltung ist ein Platz frei geworden:</p>' . "\n"
                                 . '<p><strong>{{event_title}}</strong><br>Datum: {{event_date}}<br>Ort: {{event_location}}</p>' . "\n"
                                 . '<p>{{anrede_possessiv}} Anfrage wird nun bearbeitet. {{anrede}} erhalten zeitnah eine Bestätigung.</p>',
                    ),
                    'reminder'      => array(
                        'label' => 'Erinnerungs-Mail',
                        'hint'  => 'Wird 3 Tage vor dem Event automatisch versendet (wenn aktiviert).',
                        'body'  => '<h2 style="color:#0066cc;margin-top:0;">Erinnerung: {{event_title}}</h2>' . "\n"
                                 . '<p>Hallo {{firstname}} {{lastname}},</p>' . "\n"
                                 . '<p>wir möchten {{anrede_akkusativ}} daran erinnern, dass in <strong>3 Tagen</strong> folgender Termin stattfindet:</p>' . "\n"
                                 . '<p><strong>{{event_title}}</strong><br>Datum: {{event_date}}<br>Ort: {{event_location}}</p>' . "\n"
                                 . '<p>Wir freuen uns auf {{anrede_akkusativ}}!</p>',
                    ),
                    'admin'         => array(
                        'label' => 'Admin-Benachrichtigung',
                        'hint'  => 'Interne Benachrichtigung bei jeder neuen Anmeldung.',
                        'body'  => '<h2 style="color:#0066cc;margin-top:0;">Neue Anmeldung eingegangen</h2>' . "\n"
                                 . '<p><strong>Veranstaltung:</strong> {{event_title}}<br>'
                                 . '<strong>Datum:</strong> {{event_date}}<br>'
                                 . '<strong>Ort:</strong> {{event_location}}</p>' . "\n"
                                 . '<p><strong>Teilnehmer:</strong> {{firstname}} {{lastname}}</p>',
                    ),
                );
                foreach ( $mail_meta as $mid => $mmeta ) :
                    $s = tc_get_setting( 'mail_' . $mid . '_subject', '' );
                    $p = tc_get_setting( 'mail_' . $mid . '_preview', '' );
                    $b = tc_get_setting( 'mail_' . $mid . '_body',    '' );
                ?>
                <div class="tc-stg-card">
                    <h3 class="tc-stg-section-title"><?php echo esc_html( $mmeta['label'] ); ?></h3>
                    <p class="tc-stg-card-desc"><?php echo esc_html( $mmeta['hint'] ); ?></p>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Betreff</strong>
                            <span>Platzhalter wie <code>{{event_title}}</code> sind erlaubt.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <input type="text"
                                name="tc_settings[mail_<?php echo esc_attr( $mid ); ?>_subject]"
                                class="tc-stg-input" style="max-width:100%;"
                                placeholder="Standard-Betreff des Plugins"
                                value="<?php echo esc_attr( $s ); ?>">
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Vorschautext</strong>
                            <span>Preheader in E-Mail-Clients (optional).</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <input type="text"
                                name="tc_settings[mail_<?php echo esc_attr( $mid ); ?>_preview]"
                                class="tc-stg-input" style="max-width:100%;"
                                placeholder="(optional)"
                                value="<?php echo esc_attr( $p ); ?>">
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Body (HTML)</strong>
                            <span>Leer = eingebautes Standard-Template. HTML-Tags erlaubt.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <textarea
                                name="tc_settings[mail_<?php echo esc_attr( $mid ); ?>_body]"
                                class="tc-stg-input tc-stg-input--code"
                                style="width:100%;max-width:100%;min-height:150px;"
                                placeholder="Leer = eingebauter Standard"
                            ><?php echo esc_textarea( $b ); ?></textarea>
                            <details class="tc-stg-details">
                                <summary>Standard-Template anzeigen</summary>
                                <pre class="tc-stg-code-preview"><?php echo esc_html( $mmeta['body'] ); ?></pre>
                            </details>
                        </div>
                    </div>
                </div><!-- .tc-stg-card[<?php echo esc_attr( $mid ); ?>] -->
                <?php endforeach; ?>

                <div class="tc-stg-actions">
                    <button type="submit" class="tc-stg-save">Einstellungen speichern</button>
                </div>
            </div><!-- .tc-stg-pane[texte] -->

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
            var validTabs = ['design', 'kalender', 'email', 'seo', 'texte', 'updates'];

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
