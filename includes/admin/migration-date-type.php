<?php
defined( 'ABSPATH' ) || exit;

/**
 * Einmalige Migration: event_date_type für bestehende Posts setzen.
 *
 * Liest die Legacy-Felder (is_recurring, more_days, event_dates) und
 * leitet daraus den passenden Termintyp ab:
 *   - is_recurring = 1         → 'recurring'
 *   - event_dates hat Einträge → 'multiple'
 *   - Sonst (inkl. more_days)  → 'single'
 *
 * Läuft einmalig, geschützt durch wp_option 'tc_date_type_migrated'.
 */
add_action( 'admin_init', 'tc_migrate_event_date_type' );

function tc_migrate_event_date_type() {
    if ( get_option( 'tc_date_type_migrated' ) ) {
        return;
    }

    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        $existing = get_field( 'event_date_type', $post_id );
        if ( ! empty( $existing ) ) {
            continue; // bereits migriert
        }

        $is_recurring    = (bool) get_field( 'is_recurring', $post_id );
        $event_dates_raw = get_field( 'event_dates', $post_id );
        $has_repeater    = ! empty( $event_dates_raw ) && is_array( $event_dates_raw );

        if ( $is_recurring ) {
            $date_type = 'recurring';
        } elseif ( $has_repeater ) {
            $date_type = 'multiple';
        } else {
            $date_type = 'single';
        }

        update_field( 'event_date_type', $date_type, $post_id );
    }

    update_option( 'tc_date_type_migrated', '1', true );
    tc_clear_events_cache();
}
