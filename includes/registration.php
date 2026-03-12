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

    $allowed_fields = array( 'firstname', 'lastname', 'email', 'phone', 'company', 'event_id', 'status', 'notes' );
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
            'status'     => 'pending',
            'notes'      => $notes,
            'created_at' => time(),
        ),
        array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d' )
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

    wp_send_json_success( array(
        'title'      => $event->post_title,
        'leadership' => $leadership ?: 'Nicht angegeben',
        'location'   => $location ?: 'Nicht angegeben',
        'start_date' => $start_date ?: 'Nicht angegeben',
        'start_time' => $start_time ?: '',
    ) );
}

// ─────────────────────────────────────────────
// Registrierungs-Bestätigungsmail
// ─────────────────────────────────────────────
add_action( 'tc_registration_submitted', function ( $registration_id, $data ) {
    $event = get_post( $data['event_id'] );
    $event_title = $event ? $event->post_title : 'Veranstaltung';
    
    $to      = $data['email'];
    $subject = 'Anmeldungsbestätigung: ' . $event_title;
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    $message = '<h2>Vielen Dank für Ihre Anmeldung!</h2>';
    $message .= '<p>Hallo ' . esc_html( $data['firstname'] ) . ' ' . esc_html( $data['lastname'] ) . ',</p>';
    $message .= '<p>Ihre Anmeldung für die Veranstaltung <strong>' . esc_html( $event_title ) . '</strong> wurde erfolgreich gespeichert.</p>';
    $message .= '<p>Wir werden Sie kontaktieren, sobald Ihre Anmeldung bestätigt wurde.</p>';
    $message .= '<p>Mit freundlichen Grüßen<br>Ihr Team</p>';

    wp_mail( $to, $subject, $message, $headers );
}, 10, 2 );
