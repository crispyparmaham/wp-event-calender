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
            'calendar_mode' => 'light',
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
} );

// ─────────────────────────────────────────────
// Sanitize Callback
// ─────────────────────────────────────────────
function tc_sanitize_settings( $input ) {
    $clean = array();
    $clean['calendar_mode'] = isset( $input['calendar_mode'] ) && $input['calendar_mode'] === 'dark'
        ? 'dark'
        : 'light';
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
// Settings-CSS laden
// ─────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'training_event_page_training-calendar-settings' ) return;
    wp_enqueue_style(
        'tc-settings',
        TC_URL . 'assets/css/settings.css',
        array(),
        TC_VERSION
    );
} );