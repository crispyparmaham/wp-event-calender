<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Datenbankversion check
// ─────────────────────────────────────────────
add_action( 'admin_init', 'tc_create_registrations_table' );

function tc_create_registrations_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tc_registrations';
    $charset_collate = $wpdb->get_charset_collate();

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
        return;
    }

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        firstname varchar(255) NOT NULL,
        lastname varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(20),
        company varchar(255),
        event_id bigint(20) NOT NULL,
        event_date date,
        status varchar(20) DEFAULT 'pending',
        notes longtext,
        created_at bigint(20) NOT NULL,
        PRIMARY KEY (id),
        KEY email (email),
        KEY event_id (event_id),
        KEY status (status)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// ─────────────────────────────────────────────
// Helper: Alle Anmeldungen abrufen
// ─────────────────────────────────────────────
function tc_get_all_registrations() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tc_registrations';

    $registrations = $wpdb->get_results(
        "SELECT * FROM $table_name ORDER BY created_at DESC",
        ARRAY_A
    );

    return $registrations ? $registrations : array();
}

// ─────────────────────────────────────────────
// Helper: Einzelne Anmeldung abrufen
// ─────────────────────────────────────────────
function tc_get_registration( $registration_id ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tc_registrations';

    return $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $registration_id ),
        ARRAY_A
    );
}

// ─────────────────────────────────────────────
// Helper: Anmeldung aktualisieren
// ─────────────────────────────────────────────
function tc_update_registration( $registration_id, $data ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tc_registrations';

    $allowed_fields = array( 'firstname', 'lastname', 'email', 'phone', 'company', 'event_id', 'event_date', 'status', 'notes' );
    $update_data = array();

    foreach ( $data as $key => $value ) {
        if ( in_array( $key, $allowed_fields, true ) ) {
            $update_data[ $key ] = $value;
        }
    }

    if ( empty( $update_data ) ) {
        return false;
    }

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
add_action( 'wp_ajax_tc_submit_registration', 'tc_handle_registration_submission' );

function tc_handle_registration_submission() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'tc_registrations';

    // Daten validieren und bereinigen
    $firstname = isset( $_POST['firstname'] ) ? sanitize_text_field( $_POST['firstname'] ) : '';
    $lastname  = isset( $_POST['lastname'] ) ? sanitize_text_field( $_POST['lastname'] ) : '';
    $email     = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    $phone     = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
    $company   = isset( $_POST['company'] ) ? sanitize_text_field( $_POST['company'] ) : '';
    $event_id  = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
    $event_date = isset( $_POST['event_date'] ) ? sanitize_text_field( $_POST['event_date'] ) : '';
    $notes     = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';

    // Validierung
    if ( ! $firstname || ! $lastname || ! $email || ! is_email( $email ) || ! $event_id ) {
        wp_send_json_error( array(
            'message' => 'Bitte füllen Sie alle erforderlichen Felder aus.',
        ) );
    }

    // Prüfen ob Event existiert
    if ( get_post_type( $event_id ) !== 'training_event' ) {
        wp_send_json_error( array(
            'message' => 'Diese Veranstaltung existiert nicht.',
        ) );
    }

    // Kapazität prüfen
    $track_participants = get_field( 'track_participants', $event_id );
    $max_participants = get_field( 'participants', $event_id );
    
    if ( $track_participants && $max_participants ) {
        $current_registrations = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE event_id = %d AND status IN ('pending', 'confirmed')",
            $event_id
        ) );

        if ( $current_registrations >= $max_participants ) {
            wp_send_json_error( array(
                'message' => 'Leider ist dieser Termin bereits ausgebucht.',
            ) );
        }
    }

    // Doppelten Antrag prüfen
    $existing = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM $table_name WHERE email = %s AND event_id = %d",
        $email,
        $event_id
    ) );

    if ( $existing ) {
        wp_send_json_error( array(
            'message' => 'Sie sind bereits für diese Veranstaltung angemeldet.',
        ) );
    }

    // Anmeldung erstellen
    $registration_id = $wpdb->insert(
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

    if ( ! $registration_id ) {
        wp_send_json_error( array(
            'message' => 'Es gab einen Fehler beim speichern der Anmeldung.',
        ) );
    }

    // Hook für externe Verarbeitung
    do_action( 'tc_registration_submitted', $wpdb->insert_id, array(
        'firstname' => $firstname,
        'lastname'  => $lastname,
        'email'     => $email,
        'phone'     => $phone,
        'company'   => $company,
        'event_id'  => $event_id,
        'event_date' => $event_date,
        'notes'     => $notes,
    ) );

    wp_send_json_success( array(
        'message'          => 'Vielen Dank! Ihre Anmeldung wurde erfolgreich gespeichert.',
        'registration_id'  => $wpdb->insert_id,
    ) );
}

// ─────────────────────────────────────────────
// AJAX: Event-Details laden
// ─────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_tc_get_event_details', 'tc_get_event_details_ajax' );
add_action( 'wp_ajax_tc_get_event_details', 'tc_get_event_details_ajax' );

function tc_get_event_details_ajax() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

    $event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;

    if ( ! $event_id || get_post_type( $event_id ) !== 'training_event' ) {
        wp_send_json_error( array( 'message' => 'Event nicht gefunden.' ) );
    }

    $event = get_post( $event_id );
    $leadership = get_field( 'seminar_leadership', $event_id );
    $location = get_field( 'location', $event_id );
    $start_date = get_field( 'start_date', $event_id );
    $start_time = get_field( 'start_time', $event_id );
    $more_days = get_field( 'more_days', $event_id );
    $end_date = get_field( 'end_date', $event_id );
    $track_participants = get_field( 'track_participants', $event_id );
    $max_participants = get_field( 'participants', $event_id );

    // Datumsarray für mehrtägige Events generieren
    $dates = array();
    if ( $more_days && $end_date ) {
        $start_dt = new DateTime( $start_date );
        $end_dt = new DateTime( $end_date );
        $current = clone $start_dt;

        while ( $current <= $end_dt ) {
            $dates[] = $current->format( 'Y-m-d' );
            $current->modify( '+1 day' );
        }
    }

    // Aktueller Anmeldungsstand
    $current_registrations = 0;
    $is_full = false;
    if ( $track_participants && $max_participants ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tc_registrations';
        $current_registrations = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE event_id = %d AND status IN ('pending', 'confirmed')",
            $event_id
        ) );
        $is_full = $current_registrations >= $max_participants;
    }

    wp_send_json_success( array(
        'title'       => $event->post_title,
        'leadership'  => $leadership ?: 'Nicht angegeben',
        'location'    => $location ?: 'Nicht angegeben',
        'start_date'  => $start_date ?: 'Nicht angegeben',
        'start_time'  => $start_time ?: '',
        'is_multiday' => (bool) $more_days,
        'dates'       => $dates,
        'track_participants' => (bool) $track_participants,
        'max_participants'   => $max_participants ?: 0,
        'current_registrations' => $current_registrations,
        'is_full'    => $is_full,
    ) );
}

// ─────────────────────────────────────────────
// Registrierungs-Bestätigungsmail
// ─────────────────────────────────────────────
add_action( 'tc_registration_submitted', function ( $registration_id, $data ) {
    $event = get_post( $data['event_id'] );
    $event_title = $event ? $event->post_title : 'Veranstaltung';
    
    // Mail an Teilnehmer
    $to_customer      = $data['email'];
    $blogname = get_option( 'blogname' );
    
    $subject_customer = 'Anmeldungsbestätigung: ' . $event_title;
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    $message_customer = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
    $message_customer .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px;">';
    $message_customer .= '<h2 style="color: #0066cc; margin-top: 0;">Vielen Dank für Ihre Anmeldung!</h2>';
    $message_customer .= '<p>Hallo ' . esc_html( $data['firstname'] ) . ' ' . esc_html( $data['lastname'] ) . ',</p>';
    $message_customer .= '<p>Ihre Anmeldung für die Veranstaltung <strong>' . esc_html( $event_title ) . '</strong> wurde erfolgreich gespeichert.</p>';
    
    // Wenn ein konkretes Datum vorhanden (für mehrtägige Events)
    if ( ! empty( $data['event_date'] ) ) {
        $event_date_obj = DateTime::createFromFormat( 'Y-m-d', $data['event_date'] );
        $formatted_date = $event_date_obj ? $event_date_obj->format( 'd.m.Y' ) : $data['event_date'];
        $message_customer .= '<p><strong>Gewähltes Datum:</strong> ' . esc_html( $formatted_date ) . '</p>';
    }
    
    $message_customer .= '<p>Wir werden Sie kontaktieren, sobald Ihre Anmeldung bestätigt wurde.</p>';
    $message_customer .= '<hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">';
    $message_customer .= '<p style="font-size: 0.9em; color: #666;">';
    $message_customer .= 'Mit freundlichen Grüßen<br>';
    $message_customer .= '<strong>' . esc_html( $blogname ) . '</strong>';
    $message_customer .= '</p>';
    $message_customer .= '</div></body></html>';

    wp_mail( $to_customer, $subject_customer, $message_customer, $headers );

    // Mail an Admin
    $admin_email = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );
    if ( $admin_email !== $to_customer ) {
        $subject_admin = 'Neue Anmeldung: ' . $event_title . ' - ' . $data['firstname'] . ' ' . $data['lastname'];
        
        $message_admin = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message_admin .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px;">';
        $message_admin .= '<h2 style="color: #0066cc; margin-top: 0;">Neue Anmeldung eingegangen</h2>';
        $message_admin .= '<p><strong>Veranstaltung:</strong> ' . esc_html( $event_title ) . '</p>';
        $message_admin .= '<p><strong>Name:</strong> ' . esc_html( $data['firstname'] . ' ' . $data['lastname'] ) . '</p>';
        $message_admin .= '<p><strong>E-Mail:</strong> ' . esc_html( $data['email'] ) . '</p>';
        if ( ! empty( $data['phone'] ) ) {
            $message_admin .= '<p><strong>Telefon:</strong> ' . esc_html( $data['phone'] ) . '</p>';
        }
        if ( ! empty( $data['company'] ) ) {
            $message_admin .= '<p><strong>Unternehmen:</strong> ' . esc_html( $data['company'] ) . '</p>';
        }
        if ( ! empty( $data['event_date'] ) ) {
            $event_date_obj = DateTime::createFromFormat( 'Y-m-d', $data['event_date'] );
            $formatted_date = $event_date_obj ? $event_date_obj->format( 'd.m.Y' ) : $data['event_date'];
            $message_admin .= '<p><strong>Gewähltes Datum:</strong> ' . esc_html( $formatted_date ) . '</p>';
        }
        if ( ! empty( $data['notes'] ) ) {
            $message_admin .= '<p><strong>Notizen:</strong></p><p style="background-color: #f0f0f0; padding: 10px; border-radius: 4px;">' . nl2br( esc_html( $data['notes'] ) ) . '</p>';
        }
        
        $message_admin .= '<hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">';
        $message_admin .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=training-registrations' ) ) . '" style="background-color: #0066cc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">Zur Anmeldungsübersicht</a></p>';
        $message_admin .= '</div></body></html>';

        wp_mail( $admin_email, $subject_admin, $message_admin, $headers );
    }
}, 10, 2 );
