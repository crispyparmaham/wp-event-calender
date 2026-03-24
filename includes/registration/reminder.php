<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp', function () {
    if ( ! wp_next_scheduled( 'tc_daily_reminder_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'tc_daily_reminder_cron' );
    }
} );

function tc_deactivate_reminder_cron() {
    $timestamp = wp_next_scheduled( 'tc_daily_reminder_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'tc_daily_reminder_cron' );
    }
}

add_action( 'tc_daily_reminder_cron', 'tc_send_event_reminders' );

function tc_send_event_reminders() {
    if ( ! tc_get_setting( 'reminder_enabled', '0' ) ) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tc_registrations';

    $target_date = date( 'Y-m-d', strtotime( '+3 days' ) );

    $registrations = $wpdb->get_results(
        "SELECT * FROM {$table}
         WHERE status = 'confirmed'
           AND reminder_sent = 0",
        ARRAY_A
    );

    if ( empty( $registrations ) ) return;

    foreach ( $registrations as $reg ) {
        $event_id   = (int) $reg['event_id'];
        $event_date = $reg['event_date'] ?: get_field( 'start_date', $event_id );

        if ( $event_date !== $target_date ) continue;

        tc_send_reminder_mail( $reg );

        $wpdb->update(
            $table,
            [ 'reminder_sent' => 1 ],
            [ 'id'            => $reg['id'] ],
            [ '%d' ],
            [ '%d' ]
        );
    }
}

function tc_send_reminder_mail( $reg ) {
    $event_id   = (int) $reg['event_id'];
    $event_date = $reg['event_date'] ?? '';
    $info       = tc_get_event_mail_info( $event_id, $event_date );
    $trainer    = get_field( 'event_host', $event_id ) ?: '';
    $blogname   = get_option( 'blogname' );
    $headers    = [ 'Content-Type: text/html; charset=UTF-8' ];

    $subject_tpl = tc_get_setting( 'mail_reminder_subject', 'Erinnerung: {{event_title}} – in 3 Tagen' );
    $subject     = tc_resolve_placeholders( $subject_tpl, $reg, $event_id, $event_date );

    $body_tpl = tc_get_setting( 'mail_reminder_body', '' );
    if ( $body_tpl ) {
        $resolved = tc_resolve_placeholders( $body_tpl, $reg, $event_id, $event_date );
        $msg = tc_mail_wrapper_open( $blogname ) . $resolved . tc_mail_wrapper_close();
    } else {
        $detail_rows  = tc_mail_row( 'Veranstaltung', esc_html( $info['title'] ) );
        $detail_rows .= tc_mail_row( 'Datum',         esc_html( $info['date'] ) );
        $detail_rows .= tc_mail_row( 'Ort',           esc_html( $info['location'] ) );
        if ( $trainer ) {
            $detail_rows .= tc_mail_row( 'Trainer / Leitung', esc_html( $trainer ) );
        }

        $msg  = tc_mail_wrapper_open( $blogname );
        $msg .= '<h2 style="color:#0066cc;margin-top:0;">Erinnerung an Ihre Veranstaltung</h2>';
        $msg .= '<p>Hallo ' . esc_html( $reg['firstname'] ) . ' ' . esc_html( $reg['lastname'] ) . ',</p>';
        $msg .= '<p>wir möchten Sie daran erinnern, dass in <strong>3 Tagen</strong> folgender Termin stattfindet:</p>';
        $msg .= '<div style="background:#eef2ff;border-left:4px solid #4f46e5;padding:14px 18px;'
              . 'border-radius:4px;margin:16px 0;">'
              . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
              . $detail_rows
              . '</table></div>';
        $msg .= '<p>Wir freuen uns auf Sie!</p>';
        $msg .= tc_mail_signature( $blogname );
        $msg .= tc_mail_wrapper_close();
    }

    wp_mail( $reg['email'], $subject, $msg, $headers );
}

add_action( 'admin_init', function () {
    if (
        ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ||
        ! isset( $_GET['tc_test_reminder'] ) ||
        ! current_user_can( 'administrator' )
    ) return;

    tc_send_event_reminders();
    wp_die( 'Erinnerungsversand wurde manuell ausgeführt. Bitte in den Server-Logs prüfen.' );
} );
