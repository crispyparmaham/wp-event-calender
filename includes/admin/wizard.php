<?php
/**
 * Time Calendar – Setup Wizard
 *
 * Erscheint einmalig direkt nach der Plugin-Aktivierung.
 * Steuerung über wp_option 'tc_wizard_completed' (Wert '0' → zeigen, '1' → fertig).
 */
defined( 'ABSPATH' ) || exit;

// ── Assets nur im Admin laden ────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function () {
    if ( get_option( 'tc_wizard_completed', '1' ) !== '0' ) {
        return;
    }

    wp_enqueue_style(
        'tc-wizard',
        TC_URL . 'assets/css/admin/wizard.css',
        [],
        TC_VERSION
    );

    wp_enqueue_script(
        'tc-wizard',
        TC_URL . 'assets/js/admin/wizard.js',
        [],
        TC_VERSION,
        true // im footer
    );

    wp_localize_script( 'tc-wizard', 'tcWizard', array(
        'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
        'wizNonce' => wp_create_nonce( 'tc_wizard_nonce' ),
        'catNonce' => wp_create_nonce( 'tc_nonce' ),
    ) );
} );

// ── Modal-HTML am Ende von admin_footer ausgeben ─────────────────────
add_action( 'admin_footer', function () {
    if ( get_option( 'tc_wizard_completed', '1' ) !== '0' ) {
        return;
    }

    $primary = tc_get_setting( 'primary_color', '#4f46e5' );
    $mode    = tc_get_setting( 'calendar_mode', 'light' );
    $anrede  = tc_get_setting( 'anrede_mode',   'formal' );
    $reg_mail = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );

    $cal_url = admin_url( 'edit.php?post_type=time_event' );
    $stg_url = admin_url( 'edit.php?post_type=time_event&page=tc-settings' );
    ?>
    <div id="tc-wizard-backdrop">
        <div id="tc-wizard-modal" role="dialog" aria-modal="true" aria-labelledby="tc-wiz-title">

            <!-- ── Header ── -->
            <div class="tc-wiz-header">
                <h1 class="tc-wiz-title" id="tc-wiz-title">
                    <span class="tc-wiz-title-icon">🗓</span>
                    Willkommen bei Time Calendar
                </h1>
                <div class="tc-wiz-progress-wrap">
                    <div class="tc-wiz-progress-bar-bg">
                        <div class="tc-wiz-progress-bar-fill" id="tc-wiz-progress-fill"></div>
                    </div>
                    <div class="tc-wiz-step-dots">
                        <span class="tc-wiz-dot" data-step="1"></span>
                        <span class="tc-wiz-dot" data-step="2"></span>
                        <span class="tc-wiz-dot" data-step="3"></span>
                        <span class="tc-wiz-dot" data-step="4"></span>
                        <span class="tc-wiz-dot" data-step="5"></span>
                        <span class="tc-wiz-step-label" id="tc-wiz-step-label">Schritt 1 / 5</span>
                    </div>
                </div>
            </div>

            <!-- ── Body ── -->
            <div class="tc-wiz-body">

                <!-- Schritt 1: Design -->
                <div class="tc-wiz-step" data-step="1">
                    <h2>Design &amp; Darstellung</h2>
                    <p class="tc-wiz-desc">Wähle die Akzentfarbe und den Kalender-Modus passend zu deiner Website.</p>

                    <div class="tc-wiz-field">
                        <label>Primärfarbe</label>
                        <div class="tc-wiz-color-row">
                            <input type="color" id="tc-wiz-color-picker"
                                   value="<?php echo esc_attr( $primary ); ?>">
                            <input type="text"  id="tc-wiz-color"
                                   value="<?php echo esc_attr( $primary ); ?>"
                                   maxlength="7" placeholder="#4f46e5" style="width:110px">
                            <span class="tc-wiz-color-preview" id="tc-wiz-color-preview"
                                  style="background:<?php echo esc_attr( $primary ); ?>">✦</span>
                        </div>
                    </div>

                    <div class="tc-wiz-field">
                        <label>Kalender-Modus</label>
                        <div class="tc-wiz-radio-group">
                            <label>
                                <input type="radio" name="tc_wiz_mode" value="light"
                                    <?php checked( $mode, 'light' ); ?>>
                                <span class="tc-wiz-radio-text">
                                    <strong>Hell</strong>
                                    Weißer Hintergrund, ideal für helle Themes
                                </span>
                            </label>
                            <label>
                                <input type="radio" name="tc_wiz_mode" value="dark"
                                    <?php checked( $mode, 'dark' ); ?>>
                                <span class="tc-wiz-radio-text">
                                    <strong>Dunkel</strong>
                                    Dunkler Hintergrund für Dark-Mode-Designs
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Schritt 2: Anmeldung -->
                <div class="tc-wiz-step" data-step="2">
                    <h2>Anmeldungseinstellungen</h2>
                    <p class="tc-wiz-desc">Wie sprichst du deine Teilnehmer an, und wohin sollen Anmeldungs-Benachrichtigungen?</p>

                    <div class="tc-wiz-field">
                        <label>Anredeform</label>
                        <div class="tc-wiz-radio-group">
                            <label>
                                <input type="radio" name="tc_wiz_anrede" value="formal"
                                    <?php checked( $anrede, 'formal' ); ?>>
                                <span class="tc-wiz-radio-text">
                                    <strong>Formell (Sie)</strong>
                                    Bestätigungsmails und Formulare in der Sie-Form
                                </span>
                            </label>
                            <label>
                                <input type="radio" name="tc_wiz_anrede" value="informal"
                                    <?php checked( $anrede, 'informal' ); ?>>
                                <span class="tc-wiz-radio-text">
                                    <strong>Informell (Du)</strong>
                                    Entspannter Ton in Formularen und Mails
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="tc-wiz-field">
                        <label for="tc-wiz-reg-email">Benachrichtigungs-E-Mail für neue Anmeldungen</label>
                        <input type="email" id="tc-wiz-reg-email"
                               value="<?php echo esc_attr( $reg_mail ); ?>"
                               placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                        <p class="tc-wiz-notice">
                            Diese Adresse erhält eine Kopie bei jeder neuen Event-Anmeldung.
                        </p>
                    </div>
                </div>

                <!-- Schritt 3: Kategorien -->
                <div class="tc-wiz-step" data-step="3">
                    <h2>Event-Kategorien anlegen</h2>
                    <p class="tc-wiz-desc">Kategorien helfen dabei, Events farblich zu gruppieren. Du kannst sie jederzeit in den Einstellungen anpassen.</p>

                    <div id="tc-wiz-categories-list"></div>

                    <div class="tc-wiz-cat-add-row">
                        <input type="text"  id="tc-wiz-cat-name"  placeholder="Kategoriename">
                        <input type="color" id="tc-wiz-cat-color" value="#4f46e5">
                        <button type="button" class="tc-wiz-btn-add" id="tc-wiz-btn-add-cat">
                            + Hinzufügen
                        </button>
                    </div>
                    <p class="tc-wiz-skip">Du kannst diesen Schritt überspringen und Kategorien später anlegen.</p>
                </div>

                <!-- Schritt 4: E-Mail-Absender -->
                <div class="tc-wiz-step" data-step="4">
                    <h2>E-Mail-Absender</h2>
                    <p class="tc-wiz-desc">Name und Adresse, die Teilnehmer als Absender in Bestätigungsmails sehen.</p>

                    <div class="tc-wiz-field">
                        <label for="tc-wiz-mail-name">Absendername</label>
                        <input type="text" id="tc-wiz-mail-name"
                               value="<?php echo esc_attr( tc_get_setting( 'mail_from_name', get_bloginfo( 'name' ) ) ); ?>"
                               placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                    </div>

                    <div class="tc-wiz-field">
                        <label for="tc-wiz-mail-email">Absender-E-Mail-Adresse</label>
                        <input type="email" id="tc-wiz-mail-email"
                               value="<?php echo esc_attr( tc_get_setting( 'mail_from_email', get_option( 'admin_email' ) ) ); ?>"
                               placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                        <p class="tc-wiz-notice">
                            Stelle sicher, dass diese Adresse auf deinem Server autorisiert ist, um Spam-Probleme zu vermeiden.
                        </p>
                    </div>
                </div>

                <!-- Schritt 5: Fertig -->
                <div class="tc-wiz-step" data-step="5">
                    <div class="tc-wiz-success">
                        <span class="tc-wiz-success-icon">🎉</span>
                        <h2>Alles bereit!</h2>
                        <p class="tc-wiz-desc">
                            Time Calendar ist eingerichtet und startklar.<br>
                            Lege jetzt dein erstes Event an oder passe weitere Einstellungen an.
                        </p>
                        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                            <button type="button" class="tc-wiz-btn tc-wiz-btn-primary"
                                    id="tc-wiz-go-calendar"
                                    data-href="<?php echo esc_url( $cal_url ); ?>">
                                🗓 Zum Kalender
                            </button>
                            <button type="button" class="tc-wiz-btn tc-wiz-btn-secondary"
                                    id="tc-wiz-go-settings"
                                    data-href="<?php echo esc_url( $stg_url ); ?>">
                                ⚙ Zu den Einstellungen
                            </button>
                        </div>
                    </div>
                </div>

            </div><!-- /.tc-wiz-body -->

            <!-- ── Footer ── -->
            <div class="tc-wiz-footer">
                <button type="button" class="tc-wiz-btn tc-wiz-btn-ghost"
                        id="tc-wiz-btn-back">← Zurück</button>

                <div style="display:flex;gap:10px;align-items:center">
                    <button type="button" class="tc-wiz-btn tc-wiz-btn-secondary"
                            id="tc-wiz-btn-skip" style="display:none">
                        Überspringen
                    </button>
                    <button type="button" class="tc-wiz-btn tc-wiz-btn-primary"
                            id="tc-wiz-btn-next">
                        Weiter
                    </button>
                </div>
            </div>

        </div><!-- /#tc-wizard-modal -->
    </div><!-- /#tc-wizard-backdrop -->
    <?php
} );

// ── AJAX: Wizard abschließen ─────────────────────────────────────────
add_action( 'wp_ajax_tc_complete_wizard', function () {
    check_ajax_referer( 'tc_wizard_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
    }

    update_option( 'tc_wizard_completed', '1' );
    wp_send_json_success();
} );

// ── AJAX: Einstellungen speichern ────────────────────────────────────
add_action( 'wp_ajax_tc_save_wizard_settings', function () {
    check_ajax_referer( 'tc_wizard_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
    }

    $current = get_option( 'tc_settings', array() );

    $allowed = array(
        'primary_color'     => 'sanitize_hex_color',
        'calendar_mode'     => 'sanitize_text_field',
        'anrede_mode'       => 'sanitize_text_field',
        'registration_email'=> 'sanitize_email',
        'mail_from_name'    => 'sanitize_text_field',
        'mail_from_email'   => 'sanitize_email',
    );

    foreach ( $allowed as $key => $sanitizer ) {
        if ( isset( $_POST[ $key ] ) && $_POST[ $key ] !== '' ) {
            $current[ $key ] = $sanitizer( wp_unslash( $_POST[ $key ] ) );
        }
    }

    update_option( 'tc_settings', $current );
    wp_send_json_success();
} );
