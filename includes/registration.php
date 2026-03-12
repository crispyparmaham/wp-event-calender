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
        created_at bigint(20) NOT NULL,
        PRIMARY KEY (id),
        KEY email (email),
        KEY event_id (event_id),
        KEY status (status)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

add_action( 'admin_init', 'tc_create_registrations_table' );

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
    $allowed = array( 'firstname', 'lastname', 'email', 'phone', 'address', 'zip', 'city', 'event_id', 'event_date', 'status', 'notes' );
    $clean   = array_intersect_key( $data, array_flip( $allowed ) );
    if ( empty( $clean ) ) return false;
    return $wpdb->update( "{$wpdb->prefix}tc_registrations", $clean, array( 'id' => $id ) );
}

function tc_delete_registration( $id ) {
    global $wpdb;
    return $wpdb->delete( "{$wpdb->prefix}tc_registrations", array( 'id' => $id ), array( '%d' ) );
}

// ---------------------------------------------
// Helper: Event-Infos fuer Mails aufbereiten
// Gibt array( 'title', 'date', 'location' )
// ---------------------------------------------
function tc_get_event_mail_info( $event_id, $event_date = '' ) {
    $event      = get_post( $event_id );
    $title      = $event ? $event->post_title : '-';
    $start_date = get_field( 'start_date', $event_id ); // Y-m-d
    $start_time = get_field( 'start_time', $event_id ); // H:i
    $end_date   = get_field( 'end_date',   $event_id ); // Y-m-d
    $location   = wp_strip_all_tags( get_field( 'location', $event_id ) ?: '' );

    // Datum formatieren
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
    $info      = tc_get_event_mail_info( $data['event_id'], $data['event_date'] ?? '' );
    $is_trial  = (bool) get_field( 'price_on_request', $data['event_id'] );
    $blogname  = get_option( 'blogname' );
    $headers   = array( 'Content-Type: text/html; charset=UTF-8' );

    if ( $is_trial ) {
        $subject = 'Deine Probetraining-Anfrage ist eingegangen - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#5a7a00;margin-top:0;">Deine Anfrage ist bei uns eingegangen!</h2>';
        $msg .= '<p>Hallo ' . esc_html( $data['firstname'] ) . ' ' . esc_html( $data['lastname'] ) . ',</p>';
        $msg .= '<p>vielen Dank, dass du dich fuer ein <strong>kostenloses Probetraining</strong> interessierst! Wir haben deine Anfrage erhalten und melden uns zeitnah bei dir.</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Das Probetraining ist selbstverstaendlich <strong>kostenlos und unverbindlich</strong>. Schnupper einfach rein und schau, ob es dir gefaellt.</p>';
        $msg .= '<p>Bei Fragen kannst du dich jederzeit bei uns melden.</p>';
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    } else {
        $subject = 'Vielen Dank fuer Ihre Anmeldung - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#0066cc;margin-top:0;">Vielen Dank fuer Ihre Anmeldung!</h2>';
        $msg .= '<p>Hallo ' . esc_html( $data['firstname'] ) . ' ' . esc_html( $data['lastname'] ) . ',</p>';
        $msg .= '<p>wir haben Ihre Anmeldung erhalten und melden uns zeitnah mit einer Bestaetigung bei Ihnen.</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Bei Fragen stehen wir Ihnen gerne zur Verfuegung.</p>';
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

    $info      = tc_get_event_mail_info( $reg['event_id'], $reg['event_date'] ?? '' );
    $is_trial  = (bool) get_field( 'price_on_request', $reg['event_id'] );
    $blogname  = get_option( 'blogname' );
    $headers   = array( 'Content-Type: text/html; charset=UTF-8' );

    if ( $is_trial ) {
        $subject = 'Dein Probetraining ist bestaetigt - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#059669;margin-top:0;">Dein Probetraining ist bestaetigt! &#10003;</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>wir freuen uns auf dich! Dein kostenloses Probetraining ist hiermit bestaetigt. Wir sehen uns beim Termin!</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Bring bequeme Sportkleidung mit und komm einfach vorbei. Bei Fragen melde dich gerne jederzeit.</p>';
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    } else {
        $subject = 'Ihre Anmeldung ist bestaetigt - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#059669;margin-top:0;">Ihre Anmeldung ist bestaetigt! &#10003;</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>wir freuen uns, Ihre Anmeldung hiermit offiziell zu bestaetigen. Wir sehen Sie beim Termin!</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Bei Fragen vor dem Termin stehen wir Ihnen gerne zur Verfuegung.</p>';
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
        $subject = 'Deine Probetraining-Anfrage konnte leider nicht bestaetigt werden - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#dc2626;margin-top:0;">Deine Probetraining-Anfrage konnte nicht bestaetigt werden</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>leider muessen wir dir mitteilen, dass deine Anfrage fuer ein Probetraining fuer den folgenden Termin nicht bestaetigt werden konnte.</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Melde dich gerne bei uns, wenn du einen anderen Termin finden moechtest. Wir helfen dir gerne weiter!</p>';
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    } else {
        $subject = 'Ihre Anmeldung konnte leider nicht bestaetigt werden - ' . $info['title'];
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#dc2626;margin-top:0;">Ihre Anmeldung konnte nicht bestaetigt werden</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>leider muessen wir Ihnen mitteilen, dass Ihre Anmeldung fuer den folgenden Termin nicht bestaetigt werden konnte.</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Bei Fragen oder wenn Sie einen alternativen Termin buchen moechten, melden Sie sich gerne bei uns.</p>';
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
         . tc_mail_row( 'Veranstaltung', $info['title'] )
         . tc_mail_row( 'Datum',         $info['date'] )
         . tc_mail_row( 'Ort',           $info['location'] )
         . '</table></div>';
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
add_action( 'wp_ajax_nopriv_tc_submit_registration', 'tc_handle_registration_submission' );
add_action( 'wp_ajax_tc_submit_registration',        'tc_handle_registration_submission' );

function tc_handle_registration_submission() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

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

    if ( ! $firstname || ! $lastname || ! $email || ! is_email( $email ) || ! $event_id ) {
        wp_send_json_error( array( 'message' => 'Bitte fuellen Sie alle erforderlichen Felder aus.' ) );
    }

    if ( get_post_type( $event_id ) !== 'training_event' ) {
        wp_send_json_error( array( 'message' => 'Diese Veranstaltung existiert nicht.' ) );
    }

    // Probetraining serverseitig pruefen (nicht dem POST-Feld vertrauen)
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
            ? 'Du hast bereits eine Probetraining-Anfrage fuer diese Veranstaltung gestellt.'
            : 'Sie sind bereits fuer diese Veranstaltung angemeldet.';
        wp_send_json_error( array( 'message' => $dup_msg ) );
    }

    $inserted = $wpdb->insert(
        $table_name,
        array(
            'firstname'  => $firstname,
            'lastname'   => $lastname,
            'email'      => $email,
            'phone'      => $phone,
            'address'    => $address,
            'zip'        => $zip,
            'city'       => $city,
            'event_id'   => $event_id,
            'event_date' => $event_date ?: null,
            'status'     => 'pending',
            'notes'      => $notes,
            'created_at' => time(),
        ),
        array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
    );

    if ( $inserted === false ) {
        wp_send_json_error( array(
            'message' => 'Fehler beim Speichern der Anmeldung.'
                         . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? ' DB: ' . $wpdb->last_error : '' ),
        ) );
    }

    $new_id = $wpdb->insert_id;

    do_action( 'tc_registration_submitted', $new_id, array(
        'firstname'  => $firstname,
        'lastname'   => $lastname,
        'email'      => $email,
        'phone'      => $phone,
        'address'    => $address,
        'zip'        => $zip,
        'city'       => $city,
        'event_id'   => $event_id,
        'event_date' => $event_date,
        'notes'      => $notes,
    ) );

    $success_msg = $is_trial
        ? 'Vielen Dank! Deine Anfrage fuer ein Probetraining wurde erfolgreich uebermittelt. Wir melden uns zeitnah bei dir.'
        : 'Vielen Dank! Ihre Anmeldung wurde erfolgreich gespeichert.';

    wp_send_json_success( array(
        'message'         => $success_msg,
        'registration_id' => $new_id,
    ) );
}

// ---------------------------------------------
// AJAX: Event-Details laden
// ---------------------------------------------
add_action( 'wp_ajax_nopriv_tc_get_event_details', 'tc_get_event_details_ajax' );
add_action( 'wp_ajax_tc_get_event_details',        'tc_get_event_details_ajax' );

function tc_get_event_details_ajax() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

    $event_id = absint( $_POST['event_id'] ?? 0 );
    if ( ! $event_id || get_post_type( $event_id ) !== 'training_event' ) {
        wp_send_json_error( array( 'message' => 'Event nicht gefunden.' ) );
    }

    $event             = get_post( $event_id );
    $leadership        = get_field( 'seminar_leadership', $event_id );
    $location          = get_field( 'location',           $event_id );
    $start_date        = get_field( 'start_date',         $event_id );
    $start_time        = get_field( 'start_time',         $event_id );
    $more_days         = get_field( 'more_days',          $event_id );
    $end_date          = get_field( 'end_date',           $event_id );
    $is_recurring      = get_field( 'is_recurring',       $event_id );
    $recurring_weekday = get_field( 'recurring_weekday',  $event_id );
    $recurring_until   = get_field( 'recurring_until',    $event_id );
    $track_p           = get_field( 'track_participants', $event_id );
    $max_p             = get_field( 'participants',       $event_id );

    $dates = array(); $is_multiday = false; $is_recurring_event = false;

    if ( $more_days && $end_date ) {
        $is_multiday = true;
        $cur = new DateTime( $start_date );
        $end = new DateTime( $end_date );
        while ( $cur <= $end ) { $dates[] = $cur->format('Y-m-d'); $cur->modify('+1 day'); }
    } elseif ( $is_recurring && $recurring_weekday !== '' && $recurring_until ) {
        $is_recurring_event = true;
        $target   = (int) $recurring_weekday;
        $cur      = new DateTime( $start_date );
        $until_dt = new DateTime( $recurring_until . ' 23:59:59' );
        $diff     = ( $target - (int) $cur->format('w') + 7 ) % 7;
        if ( $diff > 0 ) $cur->modify( "+{$diff} days" );
        $limit = 0;
        while ( $cur <= $until_dt && $limit < 260 ) {
            $dates[] = $cur->format('Y-m-d');
            $cur->modify('+7 days');
            $limit++;
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
