<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Selbst-Stornierung per Token-Link
// ─────────────────────────────────────────────

// URL-Parameter abfangen und Stornierungsseite ausgeben
add_action( 'template_redirect', function () {
    $token = isset( $_GET['tc_cancel'] ) ? sanitize_text_field( wp_unslash( $_GET['tc_cancel'] ) ) : '';
    if ( ! $token || strlen( $token ) < 16 ) return;

    global $wpdb;
    $reg = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tc_registrations WHERE cancel_token = %s",
            $token
        ),
        ARRAY_A
    );

    tc_render_cancel_page( $reg, $token );
    exit;
} );

// ─────────────────────────────────────────────
// AJAX: Stornierung durchführen
// ─────────────────────────────────────────────

add_action( 'wp_ajax_nopriv_tc_self_cancel', 'tc_handle_self_cancel' );
add_action( 'wp_ajax_tc_self_cancel',        'tc_handle_self_cancel' );

function tc_handle_self_cancel() {
    check_ajax_referer( 'tc_self_cancel_nonce', 'nonce' );

    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    if ( ! $token ) {
        wp_send_json_error( array( 'message' => 'Ungültiger Token.' ) );
    }

    global $wpdb;
    $reg = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tc_registrations WHERE cancel_token = %s",
            $token
        ),
        ARRAY_A
    );

    if ( ! $reg ) {
        wp_send_json_error( array( 'message' => 'Anmeldung nicht gefunden oder der Link ist bereits abgelaufen.' ) );
    }

    if ( $reg['status'] === 'cancelled' ) {
        wp_send_json_error( array( 'message' => 'Diese Anmeldung wurde bereits storniert.' ) );
    }

    // Status setzen + Token einmalig invalidieren
    tc_update_registration( (int) $reg['id'], array(
        'status'       => 'cancelled',
        'cancel_token' => '',
    ) );

    // Storno-Mail an Teilnehmer
    tc_send_cancellation_mail( (int) $reg['id'] );

    // Admin-Benachrichtigung
    tc_send_self_cancel_admin_notification( $reg );

    wp_send_json_success( array(
        'message' => 'Ihre Anmeldung wurde erfolgreich storniert. Sie erhalten in Kürze eine Bestätigungs-E-Mail.',
    ) );
}

// ─────────────────────────────────────────────
// Admin-Mail bei Selbststornierung
// ─────────────────────────────────────────────

function tc_send_self_cancel_admin_notification( array $reg ) {
    $admin_email = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );
    $info        = tc_get_event_mail_info( $reg['event_id'], $reg['event_date'] ?? '' );
    $blogname    = get_option( 'blogname' );
    $headers     = array( 'Content-Type: text/html; charset=UTF-8' );
    $subject     = 'Selbststornierung: ' . $reg['firstname'] . ' ' . $reg['lastname'] . ' – ' . $info['title'];

    $msg  = tc_mail_wrapper_open( $blogname );
    $msg .= '<h2 style="color:#dc2626;margin-top:0;">Anmeldung selbst storniert</h2>';
    $msg .= '<p>Ein Teilnehmer hat seine Anmeldung eigenständig storniert:</p>';
    $msg .= tc_event_info_block( $info );
    $msg .= '<h3 style="margin-bottom:8px;">Teilnehmer</h3>';
    $msg .= '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
    $msg .= tc_mail_row( 'Name',   esc_html( $reg['firstname'] . ' ' . $reg['lastname'] ) );
    $msg .= tc_mail_row( 'E-Mail', esc_html( $reg['email'] ) );
    if ( ! empty( $reg['phone'] ) ) $msg .= tc_mail_row( 'Telefon', esc_html( $reg['phone'] ) );
    $msg .= '</table>';
    $msg .= '<p style="margin-top:20px;"><a href="' . esc_url( admin_url( 'admin.php?page=training-registrations' ) ) . '" '
          . 'style="background:#dc2626;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;">'
          . 'Zur Anmeldungsübersicht</a></p>';
    $msg .= tc_mail_wrapper_close();

    wp_mail( $admin_email, $subject, $msg, $headers );
}

// ─────────────────────────────────────────────
// Stornierungsseite ausgeben
// ─────────────────────────────────────────────

function tc_render_cancel_page( ?array $reg, string $token ): void {
    $blogname  = get_option( 'blogname' );
    $home_url  = home_url( '/' );
    $ajax_url  = admin_url( 'admin-ajax.php' );
    $nonce     = wp_create_nonce( 'tc_self_cancel_nonce' );
    $primary   = '#4f46e5';

    $info      = $reg ? tc_get_event_mail_info( $reg['event_id'], $reg['event_date'] ?? '' ) : null;
    $name      = $reg ? esc_html( $reg['firstname'] . ' ' . $reg['lastname'] ) : '';
    $cancelled = $reg && $reg['status'] === 'cancelled';
    $not_found = ! $reg;
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Anmeldung stornieren – <?php echo esc_html( $blogname ); ?></title>
        <?php wp_head(); ?>
        <style>
        body { margin: 0; background: #f3f4f6; }
        .tc-cp-wrap {
            max-width: 500px;
            margin: 60px auto;
            padding: 0 20px 60px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #111827;
            line-height: 1.6;
        }
        .tc-cp-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 4px 24px rgba(0,0,0,.07);
        }
        .tc-cp-icon  { font-size: 38px; margin-bottom: 10px; display: block; }
        .tc-cp-title { font-size: 20px; font-weight: 700; margin: 0 0 6px; }
        .tc-cp-sub   { font-size: 14px; color: #6b7280; margin: 0 0 22px; }
        .tc-cp-event {
            background: #eef2ff;
            border-left: 4px solid <?php echo esc_attr( $primary ); ?>;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 22px;
            font-size: 14px;
        }
        .tc-cp-event strong { display: block; font-size: 15px; margin-bottom: 3px; }
        .tc-cp-event span   { color: #6b7280; display: block; }
        .tc-cp-msg {
            display: none;
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 14px;
        }
        .tc-cp-msg--error   { background: #fee2e2; color: #991b1b; }
        .tc-cp-msg--success { background: #d1fae5; color: #065f46; }
        .tc-cp-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: #dc2626;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background .15s;
            text-align: center;
            box-sizing: border-box;
        }
        .tc-cp-btn:hover:not(:disabled) { background: #b91c1c; }
        .tc-cp-btn:disabled { opacity: .55; cursor: default; }
        .tc-cp-back {
            display: block;
            margin-top: 14px;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
            text-decoration: none;
        }
        .tc-cp-back:hover { color: #111827; }
        </style>
    </head>
    <body>
    <div class="tc-cp-wrap">
        <div class="tc-cp-card">

        <?php if ( $not_found ) : ?>

            <span class="tc-cp-icon">⚠️</span>
            <h1 class="tc-cp-title">Link ungültig</h1>
            <p class="tc-cp-sub">Dieser Stornierungslink ist ungültig oder wurde bereits verwendet.</p>
            <a href="<?php echo esc_url( $home_url ); ?>" class="tc-cp-back">← Zur Startseite</a>

        <?php elseif ( $cancelled ) : ?>

            <span class="tc-cp-icon">✅</span>
            <h1 class="tc-cp-title">Bereits storniert</h1>
            <p class="tc-cp-sub">Diese Anmeldung wurde bereits storniert.</p>
            <?php if ( $info ) : ?>
            <div class="tc-cp-event">
                <strong><?php echo esc_html( $info['title'] ); ?></strong>
                <span><?php echo esc_html( $info['date'] ); ?></span>
            </div>
            <?php endif; ?>
            <a href="<?php echo esc_url( $home_url ); ?>" class="tc-cp-back">← Zur Startseite</a>

        <?php else : ?>

            <span class="tc-cp-icon">📋</span>
            <h1 class="tc-cp-title">Anmeldung stornieren</h1>
            <p class="tc-cp-sub">Hallo <?php echo $name; ?>, möchten Sie folgende Anmeldung wirklich stornieren?</p>
            <div class="tc-cp-event">
                <strong><?php echo esc_html( $info['title'] ); ?></strong>
                <span><?php echo esc_html( $info['date'] ); ?></span>
                <?php if ( $info['location'] && $info['location'] !== '-' ) : ?>
                <span>📍 <?php echo esc_html( $info['location'] ); ?></span>
                <?php endif; ?>
            </div>
            <div id="tc-cp-msg" class="tc-cp-msg"></div>
            <button id="tc-cp-btn" class="tc-cp-btn" type="button">
                Ja, Anmeldung jetzt stornieren
            </button>
            <a href="<?php echo esc_url( $home_url ); ?>" class="tc-cp-back">← Nein, zurück zur Startseite</a>

        <?php endif; ?>

        </div>
    </div>
    <script>
    (function () {
        var btn = document.getElementById('tc-cp-btn');
        var msg = document.getElementById('tc-cp-msg');
        if (!btn) return;
        btn.addEventListener('click', function () {
            btn.disabled    = true;
            btn.textContent = 'Wird storniert\u2026';
            msg.style.display = 'none';
            fetch('<?php echo esc_url( $ajax_url ); ?>', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'tc_self_cancel',
                    nonce:  '<?php echo esc_js( $nonce ); ?>',
                    token:  '<?php echo esc_js( $token ); ?>',
                }),
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    msg.className     = 'tc-cp-msg tc-cp-msg--success';
                    msg.textContent   = res.data.message;
                    msg.style.display = 'block';
                    btn.style.display = 'none';
                } else {
                    msg.className     = 'tc-cp-msg tc-cp-msg--error';
                    msg.textContent   = (res.data && res.data.message) || 'Es ist ein Fehler aufgetreten.';
                    msg.style.display = 'block';
                    btn.disabled      = false;
                    btn.textContent   = 'Ja, Anmeldung jetzt stornieren';
                }
            })
            .catch(function () {
                msg.className     = 'tc-cp-msg tc-cp-msg--error';
                msg.textContent   = 'Netzwerkfehler. Bitte versuchen Sie es erneut.';
                msg.style.display = 'block';
                btn.disabled      = false;
                btn.textContent   = 'Ja, Anmeldung jetzt stornieren';
            });
        });
    })();
    </script>
    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
}
