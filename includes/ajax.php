<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Helper: Datum + Zeit → ISO-String für FullCalendar
// $date = 'Y-m-d', $time = 'H:i' (optional)
// ─────────────────────────────────────────────
function tc_build_iso( $date, $time = '' ) {
    if ( ! $date ) return null;
    $t = $time ? $time : '00:00';
    return $date . 'T' . $t . ':00';
}

// ─────────────────────────────────────────────
// Helper: Wiederholungs-Occurrences generieren
//
// Gibt alle wöchentlichen Termine NACH dem
// Startdatum zurück (Startdatum selbst wird
// als Haupt-Post ausgegeben, nicht hier).
// ─────────────────────────────────────────────
function tc_get_occurrences( $start_date, $start_time, $end_date, $end_time, $weekday_int, $until ) {
    $occurrences = array();

    try {
        $start_dt = new DateTime( $start_date );
        $until_dt = new DateTime( $until . ' 23:59:59' );
    } catch ( Exception $e ) {
        return $occurrences;
    }

    $duration = null;
    if ( $end_date ) {
        try {
            $end_dt   = new DateTime( $end_date );
            $duration = $start_dt->diff( $end_dt );
        } catch ( Exception $e ) {
            // Enddatum ungültig – ignorieren
        }
    }

    $cur     = clone $start_dt;
    $cur->modify( '+1 day' );
    $cur_dow = (int) $cur->format('w');
    $diff    = ( $weekday_int - $cur_dow + 7 ) % 7;
    $cur->modify( "+{$diff} days" );

    $limit = 0;
    while ( $cur <= $until_dt && $limit < TC_RECURRING_LIMIT ) {
        $occ_start = $cur->format('Y-m-d');
        $occ_end   = null;

        if ( $duration ) {
            $occ_end_dt = clone $cur;
            $occ_end_dt->add( $duration );
            $occ_end = $occ_end_dt->format('Y-m-d');
        }

        $occurrences[] = array(
            'start' => tc_build_iso( $occ_start, $start_time ),
            'end'   => $occ_end ? tc_build_iso( $occ_end, $end_time ) : tc_build_iso( $occ_start, $end_time ),
        );

        $cur->modify( '+7 days' );
        $limit++;
    }

    return $occurrences;
}

// ─────────────────────────────────────────────
// 1. Events laden (eingeloggt + nicht eingeloggt)
// ─────────────────────────────────────────────
add_action( 'wp_ajax_' . TC_AJAX_GET_EVENTS,        'tc_handle_get_events' );
add_action( 'wp_ajax_nopriv_' . TC_AJAX_GET_EVENTS, 'tc_handle_get_events' );

function tc_handle_get_events() {
    check_ajax_referer( 'tc_nonce', 'nonce' );

    $is_logged_in = is_user_logged_in();
    $cache_key    = 'tc_events_' . ( $is_logged_in ? 'admin' : 'pub' );
    $cached       = get_transient( $cache_key );
    if ( $cached !== false ) {
        wp_send_json_success( $cached );
    }

    $statuses = $is_logged_in
        ? array( 'publish', 'draft' )
        : array( 'publish' );

    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => $statuses,
    ) );

    $events    = array();
    $today_str = wp_date( 'Y-m-d' );

    foreach ( $posts as $post ) {
        $type  = get_field( 'event_type', $post->ID ) ?: 'training';
        $color = tc_get_category_color( $type );

        // ── Alle relevanten Felder einmal pro Post lesen ──────────
        $intro_text      = get_field( 'intro_text',        $post->ID );
        $start_date      = get_field( 'start_date',        $post->ID );
        $start_time      = get_field( 'start_time',        $post->ID );
        $end_date        = get_field( 'end_date',          $post->ID );
        $end_time        = get_field( 'end_time',          $post->ID );
        $recurring_day   = get_field( 'recurring_weekday', $post->ID );
        $recurring_until = get_field( 'recurring_until',   $post->ID );
        $event_dates_raw = get_field( 'event_dates',       $post->ID );

        // ── Termintyp bestimmen ──────────────────────────────────
        $date_type = get_field( 'event_date_type', $post->ID );
        if ( empty( $date_type ) ) {
            // Backward-Compat: aus Legacy-Feldern ableiten
            $is_recurring   = (bool) get_field( 'is_recurring', $post->ID );
            $more_days_flag = (bool) get_field( 'more_days',    $post->ID );
            $has_repeater   = ! empty( $event_dates_raw ) && is_array( $event_dates_raw );

            if ( $is_recurring ) {
                $date_type = 'recurring';
            } elseif ( $has_repeater ) {
                $date_type = 'single'; // treat legacy 'multiple' as 'single' with repeater
            } else {
                $date_type = 'single';
            }

            if ( $more_days_flag && $is_recurring ) {
                error_log( 'Time Calendar: Event ID ' . $post->ID . ' hat more_days und is_recurring gleichzeitig aktiv. is_recurring wird ignoriert.' );
                $date_type = 'single';
            }
        }

        // Treat legacy 'multiple' as 'single' with repeater
        if ( $date_type === 'multiple' ) {
            $date_type = 'single';
        }

        $is_recurring_type = $date_type === 'recurring';
        $has_repeater      = ! empty( $event_dates_raw ) && is_array( $event_dates_raw );

        // ── Zukünftige Termine für Event-Übersichtskarten ─────────
        $future_dates = array();

        // Haupttermin als erster zukünftiger Termin (bei single)
        if ( ! $is_recurring_type && $start_date && $start_date >= $today_str ) {
            $future_dates[] = array(
                'date_start' => $start_date,
                'date_end'   => $end_date   ?: '',
                'time_start' => $start_time ?: '',
                'time_end'   => $end_time   ?: '',
            );
        }

        // Repeater-Termine
        if ( $has_repeater && ! $is_recurring_type ) {
            foreach ( $event_dates_raw as $ed ) {
                if ( ! empty( $ed['date_start'] ) && $ed['date_start'] >= $today_str ) {
                    $future_dates[] = array(
                        'date_start' => $ed['date_start'],
                        'date_end'   => $ed['date_end']   ?? '',
                        'time_start' => $ed['time_start'] ?? '',
                        'time_end'   => $ed['time_end']   ?? '',
                    );
                }
            }
        }

        $shared_props = array(
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'url'     => get_permalink( $post->ID ),
            'color'   => $color,
            'type'    => $type,
            'status'  => $post->post_status,
            'extendedProps' => array(
                'type'             => $type,
                'dateType'         => $date_type,
                'permalink'        => get_permalink( $post->ID ),
                'intro_text'       => $intro_text,
                'leadership'       => get_field( 'seminar_leadership', $post->ID ),
                'location'         => wp_strip_all_tags( get_field( 'location', $post->ID ) ),
                'participants'     => get_field( 'participants',        $post->ID ),
                'price'            => get_field( 'normal_preis',        $post->ID ),
                'editUrl'          => get_edit_post_link( $post->ID, 'raw' ),
                'isRecurring'      => $is_recurring_type,
                'recurringWeekday' => ( $is_recurring_type && $recurring_day !== false )
                                        ? (int) $recurring_day : null,
                'startTime'        => $start_time ?: '',
                'endTime'          => $end_time   ?: '',
                'eventDates'       => $future_dates,
                'dateIndex'        => -1, // Default: Haupttermin
            ),
        );

        // ── Haupttermin als Event ──────────────────────────────────
        if ( ! $start_date ) {
            // Kein Hauptdatum? Nur Repeater-Events ausgeben
            if ( $has_repeater && ! $is_recurring_type ) {
                foreach ( $event_dates_raw as $idx => $ed ) {
                    if ( empty( $ed['date_start'] ) ) continue;
                    $ev_start = tc_build_iso( $ed['date_start'], $ed['time_start'] ?? '' );
                    $ev_end   = ! empty( $ed['date_end'] )
                        ? tc_build_iso( $ed['date_end'],   $ed['time_end'] ?? '' )
                        : ( ! empty( $ed['time_end'] ) ? tc_build_iso( $ed['date_start'], $ed['time_end'] ) : null );

                    $ep              = $shared_props['extendedProps'];
                    $ep['dateIndex'] = $idx;

                    $events[] = array_merge( $shared_props, array(
                        'start'         => $ev_start,
                        'end'           => $ev_end,
                        'editable'      => true,
                        'extendedProps' => $ep,
                    ) );
                }
            }
            continue;
        }

        $main_start = tc_build_iso( $start_date, $start_time );
        $main_end   = $end_date
            ? tc_build_iso( $end_date, $end_time )
            : ( $end_time ? tc_build_iso( $start_date, $end_time ) : null );

        // ── Wiederkehrend ──────────────────────────────────────────
        if ( $is_recurring_type && $recurring_day !== false && $recurring_until ) {
            $ep              = $shared_props['extendedProps'];
            $ep['dateIndex'] = -1; // Haupttermin

            $events[] = array_merge( $shared_props, array(
                'start'         => $main_start,
                'end'           => $main_end,
                'title'         => '🔁 ' . $post->post_title,
                'editable'      => true,
                'extendedProps' => $ep,
            ) );

            $occurrences = tc_get_occurrences(
                $start_date, $start_time, $end_date, $end_time,
                (int) $recurring_day, $recurring_until
            );

            foreach ( $occurrences as $occ ) {
                $ep_occ              = $shared_props['extendedProps'];
                $ep_occ['dateIndex'] = -2; // Occurrence

                $events[] = array_merge( $shared_props, array(
                    'start'         => $occ['start'],
                    'end'           => $occ['end'],
                    'title'         => '🔁 ' . $post->post_title,
                    'editable'      => false,
                    'color'         => $color . 'bb',
                    'extendedProps' => $ep_occ,
                ) );
            }
            continue;
        }

        // ── Einzeltermin (Hauptdatum) ──────────────────────────────
        $ep              = $shared_props['extendedProps'];
        $ep['dateIndex'] = -1;

        $events[] = array_merge( $shared_props, array(
            'start'         => $main_start,
            'end'           => $main_end,
            'editable'      => true,
            'extendedProps' => $ep,
        ) );

        // ── Zusätzliche Repeater-Termine ───────────────────────────
        if ( $has_repeater ) {
            foreach ( $event_dates_raw as $idx => $ed ) {
                if ( empty( $ed['date_start'] ) ) continue;
                $ev_start = tc_build_iso( $ed['date_start'], $ed['time_start'] ?? '' );
                $ev_end   = ! empty( $ed['date_end'] )
                    ? tc_build_iso( $ed['date_end'],   $ed['time_end'] ?? '' )
                    : ( ! empty( $ed['time_end'] ) ? tc_build_iso( $ed['date_start'], $ed['time_end'] ) : null );

                $ep              = $shared_props['extendedProps'];
                $ep['dateIndex'] = $idx;

                $events[] = array_merge( $shared_props, array(
                    'start'         => $ev_start,
                    'end'           => $ev_end,
                    'editable'      => true,
                    'extendedProps' => $ep,
                ) );
            }
        }
    }

    set_transient( $cache_key, $events, TC_EVENTS_CACHE_TTL );
    wp_send_json_success( $events );
}

// ─────────────────────────────────────────────
// Cache-Invalidierung bei Post-Änderungen
// ─────────────────────────────────────────────
add_action( 'save_post_time_event', 'tc_clear_events_cache' );
add_action( 'deleted_post',             'tc_clear_events_cache' );

function tc_clear_events_cache() {
    delete_transient( 'tc_events_admin' );
    delete_transient( 'tc_events_pub' );
}

// ─────────────────────────────────────────────
// 2. Event direkt im Kalender anlegen
// ─────────────────────────────────────────────
add_action( 'wp_ajax_' . TC_AJAX_CREATE_EVENT, function () {
    check_ajax_referer( 'tc_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
    }

    $title        = sanitize_text_field( $_POST['title']             ?? '' );
    $start        = sanitize_text_field( $_POST['start']             ?? '' );
    $end          = sanitize_text_field( $_POST['end']               ?? '' );
    $type         = sanitize_text_field( $_POST['type']              ?? 'training' );
    $date_type    = sanitize_text_field( $_POST['date_type']         ?? 'single' );
    $rec_weekday  = sanitize_text_field( $_POST['recurring_weekday'] ?? '' );
    $rec_until    = sanitize_text_field( $_POST['recurring_until']   ?? '' );
    $multi_day    = (int) ( $_POST['multi_day']                      ?? 0 );

    if ( ! $title || ! $start ) {
        wp_send_json_error( array( 'message' => 'Titel und Startdatum sind Pflichtfelder.' ) );
    }

    // ISO aufsplitten → separate Felder
    $start_parts = explode( 'T', $start );
    $start_date  = $start_parts[0];
    $start_time  = isset( $start_parts[1] ) ? substr( $start_parts[1], 0, 5 ) : '';

    $end_date = '';
    $end_time = '';
    if ( $end ) {
        $end_parts = explode( 'T', $end );
        $end_date  = $end_parts[0] !== $start_date ? $end_parts[0] : '';
        $end_time  = isset( $end_parts[1] ) ? substr( $end_parts[1], 0, 5 ) : '';
    }

    $post_id = wp_insert_post( array(
        'post_title'  => $title,
        'post_type'   => 'time_event',
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
    }

    // Termintyp setzen
    $valid_types = array( 'single', 'recurring' );
    $date_type   = in_array( $date_type, $valid_types, true ) ? $date_type : 'single';
    update_field( 'event_date_type', $date_type, $post_id );

    update_field( 'event_type',  $type,       $post_id );
    update_field( 'start_date',  $start_date, $post_id );
    if ( $start_time ) update_field( 'start_time', $start_time, $post_id );
    if ( $end_time )   update_field( 'end_time',   $end_time,   $post_id );

    if ( $end_date && $multi_day ) {
        update_field( 'multi_day', 1,         $post_id );
        update_field( 'end_date',  $end_date, $post_id );
    }

    if ( $date_type === 'recurring' && $rec_weekday !== '' && $rec_until ) {
        update_field( 'recurring_weekday', $rec_weekday, $post_id );
        update_field( 'recurring_until',   $rec_until,   $post_id );
    }

    // Zusätzliche Termine aus Modal-Repeater speichern
    $additional = isset( $_POST['additional_dates'] ) && is_array( $_POST['additional_dates'] )
        ? $_POST['additional_dates']
        : array();

    if ( ! empty( $additional ) && $date_type === 'single' ) {
        $repeater = array();
        foreach ( $additional as $ad ) {
            $ad_date = sanitize_text_field( $ad['date']       ?? '' );
            $ad_from = sanitize_text_field( $ad['time_start'] ?? '' );
            $ad_to   = sanitize_text_field( $ad['time_end']   ?? '' );
            if ( ! $ad_date ) continue;
            $repeater[] = array(
                'date_start' => $ad_date,
                'time_start' => $ad_from,
                'time_end'   => $ad_to,
            );
        }
        if ( ! empty( $repeater ) ) {
            update_field( 'event_dates', $repeater, $post_id );
        }
    }

    wp_send_json_success( array(
        'id'      => $post_id,
        'editUrl' => get_edit_post_link( $post_id, 'raw' ),
    ) );
} );

// ─────────────────────────────────────────────
// 3. Event per Drag & Drop / Resize updaten
//
// dateIndex:
//   >= 0  → Repeater-Eintrag (Index)
//   -1    → Haupttermin (start_date / start_time)
//   -2    → Recurring Occurrence (nur start_date)
// ─────────────────────────────────────────────
add_action( 'wp_ajax_' . TC_AJAX_UPDATE_EVENT, function () {
    check_ajax_referer( 'tc_nonce', 'nonce' );

    $post_id    = intval( $_POST['id'] ?? 0 );

    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
    }

    $start      = sanitize_text_field( $_POST['start']      ?? '' );
    $end        = sanitize_text_field( $_POST['end']        ?? '' );
    $date_index = isset( $_POST['date_index'] ) && $_POST['date_index'] !== '' ? (int) $_POST['date_index'] : -1;

    // ── Recurring Occurrence (dateIndex = -2) ─────────────────
    if ( $date_index === -2 ) {
        // Nur Haupttermin (start_date) updaten, Occurrences werden relativ berechnet
        if ( $start ) {
            $sp = explode( 'T', $start );
            update_field( 'start_date', $sp[0], $post_id );
            if ( ! empty( $sp[1] ) ) update_field( 'start_time', substr( $sp[1], 0, 5 ), $post_id );
        }
        tc_clear_events_cache();
        wp_send_json_success( array( 'id' => $post_id ) );
    }

    // ── Repeater-Eintrag (dateIndex >= 0) ─────────────────────
    if ( $date_index >= 0 ) {
        $event_dates = get_field( 'event_dates', $post_id );
        if ( ! is_array( $event_dates ) || ! isset( $event_dates[ $date_index ] ) ) {
            wp_send_json_error( array( 'message' => 'Termin nicht gefunden.' ) );
        }

        if ( $start ) {
            $sp = explode( 'T', $start );
            $event_dates[ $date_index ]['date_start'] = $sp[0];
            if ( ! empty( $sp[1] ) ) $event_dates[ $date_index ]['time_start'] = substr( $sp[1], 0, 5 );
        }

        if ( $end ) {
            $ep         = explode( 'T', $end );
            $start_date = explode( 'T', $start )[0] ?? '';
            if ( $ep[0] !== $start_date ) {
                $event_dates[ $date_index ]['date_end'] = $ep[0];
            } else {
                $event_dates[ $date_index ]['date_end'] = '';
            }
            if ( ! empty( $ep[1] ) ) $event_dates[ $date_index ]['time_end'] = substr( $ep[1], 0, 5 );
        } else {
            $event_dates[ $date_index ]['date_end'] = '';
            $event_dates[ $date_index ]['time_end'] = '';
        }

        update_field( 'event_dates', $event_dates, $post_id );
        tc_clear_events_cache();
        wp_send_json_success( array( 'id' => $post_id ) );
    }

    // ── Haupttermin (dateIndex = -1) ──────────────────────────
    if ( $start ) {
        $sp = explode( 'T', $start );
        update_field( 'start_date', $sp[0], $post_id );
        if ( ! empty( $sp[1] ) ) update_field( 'start_time', substr( $sp[1], 0, 5 ), $post_id );
    }

    if ( $end ) {
        $ep         = explode( 'T', $end );
        $start_date = explode( 'T', $start )[0] ?? '';

        if ( $ep[0] !== $start_date ) {
            update_field( 'multi_day', 1,      $post_id );
            update_field( 'end_date',  $ep[0], $post_id );
        } else {
            update_field( 'multi_day', 0,    $post_id );
            update_field( 'end_date',  null, $post_id );
        }
        if ( ! empty( $ep[1] ) ) update_field( 'end_time', substr( $ep[1], 0, 5 ), $post_id );
    } else {
        update_field( 'multi_day', 0,    $post_id );
        update_field( 'end_date',  null, $post_id );
        update_field( 'end_time',  null, $post_id );
    }

    tc_clear_events_cache();
    wp_send_json_success( array( 'id' => $post_id ) );
} );
