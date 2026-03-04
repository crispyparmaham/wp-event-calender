<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Tabelle anlegen / aktualisieren
//
// FIX: Kein früher Return mehr — dbDelta läuft
//      immer durch und ergänzt fehlende Spalten.
//      Wird sowohl vom Activation Hook als auch
//      von admin_init aufgerufen (Fallback für
//      manuelle Updates ohne Re-Aktivierung).
// ─────────────────────────────────────────────
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
        company varchar(255),
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

// ─────────────────────────────────────────────
// Helper: Alle Anmeldungen abrufen
// ─────────────────────────────────────────────
function tc_get_all_registrations() {
    global $wpdb;
    $table_name    = $wpdb->prefix . 'tc_registrations';
    $registrations = $wpdb->get_results(
        "SELECT * FROM {$table_name} ORDER BY created_at DESC",
        ARRAY_A
    );
    return $registrations ?: array();
}

// ─────────────────────────────────────────────
// Helper: Einzelne Anmeldung abrufen
// ─────────────────────────────────────────────
function tc_get_registration( $registration_id ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tc_registrations';
    return $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $registration_id ),
        ARRAY_A
    );
}

// ─────────────────────────────────────────────
// Helper: Anmeldung aktualisieren
// ─────────────────────────────────────────────
function tc_update_registration( $registration_id, $data ) {
    global $wpdb;
    $table_name     = $wpdb->prefix . 'tc_registrations';
    $allowed_fields = array( 'firstname', 'lastname', 'email', 'phone', 'company', 'event_id', 'event_date', 'status', 'notes' );
    $update_data    = array();

    foreach ( $data as $key => $value ) {
        if ( in_array( $key, $allowed_fields, true ) ) {
            $update_data[ $key ] = $value;
        }
    }

    if ( empty( $update_data ) ) return false;

    return $wpdb->update(
        $table_name,
        $update_data,
        array( 'id' => $registration_id ),
        null,
        array( '%d' )
    );
}

// ─────────────────────────────────────────────
// Helper: Anmeldung löschen
// ─────────────────────────────────────────────
function tc_delete_registration( $registration_id ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tc_registrations';
    return $wpdb->delete(
        $table_name,
        array( 'id' => $registration_id ),
        array( '%d' )
    );
}

// ─────────────────────────────────────────────
// AJAX: Anmeldung erstellen
// ─────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_tc_submit_registration', 'tc_handle_registration_submission' );
add_action( 'wp_ajax_tc_submit_registration',        'tc_handle_registration_submission' );

function tc_handle_registration_submission() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'tc_registrations';

    $firstname  = sanitize_text_field( $_POST['firstname']  ?? '' );
    $lastname   = sanitize_text_field( $_POST['lastname']   ?? '' );
    $email      = sanitize_email(      $_POST['email']      ?? '' );
    $phone      = sanitize_text_field( $_POST['phone']      ?? '' );
    $company    = sanitize_text_field( $_POST['company']    ?? '' );
    $event_id   = absint(              $_POST['event_id']   ?? 0  );
    $event_date = sanitize_text_field( $_POST['event_date'] ?? '' );
    $notes      = sanitize_textarea_field( $_POST['notes'] ?? '' );

    // Pflichtfelder prüfen
    if ( ! $firstname || ! $lastname || ! $email || ! is_email( $email ) || ! $event_id ) {
        wp_send_json_error( array(
            'message' => 'Bitte füllen Sie alle erforderlichen Felder aus.',
        ) );
    }

    // Event-Existenz prüfen
    if ( get_post_type( $event_id ) !== 'training_event' ) {
        wp_send_json_error( array(
            'message' => 'Diese Veranstaltung existiert nicht.',
        ) );
    }

    // Kapazität prüfen
    $track_participants = get_field( 'track_participants', $event_id );
    $max_participants   = get_field( 'participants',       $event_id );

    if ( $track_participants && $max_participants ) {
        $current = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE event_id = %d AND status IN ('pending','confirmed')",
            $event_id
        ) );
        if ( $current >= (int) $max_participants ) {
            wp_send_json_error( array(
                'message' => 'Leider ist dieser Termin bereits ausgebucht.',
            ) );
        }
    }

    // Doppelte Anmeldung prüfen
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE email = %s AND event_id = %d",
        $email,
        $event_id
    ) );
    if ( $existing ) {
        wp_send_json_error( array(
            'message' => 'Sie sind bereits für diese Veranstaltung angemeldet.',
        ) );
    }

    // Anmeldung speichern
    $inserted = $wpdb->insert(
        $table_name,
        array(
            'firstname'  => $firstname,
            'lastname'   => $lastname,
            'email'      => $email,
            'phone'      => $phone,
            'company'    => $company,
            'event_id'   => $event_id,
            'event_date' => $event_date ?: null,
            'status'     => 'pending',
            'notes'      => $notes,
            'created_at' => time(),
        ),
        array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
    );

    // FIX: $wpdb->last_error mitgeben damit man den echten DB-Fehler sieht
    if ( $inserted === false ) {
        wp_send_json_error( array(
            'message' => 'Fehler beim Speichern der Anmeldung.'
                         . ( defined( 'WP_DEBUG' ) && WP_DEBUG
                             ? ' DB: ' . $wpdb->last_error
                             : '' ),
        ) );
    }

    $new_id = $wpdb->insert_id;

    do_action( 'tc_registration_submitted', $new_id, array(
        'firstname'  => $firstname,
        'lastname'   => $lastname,
        'email'      => $email,
        'phone'      => $phone,
        'company'    => $company,
        'event_id'   => $event_id,
        'event_date' => $event_date,
        'notes'      => $notes,
    ) );

    wp_send_json_success( array(
        'message'         => 'Vielen Dank! Ihre Anmeldung wurde erfolgreich gespeichert.',
        'registration_id' => $new_id,
    ) );
}

// ─────────────────────────────────────────────
// AJAX: Event-Details laden
// ─────────────────────────────────────────────
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

    $dates              = array();
    $is_multiday        = false;
    $is_recurring_event = false;

    if ( $more_days && $end_date ) {
        $is_multiday = true;
        $cur         = new DateTime( $start_date );
        $end_dt      = new DateTime( $end_date );
        while ( $cur <= $end_dt ) {
            $dates[] = $cur->format( 'Y-m-d' );
            $cur->modify( '+1 day' );
        }
    } elseif ( $is_recurring && $recurring_weekday !== '' && $recurring_until ) {
        $is_recurring_event = true;
        $target             = (int) $recurring_weekday; // 0=So … 6=Sa, identisch zu ajax.php
        $cur                = new DateTime( $start_date );
        $until_dt           = new DateTime( $recurring_until . ' 23:59:59' );
        $diff               = ( $target - (int) $cur->format('w') + 7 ) % 7;
        if ( $diff > 0 ) $cur->modify( "+{$diff} days" );

        $limit = 0;
        while ( $cur <= $until_dt && $limit < 260 ) {
            $dates[] = $cur->format( 'Y-m-d' );
            $cur->modify( '+7 days' );
            $limit++;
        }
    } else {
        $dates[] = $start_date;
    }

    $current_reg = 0;
    $is_full     = false;

    if ( $track_p && $max_p ) {
        global $wpdb;
        $table_name  = $wpdb->prefix . 'tc_registrations';
        $current_reg = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE event_id = %d AND status IN ('pending','confirmed')",
            $event_id
        ) );
        $is_full = $current_reg >= (int) $max_p;
    }

    wp_send_json_success( array(
        'title'                 => $event->post_title,
        'leadership'            => $leadership ?: 'Nicht angegeben',
        'location'              => $location   ?: 'Nicht angegeben',
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

// ─────────────────────────────────────────────
// Bestätigungsmail
// ─────────────────────────────────────────────
add_action( 'tc_registration_submitted', function ( $registration_id, $data ) {
    $event       = get_post( $data['event_id'] );
    $event_title = $event ? $event->post_title : 'Veranstaltung';
    $blogname    = get_option( 'blogname' );
    $headers     = array( 'Content-Type: text/html; charset=UTF-8' );

    // Mail an Teilnehmer
    $msg  = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">';
    $msg .= '<div style="max-width:600px;margin:0 auto;padding:20px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;">';
    $msg .= '<h2 style="color:#0066cc;margin-top:0;">Vielen Dank für Ihre Anmeldung!</h2>';
    $msg .= '<p>Hallo ' . esc_html( $data['firstname'] ) . ' ' . esc_html( $data['lastname'] ) . ',</p>';
    $msg .= '<p>Ihre Anmeldung für <strong>' . esc_html( $event_title ) . '</strong> wurde gespeichert.</p>';
    if ( ! empty( $data['event_date'] ) ) {
        $d    = DateTime::createFromFormat( 'Y-m-d', $data['event_date'] );
        $msg .= '<p><strong>Gewähltes Datum:</strong> ' . ( $d ? $d->format('d.m.Y') : esc_html( $data['event_date'] ) ) . '</p>';
    }
    $msg .= '<p>Wir melden uns, sobald Ihre Anmeldung bestätigt wurde.</p>';
    $msg .= '<hr style="border:none;border-top:1px solid #ddd;margin:20px 0;">';
    $msg .= '<p style="font-size:.9em;color:#666;">Mit freundlichen Grüßen<br><strong>' . esc_html( $blogname ) . '</strong></p>';
    $msg .= '</div></body></html>';

    wp_mail( $data['email'], 'Anmeldungsbestätigung: ' . $event_title, $msg, $headers );

    // Mail an Admin
    $admin_email = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );
    if ( $admin_email === $data['email'] ) return;

    $amsg  = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">';
    $amsg .= '<div style="max-width:600px;margin:0 auto;padding:20px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;">';
    $amsg .= '<h2 style="color:#0066cc;margin-top:0;">Neue Anmeldung eingegangen</h2>';
    $amsg .= '<p><strong>Veranstaltung:</strong> ' . esc_html( $event_title ) . '</p>';
    $amsg .= '<p><strong>Name:</strong> ' . esc_html( $data['firstname'] . ' ' . $data['lastname'] ) . '</p>';
    $amsg .= '<p><strong>E-Mail:</strong> ' . esc_html( $data['email'] ) . '</p>';
    if ( ! empty( $data['phone'] ) )   $amsg .= '<p><strong>Telefon:</strong> '     . esc_html( $data['phone'] )   . '</p>';
    if ( ! empty( $data['company'] ) ) $amsg .= '<p><strong>Unternehmen:</strong> ' . esc_html( $data['company'] ) . '</p>';
    if ( ! empty( $data['event_date'] ) ) {
        $d     = DateTime::createFromFormat( 'Y-m-d', $data['event_date'] );
        $amsg .= '<p><strong>Datum:</strong> ' . ( $d ? $d->format('d.m.Y') : esc_html( $data['event_date'] ) ) . '</p>';
    }
    if ( ! empty( $data['notes'] ) ) {
        $amsg .= '<p><strong>Notizen:</strong></p><p style="background:#f0f0f0;padding:10px;border-radius:4px;">' . nl2br( esc_html( $data['notes'] ) ) . '</p>';
    }
    $amsg .= '<hr style="border:none;border-top:1px solid #ddd;margin:20px 0;">';
    $amsg .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=training-registrations' ) ) . '" style="background:#0066cc;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;">Zur Anmeldungsübersicht</a></p>';
    $amsg .= '</div></body></html>';

    wp_mail( $admin_email, 'Neue Anmeldung: ' . $event_title . ' – ' . $data['firstname'] . ' ' . $data['lastname'], $amsg, $headers );
}, 10, 2 );