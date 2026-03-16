<?php
defined( 'ABSPATH' ) || exit;

// ---------------------------------------------
// Tabelle anlegen / aktualisieren
// ---------------------------------------------
function tc_create_registrations_table() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'tc_registrations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        firstname varchar(255) NOT NULL,
        lastname varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(20),
        address varchar(255),
        zip varchar(20),
        city varchar(100),
        event_id bigint(20) NOT NULL,
        event_date date DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        notes longtext,
        reminder_sent tinyint(1) NOT NULL DEFAULT 0,
        cancel_token varchar(64) DEFAULT NULL,
        created_at bigint(20) NOT NULL,
        PRIMARY KEY (id),
        KEY email (email),
        KEY event_id (event_id),
        KEY status (status),
        KEY cancel_token (cancel_token),
        KEY email_event (email, event_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// Nur beim Plugin-Aktivieren ausführen (register_activation_hook in functions.php),
// nicht bei jedem admin_init.

// ---------------------------------------------
// Helpers
// ---------------------------------------------
function tc_get_all_registrations() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}tc_registrations ORDER BY created_at DESC",
        ARRAY_A
    );
    return $rows ?: array();
}

function tc_get_registration( $id ) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_registrations WHERE id = %d", $id ),
        ARRAY_A
    );
}

function tc_update_registration( $id, $data ) {
    global $wpdb;
    $allowed = array( 'firstname', 'lastname', 'email', 'phone', 'address', 'zip', 'city', 'event_id', 'event_date', 'status', 'notes', 'reminder_sent', 'cancel_token' );
    $clean   = array_intersect_key( $data, array_flip( $allowed ) );
    if ( empty( $clean ) ) return false;
    return $wpdb->update( "{$wpdb->prefix}tc_registrations", $clean, array( 'id' => $id ) );
}

function tc_delete_registration( $id ) {
    global $wpdb;
    return $wpdb->delete( "{$wpdb->prefix}tc_registrations", array( 'id' => $id ), array( '%d' ) );
}

// ---------------------------------------------
// Helper: Event-Infos für Mails aufbereiten
// ---------------------------------------------
function tc_get_event_mail_info( $event_id, $event_date = '' ) {
    $event      = get_post( $event_id );
    $title      = $event ? $event->post_title : '-';
    $start_date = get_field( 'start_date', $event_id );
    $start_time = get_field( 'start_time', $event_id );
    $end_date   = get_field( 'end_date',   $event_id );
    $location   = wp_strip_all_tags( get_field( 'location', $event_id ) ?: '' );

    if ( $event_date ) {
        $d        = DateTime::createFromFormat( 'Y-m-d', $event_date );
        $date_str = $d ? $d->format( 'd.m.Y' ) : $event_date;
        if ( $start_time ) $date_str .= ' um ' . $start_time . ' Uhr';
    } elseif ( $start_date ) {
        $d        = DateTime::createFromFormat( 'Y-m-d', $start_date );
        $date_str = $d ? $d->format( 'd.m.Y' ) : $start_date;
        if ( $start_time ) $date_str .= ' um ' . $start_time . ' Uhr';
        if ( $end_date && $end_date !== $start_date ) {
            $de        = DateTime::createFromFormat( 'Y-m-d', $end_date );
            $date_str .= ' - ' . ( $de ? $de->format( 'd.m.Y' ) : $end_date );
        }
    } else {
        $date_str = '-';
    }

    return array(
        'title'    => $title,
        'date'     => $date_str,
        'location' => $location ?: '-',
    );
}

// ---------------------------------------------
// Mail 1: Dankes-Mail direkt nach Anmeldung
// ---------------------------------------------
function tc_send_thank_you_mail( $data ) {
    $info        = tc_get_event_mail_info( $data['event_id'], $data['event_date'] ?? '' );
    $is_trial    = (bool) get_field( 'price_on_request', $data['event_id'] );
    $blogname    = get_option( 'blogname' );
    $headers     = array( 'Content-Type: text/html; charset=UTF-8' );
    $cancel_url  = ! empty( $data['cancel_token'] )
        ? add_query_arg( 'tc_cancel', $data['cancel_token'], home_url( '/' ) )
        : '';

    if ( $is_trial ) {
        $subject = 'Deine Probetraining-Anfrage ist eingegangen - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#5a7a00;margin-top:0;">Deine Anfrage ist bei uns eingegangen!</h2>';
        $msg .= '<p>Hallo ' . esc_html( $data['firstname'] ) . ' ' . esc_html( $data['lastname'] ) . ',</p>';
        $msg .= '<p>vielen Dank, dass du dich für ein <strong>kostenloses Probetraining</strong> interessierst! Wir haben deine Anfrage erhalten und melden uns zeitnah bei dir.</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Das Probetraining ist selbstverständlich <strong>kostenlos und unverbindlich</strong>. Schnupper einfach rein und schau, ob es dir gefällt.</p>';
        $msg .= '<p>Bei Fragen kannst du dich jederzeit bei uns melden.</p>';
        if ( $cancel_url ) $msg .= tc_mail_cancel_block( $cancel_url );
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    } else {
        $subject = 'Vielen Dank für Ihre Anmeldung - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#0066cc;margin-top:0;">Vielen Dank für Ihre Anmeldung!</h2>';
        $msg .= '<p>Hallo ' . esc_html( $data['firstname'] ) . ' ' . esc_html( $data['lastname'] ) . ',</p>';
        $msg .= '<p>wir haben Ihre Anmeldung erhalten und melden uns zeitnah mit einer Bestätigung bei Ihnen.</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>';
        if ( $cancel_url ) $msg .= tc_mail_cancel_block( $cancel_url );
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    }

    wp_mail( $data['email'], $subject, $msg, $headers );
}

// ---------------------------------------------
// Mail 2: Bestaetigung nach Admin-Freigabe
// ---------------------------------------------
function tc_send_confirmation_mail( $registration_id ) {
    $reg = tc_get_registration( $registration_id );
    if ( ! $reg ) return;

    $info       = tc_get_event_mail_info( $reg['event_id'], $reg['event_date'] ?? '' );
    $is_trial   = (bool) get_field( 'price_on_request', $reg['event_id'] );
    $blogname   = get_option( 'blogname' );
    $headers    = array( 'Content-Type: text/html; charset=UTF-8' );
    $cancel_url = ! empty( $reg['cancel_token'] )
        ? add_query_arg( 'tc_cancel', $reg['cancel_token'], home_url( '/' ) )
        : '';

    if ( $is_trial ) {
        $subject = 'Dein Probetraining ist bestätigt - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#059669;margin-top:0;">Dein Probetraining ist bestätigt! &#10003;</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>wir freuen uns auf dich! Dein kostenloses Probetraining ist hiermit bestätigt. Wir sehen uns beim Termin!</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Bring bequeme Sportkleidung mit und komm einfach vorbei. Bei Fragen melde dich gerne jederzeit.</p>';
        if ( $cancel_url ) $msg .= tc_mail_cancel_block( $cancel_url );
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    } else {
        $subject = 'Ihre Anmeldung ist bestätigt - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#059669;margin-top:0;">Ihre Anmeldung ist bestätigt! &#10003;</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>wir freuen uns, Ihre Anmeldung hiermit offiziell zu bestaetigen. Wir sehen Sie beim Termin!</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Bei Fragen vor dem Termin stehen wir Ihnen gerne zur Verfuegung.</p>';
        if ( $cancel_url ) $msg .= tc_mail_cancel_block( $cancel_url );
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    }

    wp_mail( $reg['email'], $subject, $msg, $headers );
}

// ---------------------------------------------
// Mail 3: Absage nach Admin-Stornierung
// ---------------------------------------------
function tc_send_cancellation_mail( $registration_id ) {
    $reg = tc_get_registration( $registration_id );
    if ( ! $reg ) return;

    $info      = tc_get_event_mail_info( $reg['event_id'], $reg['event_date'] ?? '' );
    $is_trial  = (bool) get_field( 'price_on_request', $reg['event_id'] );
    $blogname  = get_option( 'blogname' );
    $headers   = array( 'Content-Type: text/html; charset=UTF-8' );

    if ( $is_trial ) {
        $subject = 'Deine Probetraining-Anfrage konnte leider nicht bestätigt werden - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#dc2626;margin-top:0;">Deine Probetraining-Anfrage konnte nicht bestätigt werden</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>leider muessen wir dir mitteilen, dass deine Anfrage für ein Probetraining für den folgenden Termin nicht bestätigt werden konnte.</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Melde dich gerne bei uns, wenn du einen anderen Termin finden möchtest. Wir helfen dir gerne weiter!</p>';
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    } else {
        $subject = 'Ihre Anmeldung konnte leider nicht bestätigt werden - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#dc2626;margin-top:0;">Ihre Anmeldung konnte nicht bestätigt werden</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>leider muessen wir Ihnen mitteilen, dass Ihre Anmeldung für den folgenden Termin nicht bestätigt werden konnte.</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Bei Fragen oder wenn Sie einen alternativen Termin buchen möchten, melden Sie sich gerne bei uns.</p>';
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    }

    wp_mail( $reg['email'], $subject, $msg, $headers );
}

// ---------------------------------------------
// Mail: Admin-Benachrichtigung (neue Anmeldung)
// ---------------------------------------------
function tc_send_admin_notification( $data ) {
    $admin_email = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );
    if ( $admin_email === $data['email'] ) return;

    $info      = tc_get_event_mail_info( $data['event_id'], $data['event_date'] ?? '' );
    $is_trial  = (bool) get_field( 'price_on_request', $data['event_id'] );
    $blogname  = get_option( 'blogname' );
    $headers   = array( 'Content-Type: text/html; charset=UTF-8' );

    if ( $is_trial ) {
        $subject = 'Neue Probetraining-Anfrage: ' . $info['title'] . ' - ' . $data['firstname'] . ' ' . $data['lastname'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#5a7a00;margin-top:0;">Neue Probetraining-Anfrage eingegangen</h2>';
        $msg .= '<div style="background:#f0fdf4;border-left:4px solid #a4d61f;padding:10px 16px;border-radius:4px;margin-bottom:16px;font-size:14px;">'
              . '<strong>Kostenlos &amp; unverbindlich</strong> &ndash; Dies ist eine Probetraining-Anfrage.'
              . '</div>';
    } else {
        $subject = 'Neue Anmeldung: ' . $info['title'] . ' - ' . $data['firstname'] . ' ' . $data['lastname'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#0066cc;margin-top:0;">Neue Anmeldung eingegangen</h2>';
    }

    $msg .= tc_event_info_block( $info );
    $msg .= '<h3 style="margin-bottom:8px;">' . ( $is_trial ? 'Anfragender' : 'Teilnehmer' ) . '</h3>';
    $msg .= '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
    $msg .= tc_mail_row( 'Name',   $data['firstname'] . ' ' . $data['lastname'] );
    $msg .= tc_mail_row( 'E-Mail', $data['email'] );
    if ( ! empty( $data['phone'] ) )   $msg .= tc_mail_row( 'Telefon', esc_html( $data['phone'] ) );
    if ( ! $is_trial ) {
        if ( ! empty( $data['address'] ) ) $msg .= tc_mail_row( 'Adresse', esc_html( $data['address'] ) );
        if ( ! empty( $data['zip'] ) || ! empty( $data['city'] ) ) {
            $msg .= tc_mail_row( 'PLZ / Ort', esc_html( trim( $data['zip'] . ' ' . $data['city'] ) ) );
        }
    }
    if ( ! empty( $data['notes'] ) )   $msg .= tc_mail_row( 'Notizen', nl2br( esc_html( $data['notes'] ) ) );
    $msg .= '</table>';
    $msg .= '<p style="margin-top:20px;"><a href="' . esc_url( admin_url( 'admin.php?page=training-registrations' ) ) . '" '
          . 'style="background:#0066cc;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;">'
          . 'Zur Anmeldungsuebersicht</a></p>';
    $msg .= tc_mail_wrapper_close();

    wp_mail( $admin_email, $subject, $msg, $headers );
}

// ---------------------------------------------
// Mail-Hilfsfunktionen (DRY)
// ---------------------------------------------
function tc_mail_wrapper_open( $blogname ) {
    return '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;">'
         . '<div style="max-width:600px;margin:0 auto;padding:24px;background:#f9f9f9;'
         . 'border:1px solid #e0e0e0;border-radius:8px;">';
}

function tc_mail_wrapper_close() {
    return '</div></body></html>';
}

function tc_mail_signature( $blogname ) {
    return '<hr style="border:none;border-top:1px solid #ddd;margin:20px 0;">'
         . '<p style="font-size:.9em;color:#666;">Mit freundlichen Gruessen<br>'
         . '<strong>' . esc_html( $blogname ) . '</strong></p>';
}

function tc_event_info_block( $info ) {
    return '<div style="background:#eef2ff;border-left:4px solid #4f46e5;padding:14px 18px;'
         . 'border-radius:4px;margin:16px 0;">'
         . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
         . tc_mail_row( 'Veranstaltung', esc_html( $info['title'] ) )
         . tc_mail_row( 'Datum',         esc_html( $info['date'] ) )
         . tc_mail_row( 'Ort',           esc_html( $info['location'] ) )
         . '</table></div>';
}

function tc_mail_cancel_block( string $cancel_url ): string {
    return '<div style="margin-top:20px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:13px;color:#6b7280;">'
         . 'Möchten Sie Ihre Anmeldung stornieren? '
         . '<a href="' . esc_url( $cancel_url ) . '" style="color:#dc2626;text-decoration:underline;">Hier klicken</a>.'
         . '</div>';
}

function tc_mail_row( $label, $value ) {
    return '<tr>'
         . '<td style="padding:4px 12px 4px 0;font-weight:600;white-space:nowrap;vertical-align:top;color:#374151;">'
         . esc_html( $label ) . ':</td>'
         . '<td style="padding:4px 0;color:#111827;">' . $value . '</td>'
         . '</tr>';
}

// ---------------------------------------------
// Hook: nach Anmeldung - Dankes-Mail + Admin
// ---------------------------------------------
add_action( 'tc_registration_submitted', function ( $registration_id, $data ) {
    tc_send_thank_you_mail( $data );
    tc_send_admin_notification( $data );
}, 10, 2 );

// ---------------------------------------------
// AJAX: Anmeldung erstellen
// ---------------------------------------------
add_action( 'wp_ajax_nopriv_' . TC_AJAX_SUBMIT_REGISTRATION, 'tc_handle_registration_submission' );
add_action( 'wp_ajax_' . TC_AJAX_SUBMIT_REGISTRATION,        'tc_handle_registration_submission' );

function tc_handle_registration_submission() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

    // Rate Limiting: max. TC_RATE_LIMIT_COUNT Anmeldungen pro IP innerhalb von TC_RATE_LIMIT_SECONDS
    $ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
    $rl_key    = 'tc_reg_limit_' . md5( $ip );
    $rl_count  = (int) get_transient( $rl_key );
    if ( $rl_count >= TC_RATE_LIMIT_COUNT ) {
        wp_send_json_error( array( 'message' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.' ) );
    }
    set_transient( $rl_key, $rl_count + 1, TC_RATE_LIMIT_SECONDS );

    global $wpdb;
    $table_name = $wpdb->prefix . 'tc_registrations';

    $firstname  = sanitize_text_field(    $_POST['firstname']  ?? '' );
    $lastname   = sanitize_text_field(    $_POST['lastname']   ?? '' );
    $email      = sanitize_email(         $_POST['email']      ?? '' );
    $phone      = sanitize_text_field(    $_POST['phone']      ?? '' );
    $address    = sanitize_text_field(    $_POST['address']    ?? '' );
    $zip        = sanitize_text_field(    $_POST['zip']        ?? '' );
    $city       = sanitize_text_field(    $_POST['city']       ?? '' );
    $event_id   = absint(                 $_POST['event_id']   ?? 0  );
    $event_date = sanitize_text_field(    $_POST['event_date'] ?? '' );
    $notes      = sanitize_textarea_field($_POST['notes']      ?? '' );

    if ( $event_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $event_date ) ) {
        wp_send_json_error( array( 'message' => 'Ungültiges Datumsformat.' ) );
    }

    if ( ! $firstname || ! $lastname || ! $email || ! is_email( $email ) || ! $event_id ) {
        wp_send_json_error( array( 'message' => 'Bitte fuellen Sie alle erforderlichen Felder aus.' ) );
    }

    if ( get_post_type( $event_id ) !== 'time_event' ) {
        wp_send_json_error( array( 'message' => 'Diese Veranstaltung existiert nicht.' ) );
    }

    $is_trial = (bool) get_field( 'price_on_request', $event_id );

    $track_p = get_field( 'track_participants', $event_id );
    $max_p   = get_field( 'participants',       $event_id );
    if ( $track_p && $max_p ) {
        $cur = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE event_id = %d AND status IN ('pending','confirmed')",
            $event_id
        ) );
        if ( $cur >= (int) $max_p ) {
            wp_send_json_error( array( 'message' => 'Leider ist dieser Termin bereits ausgebucht.' ) );
        }
    }

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE email = %s AND event_id = %d",
        $email, $event_id
    ) );
    if ( $existing ) {
        $dup_msg = $is_trial
            ? 'Du hast bereits eine Probetraining-Anfrage für diese Veranstaltung gestellt.'
            : 'Sie sind bereits für diese Veranstaltung angemeldet.';
        wp_send_json_error( array( 'message' => $dup_msg ) );
    }

    $cancel_token = bin2hex( random_bytes( 32 ) );

    $inserted = $wpdb->insert(
        $table_name,
        array(
            'firstname'    => $firstname,
            'lastname'     => $lastname,
            'email'        => $email,
            'phone'        => $phone,
            'address'      => $address,
            'zip'          => $zip,
            'city'         => $city,
            'event_id'     => $event_id,
            'event_date'   => $event_date ?: null,
            'status'       => 'pending',
            'notes'        => $notes,
            'cancel_token' => $cancel_token,
            'created_at'   => time(),
        ),
        array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d' )
    );

    if ( $inserted === false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'TC Registration DB error: ' . $wpdb->last_error );
        }
        wp_send_json_error( array( 'message' => 'Fehler beim Speichern der Anmeldung.' ) );
    }

    $new_id = $wpdb->insert_id;

    do_action( 'tc_registration_submitted', $new_id, array(
        'firstname'    => $firstname,
        'lastname'     => $lastname,
        'email'        => $email,
        'phone'        => $phone,
        'address'      => $address,
        'zip'          => $zip,
        'city'         => $city,
        'event_id'     => $event_id,
        'event_date'   => $event_date,
        'notes'        => $notes,
        'cancel_token' => $cancel_token,
    ) );

    $success_msg = $is_trial
        ? 'Vielen Dank! Deine Anfrage für ein Probetraining wurde erfolgreich übermittelt. Wir melden uns zeitnah bei dir.'
        : 'Vielen Dank! Ihre Anmeldung wurde erfolgreich gespeichert.';

    wp_send_json_success( array(
        'message'         => $success_msg,
        'registration_id' => $new_id,
    ) );
}

// ---------------------------------------------
// AJAX: Event-Details laden
// ---------------------------------------------
add_action( 'wp_ajax_nopriv_' . TC_AJAX_GET_EVENT_DETAILS, 'tc_get_event_details_ajax' );
add_action( 'wp_ajax_' . TC_AJAX_GET_EVENT_DETAILS,        'tc_get_event_details_ajax' );

function tc_get_event_details_ajax() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

    $event_id = absint( $_POST['event_id'] ?? 0 );
    if ( ! $event_id || get_post_type( $event_id ) !== 'time_event' ) {
        wp_send_json_error( array( 'message' => 'Event nicht gefunden.' ) );
    }

    $event  = get_post( $event_id );
    $fields = get_fields( $event_id ) ?: array();

    $leadership        = $fields['seminar_leadership']  ?? null;
    $location          = $fields['location']            ?? null;
    $start_date        = $fields['start_date']          ?? null;
    $start_time        = $fields['start_time']          ?? null;
    $more_days         = $fields['more_days']           ?? null;
    $end_date          = $fields['end_date']            ?? null;
    $is_recurring      = $fields['is_recurring']        ?? null;
    $recurring_weekday = $fields['recurring_weekday']   ?? null;
    $recurring_until   = $fields['recurring_until']     ?? null;
    $track_p           = $fields['track_participants']  ?? null;
    $max_p             = $fields['participants']        ?? null;

    $dates = array(); $is_multiday = false; $is_recurring_event = false;

    if ( $more_days && $end_date ) {
        $is_multiday = true;
        try {
            $cur = new DateTime( $start_date );
            $end = new DateTime( $end_date );
            while ( $cur <= $end ) { $dates[] = $cur->format('Y-m-d'); $cur->modify('+1 day'); }
        } catch ( Exception $e ) {
            if ( $start_date ) $dates = array( $start_date );
        }
    } elseif ( $is_recurring && $recurring_weekday !== '' && $recurring_until ) {
        $is_recurring_event = true;
        try {
            $target   = (int) $recurring_weekday;
            $cur      = new DateTime( $start_date );
            $until_dt = new DateTime( $recurring_until . ' 23:59:59' );
            $diff     = ( $target - (int) $cur->format('w') + 7 ) % 7;
            if ( $diff > 0 ) $cur->modify( "+{$diff} days" );
            $limit = 0;
            while ( $cur <= $until_dt && $limit < TC_RECURRING_LIMIT ) {
                $dates[] = $cur->format('Y-m-d');
                $cur->modify('+7 days');
                $limit++;
            }
        } catch ( Exception $e ) {
            if ( $start_date ) $dates = array( $start_date );
        }
    } else {
        $dates[] = $start_date;
    }

    $current_reg = 0; $is_full = false;
    if ( $track_p && $max_p ) {
        global $wpdb;
        $current_reg = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tc_registrations WHERE event_id = %d AND status IN ('pending','confirmed')",
            $event_id
        ) );
        $is_full = $current_reg >= (int) $max_p;
    }

    wp_send_json_success( array(
        'title'                 => $event->post_title,
        'leadership'            => $leadership ?: 'Nicht angegeben',
        'location'              => wp_strip_all_tags( $location ?: 'Nicht angegeben' ),
        'start_date'            => $start_date ?: 'Nicht angegeben',
        'start_time'            => $start_time ?: '',
        'is_multiday'           => (bool) $is_multiday,
        'is_recurring'          => (bool) $is_recurring_event,
        'dates'                 => $dates,
        'track_participants'    => (bool) $track_p,
        'max_participants'      => $max_p ? (int) $max_p : 0,
        'current_registrations' => $current_reg,
        'is_full'               => $is_full,
    ) );
}
