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
    $info       = tc_get_event_mail_info( $event_id, $event_date );
    $blogname   = get_option( 'blogname' );
    $headers    = [ 'Content-Type: text/html; charset=UTF-8' ];

    $subject_tpl = tc_get_setting( 'mail_waitlist_subject', '{{anrede}} stehen auf der Warteliste – {{event_title}}' );
    $subject     = tc_resolve_placeholders( $subject_tpl, $reg, $event_id, $event_date );

    $body_tpl = tc_get_setting( 'mail_waitlist_body', '' );
    if ( $body_tpl ) {
        $resolved = tc_resolve_placeholders( $body_tpl, $reg, $event_id, $event_date );
        $msg = tc_mail_wrapper_open( $blogname ) . $resolved . tc_mail_wrapper_close();
    } else {
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#d97706;margin-top:0;">Sie stehen auf der Warteliste</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>vielen Dank für Ihr Interesse! Sie wurden auf die Warteliste für folgende Veranstaltung eingetragen:</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Wir benachrichtigen Sie umgehend, sobald ein Platz frei wird.</p>';
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    }

    wp_mail( $reg['email'], $subject, $msg, $headers );
}

function tc_send_waitlist_slot_available_mail( $reg ) {
    $event_id   = (int) $reg['event_id'];
    $event_date = $reg['event_date'] ?? '';
    $info       = tc_get_event_mail_info( $event_id, $event_date );
    $blogname   = get_option( 'blogname' );
    $headers    = [ 'Content-Type: text/html; charset=UTF-8' ];

    $subject_tpl = tc_get_setting( 'mail_waitlist_slot_subject', 'Ein Platz ist frei geworden – {{event_title}}' );
    $subject     = tc_resolve_placeholders( $subject_tpl, $reg, $event_id, $event_date );

    $body_tpl = tc_get_setting( 'mail_waitlist_slot_body', '' );
    if ( $body_tpl ) {
        $resolved = tc_resolve_placeholders( $body_tpl, $reg, $event_id, $event_date );
        $msg = tc_mail_wrapper_open( $blogname ) . $resolved . tc_mail_wrapper_close();
    } else {
        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#059669;margin-top:0;">Ein Platz ist frei geworden!</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>gute Neuigkeit! Für folgende Veranstaltung ist ein Platz frei geworden:</p>';
        $msg .= tc_event_info_block( $info );
        $msg .= '<p>Ihre Anfrage wird nun bearbeitet. Sie erhalten zeitnah eine Bestätigung von uns.</p>';
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    }

    wp_mail( $reg['email'], $subject, $msg, $headers );
}
