<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_nopriv_tc_submit_waitlist', 'tc_handle_waitlist_submission' );
add_action( 'wp_ajax_tc_submit_waitlist',        'tc_handle_waitlist_submission' );

function tc_handle_waitlist_submission() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

    global $wpdb;
    $table = $wpdb->prefix . 'tc_registrations';

    $firstname  = sanitize_text_field(    $_POST['firstname']  ?? '' );
    $lastname   = sanitize_text_field(    $_POST['lastname']   ?? '' );
    $email      = sanitize_email(         $_POST['email']      ?? '' );
    $phone      = sanitize_text_field(    $_POST['phone']      ?? '' );
    $event_id   = absint(                 $_POST['event_id']   ?? 0  );
    $event_date = sanitize_text_field(    $_POST['event_date'] ?? '' );
    $notes      = sanitize_textarea_field($_POST['notes']      ?? '' );

    if ( ! $firstname || ! $lastname || ! $email || ! is_email( $email ) || ! $event_id ) {
        wp_send_json_error( [ 'message' => 'Bitte füllen Sie alle erforderlichen Felder aus.' ] );
    }

    if ( get_post_type( $event_id ) !== 'time_event' ) {
        wp_send_json_error( [ 'message' => 'Diese Veranstaltung existiert nicht.' ] );
    }

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$table} WHERE email = %s AND event_id = %d",
        $email, $event_id
    ) );
    if ( $existing ) {
        wp_send_json_error( [ 'message' => 'Sie sind bereits für diese Veranstaltung angemeldet oder auf der Warteliste.' ] );
    }

    $inserted = $wpdb->insert(
        $table,
        [
            'firstname'   => $firstname,
            'lastname'    => $lastname,
            'email'       => $email,
            'phone'       => $phone,
            'address'     => '',
            'zip'         => '',
            'city'        => '',
            'event_id'    => $event_id,
            'event_date'  => $event_date ?: null,
            'status'      => 'waitlist',
            'notes'       => $notes,
            'created_at'  => time(),
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' ]
    );

    if ( $inserted === false ) {
        wp_send_json_error( [ 'message' => 'Fehler beim Speichern.' ] );
    }

    tc_send_waitlist_confirmation_mail( $wpdb->insert_id );

    wp_send_json_success( [
        'message' => 'Sie wurden erfolgreich auf die Warteliste eingetragen. Wir benachrichtigen Sie, sobald ein Platz frei wird.',
    ] );
}

add_action( 'tc_registration_cancelled', function ( $cancelled_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'tc_registrations';

    $reg = tc_get_registration( $cancelled_id );
    if ( ! $reg ) return;

    $waitlisted = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE event_id = %d AND status = 'waitlist'
         ORDER BY created_at ASC
         LIMIT 1",
        $reg['event_id']
    ), ARRAY_A );

    if ( ! $waitlisted ) return;

    $wpdb->update(
        $table,
        [ 'status' => 'pending' ],
        [ 'id'     => $waitlisted['id'] ]
    );

    tc_send_waitlist_slot_available_mail( $waitlisted );
}, 10, 1 );

function tc_send_waitlist_confirmation_mail( $registration_id ) {
    $reg = tc_get_registration( $registration_id );
    if ( ! $reg ) return;

    $event_id   = (int) $reg['event_id'];
    $event_date = $reg['event_date'] ?? '';
    $headers    = [ 'Content-Type: text/html; charset=UTF-8' ];

    $subject_tpl = tc_get_mail_setting( 'waitlist', 'subject' );
    $subject     = tc_resolve_placeholders( $subject_tpl, $reg, $event_id, $event_date );
    $msg         = tc_build_mail_body( 'waitlist', $reg, $event_id, $event_date );

    wp_mail( $reg['email'], $subject, $msg, $headers );
}

function tc_send_waitlist_slot_available_mail( $reg ) {
    $event_id   = (int) $reg['event_id'];
    $event_date = $reg['event_date'] ?? '';
    $headers    = [ 'Content-Type: text/html; charset=UTF-8' ];

    $subject_tpl = tc_get_mail_setting( 'waitlist_slot', 'subject' );
    $subject     = tc_resolve_placeholders( $subject_tpl, $reg, $event_id, $event_date );
    $msg         = tc_build_mail_body( 'waitlist_slot', $reg, $event_id, $event_date );

    wp_mail( $reg['email'], $subject, $msg, $headers );
}
