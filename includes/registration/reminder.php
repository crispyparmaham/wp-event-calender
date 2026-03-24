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
    $headers    = [ 'Content-Type: text/html; charset=UTF-8' ];

    $subject_tpl = tc_get_mail_setting( 'reminder', 'subject' );
    $subject     = tc_resolve_placeholders( $subject_tpl, $reg, $event_id, $event_date );
    $msg         = tc_build_mail_body( 'reminder', $reg, $event_id, $event_date );

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
