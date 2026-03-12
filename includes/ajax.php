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
    // $start_date : 'Y-m-d'
    // $start_time : 'H:i'
    // $end_date   : 'Y-m-d' | null  (nur bei mehrtägig)
    // $end_time   : 'H:i'   | null
    // $weekday_int: 0 (So) – 6 (Sa)
    // $until      : 'Y-m-d'

    $occurrences = array();

    $start_dt = new DateTime( $start_date );
    $until_dt = new DateTime( $until . ' 23:59:59' );

    // Dauer berechnen wenn mehrtägig
    $duration = null;
    if ( $end_date ) {
        $end_dt   = new DateTime( $end_date );
        $duration = $start_dt->diff( $end_dt );
    }

    // Ersten Occurrence-Termin bestimmen:
    // Immer mindestens 1 Tag nach Startdatum, dann zum
    // gesuchten Wochentag springen.
    $cur     = clone $start_dt;
    $cur->modify( '+1 day' ); // garantiert: nie gleich wie Startdatum
    $cur_dow = (int) $cur->format('w');
    $diff    = ( $weekday_int - $cur_dow + 7 ) % 7;
    $cur->modify( "+{$diff} days" );

    $limit = 0;
    while ( $cur <= $until_dt && $limit < 260 ) {
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
add_action( 'wp_ajax_tc_get_events',        'tc_handle_get_events' );
add_action( 'wp_ajax_nopriv_tc_get_events', 'tc_handle_get_events' );

function tc_handle_get_events() {
    check_ajax_referer( 'tc_nonce', 'nonce' );

    $statuses = is_user_logged_in()
        ? array( 'publish', 'draft' )
        : array( 'publish' );

    $posts = get_posts( array(
        'post_type'      => 'training_event',
        'posts_per_page' => -1,
        'post_status'    => $statuses,
    ) );

    $events = array();

    foreach ( $posts as $post ) {
        $type  = get_field( 'event_type', $post->ID ) ?: 'training';
        $color = tc_get_category_color( $type );

        $shared_props = array(
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'url'     => get_permalink( $post->ID ),
            'color'   => $color,
            'type'    => $type,
            'status'  => $post->post_status,
            'extendedProps' => array(
                'type'         => $type,
                'permalink'    => get_permalink( $post->ID ),
                'leadership'   => get_field( 'seminar_leadership', $post->ID ),
                'location'     => wp_strip_all_tags( get_field( 'location', $post->ID ) ),
                'participants' => get_field( 'participants',        $post->ID ),
                'price'        => get_field( 'normal_preis',        $post->ID ),
                'editUrl'      => get_edit_post_link( $post->ID, 'raw' ),
                'isRecurring'  => false,
                'dateIndex'    => null,
            ),
        );

        // ── Mehrere Termine (Repeater) ─────────────
        $event_dates = get_field( 'event_dates', $post->ID );
        if ( ! empty( $event_dates ) && is_array( $event_dates ) ) {
            foreach ( $event_dates as $idx => $ed ) {
                if ( empty( $ed['date_start'] ) ) continue;
                $ev_start = tc_build_iso( $ed['date_start'], $ed['time_start'] ?? '' );
                $ev_end   = ! empty( $ed['date_end'] )
                    ? tc_build_iso( $ed['date_end'],   $ed['time_end'] ?? '' )
                    : ( ! empty( $ed['time_end'] ) ? tc_build_iso( $ed['date_start'], $ed['time_end'] ) : null );

                $ep              = $shared_props['extendedProps'];
                $ep['dateIndex'] = $idx;

                $events[] = array_merge( $shared_props, array(
                    'start'    => $ev_start,
                    'end'      => $ev_end,
                    'editable' => true,
                    'extendedProps' => $ep,
                ) );
            }
            continue; // Repeater-Modus: Legacy-Felder ignorieren
        }

        // ── Legacy: Einzeldatum + optionale Wiederholung ──
        $start_date = get_field( 'start_date',      $post->ID );
        $start_time = get_field( 'start_time',      $post->ID );
        $end_date   = get_field( 'end_date',        $post->ID );
        $end_time   = get_field( 'end_time',        $post->ID );

        if ( ! $start_date ) continue;

        $is_recurring    = (bool) get_field( 'is_recurring',   $post->ID );
        $recurring_until = get_field( 'recurring_until',       $post->ID );
        $recurring_day   = get_field( 'recurring_weekday',     $post->ID );

        $main_start = tc_build_iso( $start_date, $start_time );
        $main_end   = $end_date
            ? tc_build_iso( $end_date, $end_time )
            : ( $end_time ? tc_build_iso( $start_date, $end_time ) : null );

        $shared = $shared_props;
        $shared['extendedProps']['isRecurring'] = $is_recurring;

        if ( $is_recurring && $recurring_day !== false && $recurring_until ) {
            $events[] = array_merge( $shared, array(
                'start'    => $main_start,
                'end'      => $main_end,
                'title'    => '🔁 ' . $post->post_title,
                'editable' => true,
            ) );

            $occurrences = tc_get_occurrences(
                $start_date, $start_time, $end_date, $end_time,
                (int) $recurring_day, $recurring_until
            );

            foreach ( $occurrences as $occ ) {
                $events[] = array_merge( $shared, array(
                    'start'    => $occ['start'],
                    'end'      => $occ['end'],
                    'title'    => '🔁 ' . $post->post_title,
                    'editable' => false,
                    'color'    => $color . 'bb',
                ) );
            }
        } else {
            $events[] = array_merge( $shared, array(
                'start'    => $main_start,
                'end'      => $main_end,
                'editable' => true,
            ) );
        }
    }

    wp_send_json_success( $events );
}

// ─────────────────────────────────────────────
// 2. Event direkt im Kalender anlegen
// ─────────────────────────────────────────────
add_action( 'wp_ajax_tc_create_event', function () {
    check_ajax_referer( 'tc_nonce', 'nonce' );

    if ( ! current_user_can( 'publish_posts' ) ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
    }

    $title        = sanitize_text_field( $_POST['title']             ?? '' );
    $start        = sanitize_text_field( $_POST['start']             ?? '' ); // Y-m-d\TH:i
    $end          = sanitize_text_field( $_POST['end']               ?? '' );
    $type         = sanitize_text_field( $_POST['type']              ?? 'training' );
    $is_recurring = (int) ( $_POST['is_recurring']                   ?? 0 );
    $rec_weekday  = sanitize_text_field( $_POST['recurring_weekday'] ?? '' );
    $rec_until    = sanitize_text_field( $_POST['recurring_until']   ?? '' );

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
        'post_type'   => 'training_event',
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
    }

    update_field( 'event_type',  $type,       $post_id );
    update_field( 'start_date',  $start_date, $post_id );
    if ( $start_time ) update_field( 'start_time', $start_time, $post_id );
    if ( $end_time )   update_field( 'end_time',   $end_time,   $post_id );

    if ( $end_date ) {
        update_field( 'more_days', 1,         $post_id );
        update_field( 'end_date',  $end_date, $post_id );
    }

    if ( $is_recurring && $rec_weekday !== '' && $rec_until ) {
        update_field( 'is_recurring',      1,            $post_id );
        update_field( 'recurring_weekday', $rec_weekday, $post_id );
        update_field( 'recurring_until',   $rec_until,   $post_id );
    }

    wp_send_json_success( array(
        'id'      => $post_id,
        'editUrl' => get_edit_post_link( $post_id, 'raw' ),
    ) );
} );

// ─────────────────────────────────────────────
// 3. Event per Drag & Drop / Resize updaten
// ─────────────────────────────────────────────
add_action( 'wp_ajax_tc_update_event', function () {
    check_ajax_referer( 'tc_nonce', 'nonce' );

    $post_id = intval( $_POST['id'] ?? 0 );

    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
    }

    $start      = sanitize_text_field( $_POST['start']      ?? '' );
    $end        = sanitize_text_field( $_POST['end']        ?? '' );
    $date_index = isset( $_POST['date_index'] ) && $_POST['date_index'] !== '' ? (int) $_POST['date_index'] : null;

    // ── Repeater-Modus: einzelnen Termin aktualisieren ──
    if ( $date_index !== null ) {
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
        wp_send_json_success( array( 'id' => $post_id ) );
    }

    // ── Legacy-Modus: klassische Einzeldatum-Felder ──
    if ( $start ) {
        $sp = explode( 'T', $start );
        update_field( 'start_date', $sp[0], $post_id );
        if ( ! empty( $sp[1] ) ) update_field( 'start_time', substr( $sp[1], 0, 5 ), $post_id );
    }

    if ( $end ) {
        $ep         = explode( 'T', $end );
        $start_date = explode( 'T', $start )[0] ?? '';

        if ( $ep[0] !== $start_date ) {
            update_field( 'more_days', 1,      $post_id );
            update_field( 'end_date',  $ep[0], $post_id );
        } else {
            update_field( 'more_days', 0,    $post_id );
            update_field( 'end_date',  null, $post_id );
        }
        if ( ! empty( $ep[1] ) ) update_field( 'end_time', substr( $ep[1], 0, 5 ), $post_id );
    } else {
        update_field( 'more_days', 0,    $post_id );
        update_field( 'end_date',  null, $post_id );
        update_field( 'end_time',  null, $post_id );
    }

    wp_send_json_success( array( 'id' => $post_id ) );
} );
