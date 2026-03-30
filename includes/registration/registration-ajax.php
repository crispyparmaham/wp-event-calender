<?php
defined( 'ABSPATH' ) || exit;

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

    $is_trial = in_array( get_field( 'event_price_type', $event_id ) ?: 'fixed', array( 'request', 'free' ), true );

    $track_p = get_field( 'registration_limit', $event_id );
    $max_p   = get_field( 'max_participants',   $event_id );
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
            ? tc_get_setting( 'label_duplicate_msg_trial', 'Du bist für diese Veranstaltung bereits angemeldet.' )
            : tc_get_setting( 'label_duplicate_msg',       'Sie sind bereits für diese Veranstaltung angemeldet.' );
        // label_form_duplicate is the canonical new key; fall back to legacy keys above
        $dup_override = tc_get_setting( 'label_form_duplicate', '' );
        if ( $dup_override ) {
            $dup_msg = $dup_override;
        }
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
        wp_send_json_error( array( 'message' => tc_get_setting( 'label_form_error', 'Es ist ein Fehler aufgetreten. Bitte versuche es erneut.' ) ) );
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

    // label_form_success is the canonical new key; legacy keys remain for backward compat
    $success_msg_new = tc_get_setting( 'label_form_success', '' );
    if ( $success_msg_new ) {
        $success_msg = $success_msg_new;
    } else {
        $success_msg = $is_trial
            ? tc_get_setting( 'label_success_msg_trial', 'Vielen Dank für deine Anfrage. Wir melden uns zeitnah bei dir.' )
            : tc_get_setting( 'label_success_msg',       'Vielen Dank! Ihre Anmeldung wurde erfolgreich gespeichert.' );
    }

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

    $leadership = $fields['event_host']         ?? null;
    $location   = $fields['location']           ?? null;
    $track_p    = $fields['registration_limit'] ?? null;
    $max_p      = $fields['max_participants']   ?? null;
    $date_type  = $fields['event_date_type']    ?? 'single';

    $dates = array(); $is_multiday = false; $is_recurring_event = false;
    $start_date = null; $start_time = null;

    if ( $date_type === 'recurring' ) {
        $start_time        = $fields['recurring_time_start'] ?? null;
        $recurring_weekday = $fields['recurring_weekday']    ?? null;

        if ( $recurring_weekday !== '' && $recurring_weekday !== null ) {
            $is_recurring_event = true;
            $start_date         = wp_date( 'Y-m-d' );
            // Kein dates-Array — recurring Events benötigen kein Termin-Dropdown.
            // Die Anmeldung gilt für das gesamte Event, nicht einen einzelnen Termin.
        }
    } else {
        $rows = get_field( 'event_dates', $event_id ) ?: array();
        foreach ( $rows as $row ) {
            if ( ! empty( $row['date_start'] ) ) {
                $dates[] = $row['date_start'];
            }
        }
        $start_date  = $rows[0]['date_start'] ?? null;
        $start_time  = $rows[0]['time_start'] ?? null;
        $is_multiday = (bool) array_filter( $rows, fn( $r ) => ! empty( $r['date_end'] ) );
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
