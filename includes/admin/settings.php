<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Settings-Seite registrieren
// ─────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=training_event',
        'Einstellungen',
        'Einstellungen',
        'administrator',
        'training-calendar-settings',
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
            'calendar_mode'      => 'light',
            'registration_email' => get_option( 'admin_email' ),
            'reminder_enabled'   => '0',
            'primary_color'      => '#4f46e5',
            'github_token'       => '',
        ),
    ) );

    // ── Sektion: Frontend Kalender ──────────
    add_settings_section(
        'tc_section_frontend',
        'Frontend Kalender',
        function () {
            echo '<p class="tc-settings-desc">Einstellungen für die Kalenderansicht im Frontend.</p>';
        },
        'training-calendar-settings'
    );

    add_settings_field(
        'tc_calendar_mode',
        'Farbmodus',
        'tc_field_calendar_mode',
        'training-calendar-settings',
        'tc_section_frontend'
    );

    // ── Sektion: Design ─────────────────────
    add_settings_section(
        'tc_section_design',
        'Design',
        function () {
            echo '<p class="tc-settings-desc">Farbgebung aller Plugin-Elemente im Frontend.</p>';
        },
        'training-calendar-settings'
    );

    add_settings_field(
        'tc_primary_color',
        'Primärfarbe (Keycolor)',
        'tc_field_primary_color',
        'training-calendar-settings',
        'tc_section_design'
    );

    // ── Sektion: Anmeldeformular ────────────
    add_settings_section(
        'tc_section_registration',
        'Anmeldeformular',
        function () {
            echo '<p class="tc-settings-desc">Einstellungen für das Anmeldeformular und Bestätigungsmails.</p>';
        },
        'training-calendar-settings'
    );

    add_settings_field(
        'tc_registration_email',
        'Bestätigungs-E-Mail von',
        'tc_field_registration_email',
        'training-calendar-settings',
        'tc_section_registration'
    );

    add_settings_field(
        'tc_reminder_enabled',
        'Erinnerungsmail (3 Tage vorher)',
        'tc_field_reminder_enabled',
        'training-calendar-settings',
        'tc_section_registration'
    );

    // ── Sektion: Update ─────────────────────
    add_settings_section(
        'tc_section_update',
        'Update',
        function () {
            echo '<p class="tc-settings-desc">Automatische Updates via GitHub Releases.</p>';
        },
        'training-calendar-settings'
    );

    add_settings_field(
        'tc_plugin_version',
        'Plugin-Version',
        'tc_field_plugin_version',
        'training-calendar-settings',
        'tc_section_update'
    );

    add_settings_field(
        'tc_github_token',
        'GitHub Access Token',
        'tc_field_github_token',
        'training-calendar-settings',
        'tc_section_update'
    );
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

    $clean['reminder_enabled'] = ! empty( $input['reminder_enabled'] ) ? '1' : '0';

    $color = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '';
    $clean['primary_color'] = $color ?: '#4f46e5';

    $clean['github_token'] = isset( $input['github_token'] )
        ? sanitize_text_field( $input['github_token'] )
        : '';

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
    $primary      = tc_get_primary_color();
    $primary_dark = tc_primary_darken( 15 );
    $primary_light      = tc_primary_rgba( 0.15 );
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
// Feld: Primärfarbe
// ─────────────────────────────────────────────
function tc_field_primary_color() {
    $color = tc_get_primary_color();
    ?>
    <div style="display:flex;align-items:center;gap:10px;">
        <input
            type="color"
            id="tc-primary-color-input"
            name="tc_settings[primary_color]"
            value="<?php echo esc_attr( $color ); ?>"
            style="width:48px;height:36px;padding:2px 4px;border:1px solid #ddd;border-radius:4px;cursor:pointer;"
        />
        <code id="tc-primary-color-label" style="font-size:14px;"><?php echo esc_html( $color ); ?></code>
    </div>
    <p class="description" style="margin-top:6px;">
        Primärfarbe für alle Plugin-Elemente (Filter-Buttons, CTA-Buttons, Fokus-Ringe&nbsp;etc.).<br>
        Standard: <code>#4f46e5</code> (Indigo)
    </p>
    <script>
    (function () {
        var input = document.getElementById('tc-primary-color-input');
        var label = document.getElementById('tc-primary-color-label');
        if (!input || !label) return;
        input.addEventListener('input', function () {
            label.textContent = input.value;
        });
    })();
    </script>
    <?php
}

// ─────────────────────────────────────────────
// Feld: Registrierungs-E-Mail
// ─────────────────────────────────────────────
function tc_field_registration_email() {
    $email = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );
    ?>
    <input
        type="email"
        name="tc_settings[registration_email]"
        value="<?php echo esc_attr( $email ); ?>"
        class="regular-text"
        required
    />
    <p class="description">
        Diese E-Mail-Adresse <strong>empfängt</strong> die Anmeldebestätigungen und Benachrichtigungen von neuen Anmeldungen.
        <br>
        <strong>Hinweis:</strong> Die tatsächliche Absenderadresse wird durch Fluent SMTP Plugin bestimmt (falls installiert/konfiguriert).
    </p>
    <?php
}

// ─────────────────────────────────────────────
// Feld: Light / Dark Mode Switch
// ─────────────────────────────────────────────
function tc_field_calendar_mode() {
    $mode = tc_get_setting( 'calendar_mode', 'light' );
    ?>
    <div class="tc-settings-toggle-wrap">
        <label class="tc-settings-toggle">
            <input
                type="checkbox"
                name="tc_settings[calendar_mode]"
                value="dark"
                <?php checked( $mode, 'dark' ); ?>
            />
            <span class="tc-settings-toggle-slider"></span>
        </label>
        <div class="tc-settings-toggle-labels">
            <span class="tc-settings-toggle-label" id="tc-mode-label">
                <?php echo $mode === 'dark' ? 'Dark Mode' : 'Light Mode'; ?>
            </span>
            <span class="tc-settings-toggle-hint">
                Gilt für alle <code>[training_calendar]</code> Shortcodes im Frontend.
            </span>
        </div>
    </div>
    <script>
    (function() {
        const cb    = document.querySelector('input[name="tc_settings[calendar_mode]"]');
        const label = document.getElementById('tc-mode-label');
        if (!cb || !label) return;
        cb.addEventListener('change', () => {
            label.textContent = cb.checked ? 'Dark Mode' : 'Light Mode';
        });
    })();
    </script>
    <?php
}

// ─────────────────────────────────────────────
// Settings-Seite rendern
// ─────────────────────────────────────────────
function tc_render_settings_page() { ?>
    <div class="wrap tc-settings-wrap">
        <h1>
            <span class="dashicons dashicons-calendar-alt"></span>
            Training Calendar — Einstellungen
        </h1>

        <?php settings_errors( 'tc_settings_group' ); ?>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'tc_settings_group' );
            do_settings_sections( 'training-calendar-settings' );
            submit_button( 'Einstellungen speichern' );
            ?>
        </form>
    </div>
<?php }

// ─────────────────────────────────────────────
// Feld: Erinnerungsmail-Toggle
// ─────────────────────────────────────────────
function tc_field_reminder_enabled() {
    $enabled = tc_get_setting( 'reminder_enabled', '0' );
    ?>
    <div class="tc-settings-toggle-wrap">
        <label class="tc-settings-toggle">
            <input
                type="checkbox"
                name="tc_settings[reminder_enabled]"
                value="1"
                <?php checked( $enabled, '1' ); ?>
            />
            <span class="tc-settings-toggle-slider"></span>
        </label>
        <div class="tc-settings-toggle-labels">
            <span class="tc-settings-toggle-hint">
                Sendet automatisch 3 Tage vor jedem Event eine Erinnerungsmail an alle Teilnehmer mit Status <em>Bestätigt</em>.
                Der Cron-Job läuft täglich. Bereits versendete Erinnerungen werden nicht erneut gesendet.
            </span>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────
// Action: Update-Cache leeren ("Jetzt prüfen")
// ─────────────────────────────────────────────
add_action( 'admin_init', function () {
    if ( empty( $_GET['tc_check_update'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'tc_check_update' ) ) return;

    delete_transient( TC_Plugin_Updater::TRANSIENT_KEY );

    // WordPress-eigenen Update-Transient ebenfalls löschen damit
    // check_for_update sofort beim nächsten Seitenaufruf greift.
    delete_site_transient( 'update_plugins' );

    wp_safe_redirect( remove_query_arg( array( 'tc_check_update', '_wpnonce' ) ) );
    exit;
} );

// ─────────────────────────────────────────────
// Feld: Plugin-Version + Update-Status
// ─────────────────────────────────────────────
function tc_field_plugin_version() {
    $last_checked = (int) get_option( TC_Plugin_Updater::LAST_CHECK_KEY, 0 );
    $check_url    = wp_nonce_url(
        add_query_arg( 'tc_check_update', '1' ),
        'tc_check_update'
    );

    if ( $last_checked > 0 ) {
        $diff    = time() - $last_checked;
        $minutes = (int) round( $diff / 60 );
        if ( $minutes < 60 ) {
            $since = $minutes <= 1 ? 'vor 1 Minute' : "vor {$minutes} Minuten";
        } elseif ( $minutes < 1440 ) {
            $hours = (int) round( $minutes / 60 );
            $since = "vor {$hours} Stunde" . ( $hours !== 1 ? 'n' : '' );
        } else {
            $days  = (int) round( $minutes / 1440 );
            $since = "vor {$days} Tag" . ( $days !== 1 ? 'en' : '' );
        }
        $last_check_text = esc_html( $since );
    } else {
        $last_check_text = 'noch nie';
    }
    ?>
    <p style="margin:0 0 8px;">
        <strong>Installierte Version:</strong>
        <code><?php echo esc_html( TC_VERSION ); ?></code>
    </p>
    <p style="margin:0 0 10px;color:#666;font-size:13px;">
        Letzter Update-Check: <?php echo $last_check_text; ?>
    </p>
    <a href="<?php echo esc_url( $check_url ); ?>" class="button button-secondary">
        ↻ Jetzt auf Updates prüfen
    </a>
    <?php
}

// ─────────────────────────────────────────────
// Feld: GitHub Access Token
// ─────────────────────────────────────────────
function tc_field_github_token() {
    $token = tc_get_setting( 'github_token', '' );
    ?>
    <input
        type="password"
        name="tc_settings[github_token]"
        value="<?php echo esc_attr( $token ); ?>"
        class="regular-text"
        autocomplete="new-password"
        placeholder="ghp_xxxxxxxxxxxxxxxxxxxx"
    />
    <p class="description">
        Nur für <strong>private</strong> GitHub-Repositories erforderlich.<br>
        Benötigte Berechtigung: <code>repo</code> (read) oder <code>contents: read</code> (fine-grained).<br>
        Für öffentliche Repos dieses Feld leer lassen.
    </p>
    <?php
}

// ─────────────────────────────────────────────
// Settings-CSS laden
// ─────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'training_event_page_training-calendar-settings' ) return;
    wp_enqueue_style(
        'tc-settings',
        TC_URL . 'assets/css/admin/settings.css',
        array(),
        TC_VERSION
    );
} );
