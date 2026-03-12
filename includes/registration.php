<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// 1. Custom Post Type für Anmeldungen
// ─────────────────────────────────────────────
add_action( 'init', function () {
    register_post_type( 'training_registration', array(
        'labels' => array(
            'name'          => 'Anmeldungen',
            'singular_name' => 'Anmeldung',
            'add_new'       => 'Anmeldung hinzufügen',
            'add_new_item'  => 'Neue Anmeldung hinzufügen',
            'edit_item'     => 'Anmeldung bearbeiten',
            'all_items'     => 'Alle Anmeldungen',
            'search_items'  => 'Anmeldungen suchen',
            'not_found'     => 'Keine Anmeldungen gefunden.',
        ),
        'public'            => false,
        'show_in_menu'      => true,
        'menu_icon'         => 'dashicons-clipboard',
        'show_ui'           => true,
        'supports'          => array( 'title' ),
        'has_archive'       => false,
        'show_in_rest'      => true,
        'capability_type'   => 'post',
    ) );

    // Spalten in der Admin-Liste definieren
    add_filter( 'manage_training_registration_posts_columns', function ( $columns ) {
        unset( $columns['date'] );
        return array_merge( $columns, array(
            'firstname'  => 'Vorname',
            'lastname'   => 'Nachname',
            'email'      => 'E-Mail',
            'phone'      => 'Telefon',
            'event'      => 'Veranstaltung',
            'status'     => 'Status',
            'registered' => 'Angemeldet am',
        ) );
    } );

    // Spalten-Inhalte
    add_action( 'manage_training_registration_posts_custom_column', function ( $column, $post_id ) {
        switch ( $column ) {
            case 'firstname':
                echo esc_html( get_post_meta( $post_id, '_tc_firstname', true ) );
                break;
            case 'lastname':
                echo esc_html( get_post_meta( $post_id, '_tc_lastname', true ) );
                break;
            case 'email':
                echo esc_html( get_post_meta( $post_id, '_tc_email', true ) );
                break;
            case 'phone':
                echo esc_html( get_post_meta( $post_id, '_tc_phone', true ) );
                break;
            case 'event':
                $event_id = get_post_meta( $post_id, '_tc_event_id', true );
                if ( $event_id ) {
                    echo '<a href="' . esc_url( get_edit_post_link( $event_id ) ) . '">';
                    echo esc_html( get_the_title( $event_id ) );
                    echo '</a>';
                }
                break;
            case 'status':
                $status = get_post_meta( $post_id, '_tc_status', true );
                $status_label = $status === 'confirmed' ? 'Bestätigt' : 'Ausstehend';
                echo '<span class="tc-status-badge tc-status-' . esc_attr( $status ) . '">' . esc_html( $status_label ) . '</span>';
                break;
            case 'registered':
                $post = get_post( $post_id );
                echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $post->post_date ) ) );
                break;
        }
    }, 10, 2 );
} );

// ─────────────────────────────────────────────
// 2. ACF Feldgruppe für Anmeldungen
// ─────────────────────────────────────────────
add_action( 'acf/include_fields', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( array(
        'key'    => 'group_training_registration',
        'title'  => 'Anmeldungsdetails',
        'fields' => array(
            array(
                'key'           => 'field_tc_reg_firstname',
                'label'         => 'Vorname',
                'name'          => 'tc_firstname',
                'type'          => 'text',
                'required'      => 1,
            ),
            array(
                'key'           => 'field_tc_reg_lastname',
                'label'         => 'Nachname',
                'name'          => 'tc_lastname',
                'type'          => 'text',
                'required'      => 1,
            ),
            array(
                'key'           => 'field_tc_reg_email',
                'label'         => 'E-Mail',
                'name'          => 'tc_email',
                'type'          => 'email',
                'required'      => 1,
            ),
            array(
                'key'           => 'field_tc_reg_phone',
                'label'         => 'Telefon',
                'name'          => 'tc_phone',
                'type'          => 'text',
            ),
            array(
                'key'           => 'field_tc_reg_company',
                'label'         => 'Unternehmen',
                'name'          => 'tc_company',
                'type'          => 'text',
            ),
            array(
                'key'           => 'field_tc_reg_event',
                'label'         => 'Veranstaltung',
                'name'          => 'tc_event_id',
                'type'          => 'post_object',
                'post_type'     => array( 'training_event' ),
                'required'      => 1,
                'return_format' => 'id',
            ),
            array(
                'key'           => 'field_tc_reg_status',
                'label'         => 'Anmeldestatus',
                'name'          => 'tc_status',
                'type'          => 'select',
                'choices'       => array(
                    'pending'   => 'Ausstehend',
                    'confirmed' => 'Bestätigt',
                    'cancelled' => 'Storniert',
                ),
                'default_value' => 'pending',
                'return_format' => 'value',
            ),
            array(
                'key'           => 'field_tc_reg_notes',
                'label'         => 'Notizen',
                'name'          => 'tc_notes',
                'type'          => 'textarea',
                'rows'          => 3,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'training_registration',
                ),
            ),
        ),
    ) );
} );

// ─────────────────────────────────────────────
// 3. AJAX: Anmeldung erstellen
// ─────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_tc_submit_registration', 'tc_handle_registration_submission' );
add_action( 'wp_ajax_tc_submit_registration', 'tc_handle_registration_submission' );

function tc_handle_registration_submission() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

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
    $existing = new WP_Query( array(
        'post_type'     => 'training_registration',
        'meta_query'    => array(
            'relation' => 'AND',
            array(
                'key'   => '_tc_email',
                'value' => $email,
            ),
            array(
                'key'   => '_tc_event_id',
                'value' => $event_id,
            ),
        ),
        'posts_per_page' => 1,
    ) );

    if ( $existing->found_posts > 0 ) {
        wp_send_json_error( array(
            'message' => 'Sie sind bereits für diese Veranstaltung angemeldet.',
        ) );
    }

    // Anmeldung erstellen
    $post_title = $firstname . ' ' . $lastname . ' - ' . get_the_title( $event_id );
    
    $registration_id = wp_insert_post( array(
        'post_type'   => 'training_registration',
        'post_title'  => $post_title,
        'post_status' => 'publish',
    ) );

    if ( is_wp_error( $registration_id ) ) {
        wp_send_json_error( array(
            'message' => 'Es gab einen Fehler beim speichern der Anmeldung.',
        ) );
    }

    // Metadaten speichern
    update_post_meta( $registration_id, '_tc_firstname', $firstname );
    update_post_meta( $registration_id, '_tc_lastname', $lastname );
    update_post_meta( $registration_id, '_tc_email', $email );
    update_post_meta( $registration_id, '_tc_phone', $phone );
    update_post_meta( $registration_id, '_tc_company', $company );
    update_post_meta( $registration_id, '_tc_event_id', $event_id );
    update_post_meta( $registration_id, '_tc_status', 'pending' );

    if ( $notes ) {
        update_post_meta( $registration_id, '_tc_notes', $notes );
    }

    // Hook für externe Verarbeitung
    do_action( 'tc_registration_submitted', $registration_id, array(
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
        'registration_id'  => $registration_id,
    ) );
}

// ─────────────────────────────────────────────
// 4. AJAX: Event-Details laden
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
// 5. Registrierungs-Bestätigungsmail
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
