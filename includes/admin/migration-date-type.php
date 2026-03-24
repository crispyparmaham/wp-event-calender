<?php
defined( 'ABSPATH' ) || exit;

/**
 * Einmalige Migration: event_date_type für bestehende Posts setzen.
 *
 * Phase 1 (v3.1): Legacy-Felder → event_date_type (single/multiple/recurring)
 * Phase 2 (v3.2): 'multiple' → 'single' mit Repeater-Erhalt
 *   - Erster event_dates Eintrag → start_date / start_time / end_time
 *   - Restliche Einträge bleiben im Repeater
 *   - event_date_type wird auf 'single' gesetzt
 * Phase 3 (v3.3): Haupttermin (start_date) → event_dates Repeater
 *   - start_date / start_time / end_time / end_date als ersten Repeater-Eintrag
 *   - Datenbankwerte der alten Felder bleiben erhalten
 */

// ── Phase 1: Legacy → event_date_type ─────────────────────────────
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
            continue;
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

// ── Phase 2: 'multiple' → 'single' + Repeater-Erhalt ─────────────
add_action( 'admin_init', 'tc_migrate_multiple_to_single' );

function tc_migrate_multiple_to_single() {
    if ( get_option( 'tc_date_type_v2_migrated' ) ) {
        return;
    }

    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        $date_type = get_field( 'event_date_type', $post_id );
        if ( $date_type !== 'multiple' ) {
            continue;
        }

        $event_dates = get_field( 'event_dates', $post_id );
        if ( ! empty( $event_dates ) && is_array( $event_dates ) ) {
            // Erster Eintrag wird zum Haupttermin
            $first = $event_dates[0];

            if ( ! empty( $first['date_start'] ) ) {
                update_field( 'start_date', $first['date_start'], $post_id );
            }
            if ( ! empty( $first['time_start'] ) ) {
                update_field( 'start_time', $first['time_start'], $post_id );
            }
            if ( ! empty( $first['time_end'] ) ) {
                update_field( 'end_time', $first['time_end'], $post_id );
            }

            // Ersten Eintrag aus Repeater entfernen, Rest behalten
            array_shift( $event_dates );
            if ( ! empty( $event_dates ) ) {
                update_field( 'event_dates', $event_dates, $post_id );
            } else {
                update_field( 'event_dates', array(), $post_id );
            }
        }

        update_field( 'event_date_type', 'single', $post_id );
    }

    update_option( 'tc_date_type_v2_migrated', '1', true );
    tc_clear_events_cache();
}

// ── Phase 3: Haupttermin → event_dates Repeater ───────────────
add_action( 'admin_init', 'tc_migrate_main_date_to_repeater' );

function tc_migrate_main_date_to_repeater() {
    if ( get_option( 'tc_date_type_v3_migrated' ) ) {
        return;
    }

    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        $date_type = get_field( 'event_date_type', $post_id );
        if ( $date_type !== 'single' ) {
            continue;
        }

        // Alte Felder direkt aus der Meta-Tabelle lesen (unabhängig vom ACF-Register)
        $start_date = get_post_meta( $post_id, 'start_date', true );
        if ( empty( $start_date ) ) {
            continue;
        }

        $start_time = get_post_meta( $post_id, 'start_time', true ) ?: '';
        $end_time   = get_post_meta( $post_id, 'end_time',   true ) ?: '';
        $end_date   = get_post_meta( $post_id, 'end_date',   true ) ?: '';
        $multi_day  = get_post_meta( $post_id, 'multi_day',  true );

        // Prüfen ob dieser date_start bereits im Repeater vorhanden ist
        $existing = get_field( 'event_dates', $post_id );
        if ( ! empty( $existing ) && is_array( $existing ) ) {
            $already = false;
            foreach ( $existing as $row ) {
                if ( ( $row['date_start'] ?? '' ) === $start_date ) {
                    $already = true;
                    break;
                }
            }
            if ( $already ) {
                continue;
            }
        }

        $new_entry = array(
            'date_start' => $start_date,
            'date_end'   => ( $multi_day == '1' || $multi_day === true ) ? $end_date : '',
            'time_start' => $start_time,
            'time_end'   => $end_time,
            'seats'      => '',
            'notes'      => '',
        );

        // Neuen Eintrag an den ANFANG des Repeaters setzen
        $new_repeater = array_merge( array( $new_entry ), $existing ?: array() );
        update_field( 'event_dates', $new_repeater, $post_id );
    }

    update_option( 'tc_date_type_v3_migrated', '1', true );
    tc_clear_events_cache();
}

// ── Phase 4: Feldnamen-Generalisierung ────────────────────────────
add_action( 'admin_init', 'tc_migrate_generalize_v1' );

function tc_migrate_generalize_v1() {
    if ( get_option( 'tc_generalize_v1_migrated' ) ) {
        return;
    }

    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        // seminar_leadership → event_host
        $val = get_post_meta( $post_id, 'seminar_leadership', true );
        if ( $val !== '' && $val !== false ) {
            update_field( 'event_host', $val, $post_id );
        }

        // intro_text → event_description
        $val = get_post_meta( $post_id, 'intro_text', true );
        if ( $val !== '' && $val !== false ) {
            update_field( 'event_description', $val, $post_id );
        }

        // participants → max_participants
        $val = get_post_meta( $post_id, 'participants', true );
        if ( $val !== '' && $val !== false ) {
            update_field( 'max_participants', $val, $post_id );
        }

        // track_participants → registration_limit
        $val = get_post_meta( $post_id, 'track_participants', true );
        if ( $val !== '' && $val !== false ) {
            update_field( 'registration_limit', $val, $post_id );
        }

        // normal_preis → event_price
        $val = get_post_meta( $post_id, 'normal_preis', true );
        if ( $val !== '' && $val !== false ) {
            update_field( 'event_price', $val, $post_id );
        }

        // price_on_request (true_false) → event_price_type (fixed/request)
        $price_on_req = get_post_meta( $post_id, 'price_on_request', true );
        $price_type   = ( $price_on_req == '1' || $price_on_req === true ) ? 'request' : 'fixed';
        update_field( 'event_price_type', $price_type, $post_id );

        // registration_mode: set default 'open' if not already set
        $existing_mode = get_post_meta( $post_id, 'registration_mode', true );
        if ( $existing_mode === '' || $existing_mode === false ) {
            update_field( 'registration_mode', 'open', $post_id );
        }
    }

    update_option( 'tc_generalize_v1_migrated', '1', true );
    tc_clear_events_cache();
}

// ── Phase 5: Abrechnungszeitraum – alle Events auf 'once' setzen ──────────
add_action( 'admin_init', 'tc_migrate_price_period' );

function tc_migrate_price_period() {
    if ( get_option( 'tc_price_period_migrated' ) ) {
        return;
    }

    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        $existing = get_post_meta( $post_id, 'price_period', true );
        if ( $existing === '' || $existing === false ) {
            update_field( 'price_period', 'once', $post_id );
        }
    }

    update_option( 'tc_price_period_migrated', '1', true );
    tc_clear_events_cache();
}

// ── Phase 6: Early Bird → Aktionspreis ───────────────────────────────────
add_action( 'admin_init', 'tc_migrate_action_price' );

function tc_migrate_action_price() {
    if ( get_option( 'tc_action_price_migrated' ) ) {
        return;
    }

    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        // Alte early_bird Gruppe aus DB lesen (unabhängig vom ACF-Register)
        $eb_preis    = get_post_meta( $post_id, 'early_bird_early_bird_preis', true );
        $eb_deadline = get_post_meta( $post_id, 'early_bird_anmeldung', true );

        if ( $eb_preis !== '' && $eb_preis !== false ) {
            update_post_meta( $post_id, 'action_price_action_price_value', $eb_preis );
        }
        if ( $eb_deadline !== '' && $eb_deadline !== false ) {
            update_post_meta( $post_id, 'action_price_action_price_until', $eb_deadline );
        }
    }

    update_option( 'tc_action_price_migrated', '1', true );
    tc_clear_events_cache();
}

// ── Phase 7: Wiederkehrend v2 – Zeitfelder + Intervall ───────────────────
add_action( 'admin_init', 'tc_migrate_recurring_v2' );

function tc_migrate_recurring_v2() {
    if ( get_option( 'tc_recurring_v2_migrated' ) ) {
        return;
    }

    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        $date_type = get_field( 'event_date_type', $post_id );
        if ( $date_type !== 'recurring' ) {
            continue;
        }

        // start_time → recurring_time_start
        $start_time = get_post_meta( $post_id, 'start_time', true );
        if ( $start_time !== '' && $start_time !== false ) {
            update_field( 'recurring_time_start', $start_time, $post_id );
        }

        // end_time → recurring_time_end
        $end_time = get_post_meta( $post_id, 'end_time', true );
        if ( $end_time !== '' && $end_time !== false ) {
            update_field( 'recurring_time_end', $end_time, $post_id );
        }

        // recurring_interval default: 1 (jede Woche)
        $existing_interval = get_post_meta( $post_id, 'recurring_interval', true );
        if ( $existing_interval === '' || $existing_interval === false ) {
            update_field( 'recurring_interval', '1', $post_id );
        }
    }

    update_option( 'tc_recurring_v2_migrated', '1', true );
    tc_clear_events_cache();
}
