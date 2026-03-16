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
            'calendar_mode'      => 'light',
            'registration_email' => get_option( 'admin_email' ),
            'reminder_enabled'   => '0',
            'primary_color'      => '#4f46e5',
            'frontend_week_only' => '0',
            'show_event_list'    => '0',
            'event_list_title'   => 'Unsere Events',
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

    $clean['event_list_title'] = isset( $input['event_list_title'] )
        ? sanitize_text_field( $input['event_list_title'] )
        : 'Unsere Events';
    if ( empty( $clean['event_list_title'] ) ) {
        $clean['event_list_title'] = 'Unsere Events';
    }

    $color = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '';
    $clean['primary_color'] = $color ?: '#4f46e5';

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
// Settings-Seite rendern
// ─────────────────────────────────────────────
function tc_render_settings_page() {
    $updated = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true';

    // Aktuelle Werte
    $primary_color    = tc_get_primary_color();
    $calendar_mode    = tc_get_setting( 'calendar_mode', 'light' );
    $week_only        = tc_get_setting( 'frontend_week_only', '0' );
    $show_event_list  = tc_get_setting( 'show_event_list', '0' );
    $event_list_title = tc_get_setting( 'event_list_title', 'Unsere Events' ) ?: 'Unsere Events';
    $reg_email        = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );
    $reminder         = tc_get_setting( 'reminder_enabled', '0' );
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
            <button class="tc-stg-tab" data-tab="email"    role="tab">📧 E-Mails & Anmeldungen</button>
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
                <div class="tc-stg-actions">
                    <button type="submit" class="tc-stg-save">Einstellungen speichern</button>
                </div>
            </div><!-- .tc-stg-pane[design] -->

            <!-- ═══ Tab: Kalender ═════════════════════════════ -->
            <div class="tc-stg-pane" data-tab="kalender">
                <div class="tc-stg-card">

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Nur aktuelle Woche anzeigen</strong>
                            <span>Blendet Navigation und Ansichtswechsel aus. Der Nutzer sieht immer nur die aktuelle Woche.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-toggle">
                                <input
                                    type="checkbox"
                                    name="tc_settings[frontend_week_only]"
                                    value="1"
                                    <?php checked( $week_only, '1' ); ?>
                                >
                                <span class="tc-toggle-track"></span>
                            </label>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Event-Übersicht unter Kalender anzeigen</strong>
                            <span>Zeigt alle Events als klickbare Karten unterhalb des Kalenders. Reagiert auf den aktiven Kategorie-Filter.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <label class="tc-toggle">
                                <input
                                    type="checkbox"
                                    name="tc_settings[show_event_list]"
                                    value="1"
                                    <?php checked( $show_event_list, '1' ); ?>
                                >
                                <span class="tc-toggle-track"></span>
                            </label>
                        </div>
                    </div>

                    <div class="tc-stg-divider"></div>

                    <div class="tc-stg-row">
                        <div class="tc-stg-row-left">
                            <strong>Überschrift der Event-Liste</strong>
                            <span>Abschnittsüberschrift über der Event-Kartenansicht im Frontend.</span>
                        </div>
                        <div class="tc-stg-row-right">
                            <input
                                type="text"
                                name="tc_settings[event_list_title]"
                                value="<?php echo esc_attr( $event_list_title ); ?>"
                                class="tc-stg-input"
                                placeholder="Unsere Events"
                            >
                        </div>
                    </div>

                </div><!-- .tc-stg-card -->
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
            // ── Color picker ───────────────────────────────────
            var colorInput = document.getElementById('tc-primary-color-input');
            var colorLabel = document.getElementById('tc-primary-color-label');
            if (colorInput && colorLabel) {
                colorInput.addEventListener('input', function () {
                    colorLabel.textContent = colorInput.value;
                });
            }

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
            var validTabs = ['design', 'kalender', 'email', 'updates'];

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
