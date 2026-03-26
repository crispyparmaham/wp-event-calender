<?php
/**
 * Shortcode [time_event_info]
 *
 * Kompakte Info-Leiste mit Event-Details für Einzel-Event-Seiten.
 * Layouts: bar (horizontal) | cards (Kacheln)
 */
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Assets früh einreihen damit
// Oxygen Builder die Styles im <head> ausgibt.
// ─────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'tc-event-info',
        TC_URL . 'assets/css/frontend/event-info.css',
        array( 'tc-design-system' ),
        TC_VERSION
    );
} );

add_shortcode( 'time_event_info', 'tc_time_event_info_shortcode' );

function tc_time_event_info_shortcode( $atts ): string {
    $atts = shortcode_atts( array(
        'event_id'      => 0,
        'show_date'     => 'true',
        'show_time'     => 'true',
        'show_location' => 'true',
        'show_host'     => 'true',
        'show_seats'    => 'true',
        'show_audience' => 'true',
        'layout'        => 'bar',
    ), $atts, 'time_event_info' );

    $event_id = (int) $atts['event_id'];
    if ( ! $event_id ) {
        $event_id = get_the_ID();
    }
    if ( ! $event_id || get_post_type( $event_id ) !== 'time_event' ) {
        return '';
    }

    $show_date     = filter_var( $atts['show_date'],     FILTER_VALIDATE_BOOLEAN );
    $show_time     = filter_var( $atts['show_time'],     FILTER_VALIDATE_BOOLEAN );
    $show_location = filter_var( $atts['show_location'], FILTER_VALIDATE_BOOLEAN );
    $show_host     = filter_var( $atts['show_host'],     FILTER_VALIDATE_BOOLEAN );
    $show_seats    = filter_var( $atts['show_seats'],    FILTER_VALIDATE_BOOLEAN );
    $show_audience = filter_var( $atts['show_audience'], FILTER_VALIDATE_BOOLEAN );
    $layout        = $atts['layout'] === 'cards' ? 'cards' : 'bar';

    // ── Daten sammeln ──────────────────────────────────────────────
    $fields     = get_fields( $event_id ) ?: array();
    $date_type  = $fields['event_date_type'] ?? 'single';
    $location   = wp_strip_all_tags( $fields['location']   ?? '' );
    $host       = $fields['event_host']  ?? '';
    $difficulty = $fields['difficulty']  ?? '';
    $track_p    = ! empty( $fields['registration_limit'] );
    $max_p      = (int) ( $fields['max_participants'] ?? 0 );

    // Datum + Uhrzeit
    $date_str = '';
    $time_str = '';
    if ( $date_type === 'recurring' ) {
        $weekdays  = array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' );
        $weekday   = $fields['recurring_weekday'] ?? null;
        $date_str  = $weekday !== null ? 'Jeden ' . ( $weekdays[ (int) $weekday ] ?? '' ) : '';
        $ts        = $fields['recurring_time_start'] ?? '';
        $te        = $fields['recurring_time_end']   ?? '';
        if ( $ts ) {
            $time_str = $ts;
            $time_str .= $te ? " – {$te} Uhr" : ' Uhr';
        }
    } else {
        $first = tc_get_first_event_date( $event_id );
        if ( ! empty( $first['date_start'] ) ) {
            $date_str = tc_events_format_date( $first['date_start'] );
        }
        $ts = $first['time_start'] ?? '';
        $te = $first['time_end']   ?? '';
        if ( $ts ) {
            $time_str = substr( $ts, 0, 5 );
            $time_str .= $te ? ' – ' . substr( $te, 0, 5 ) . ' Uhr' : ' Uhr';
        }
    }

    // Plätze
    $seats_str   = '';
    $seats_class = '';
    if ( $show_seats && $track_p && $max_p > 0 ) {
        global $wpdb;
        $cur = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tc_registrations WHERE event_id = %d AND status IN ('pending','confirmed')",
            $event_id
        ) );
        $free = $max_p - $cur;
        if ( $free <= 0 ) {
            $seats_str   = 'Ausgebucht';
            $seats_class = 'tc-event-info-value--full';
        } else {
            $pct = ( $cur / $max_p ) * 100;
            $seats_str = "{$cur} / {$max_p}";
            if ( $pct > 80 )      $seats_class = 'tc-event-info-value--full';
            elseif ( $pct >= 50 )  $seats_class = 'tc-event-info-value--warning';
            else                   $seats_class = 'tc-event-info-value--available';
        }
    }

    // ── Items aufbauen ─────────────────────────────────────────────
    $items = array();
    if ( $show_date && $date_str ) {
        $items[] = array( 'icon' => "\xF0\x9F\x93\x85", 'label' => 'Datum', 'value' => $date_str, 'class' => '' );
    }
    if ( $show_time && $time_str ) {
        $items[] = array( 'icon' => "\xF0\x9F\x95\x90", 'label' => 'Uhrzeit', 'value' => $time_str, 'class' => '' );
    }
    if ( $show_location && $location ) {
        $items[] = array( 'icon' => "\xF0\x9F\x93\x8D", 'label' => 'Ort', 'value' => $location, 'class' => '' );
    }
    if ( $show_host && $host ) {
        $items[] = array( 'icon' => "\xF0\x9F\x91\xA4", 'label' => 'Leitung', 'value' => $host, 'class' => '' );
    }
    if ( $show_audience && $difficulty ) {
        $items[] = array( 'icon' => "\xF0\x9F\x8E\xAF", 'label' => "F\xC3\xBCr wen geeignet", 'value' => $difficulty, 'class' => '' );
    }
    if ( $show_seats && $seats_str ) {
        $items[] = array( 'icon' => "\xF0\x9F\x91\xA5", 'label' => "Pl\xC3\xA4tze", 'value' => $seats_str, 'class' => $seats_class );
    }

    if ( empty( $items ) ) {
        return '';
    }

    $dark_class = tc_dark_class();

    // ── Render ─────────────────────────────────────────────────────
    ob_start();

    if ( $layout === 'cards' ) {
        echo '<div class="tc-event-info-wrap tc-event-info-wrap--cards' . ( $dark_class ? ' ' . $dark_class : '' ) . '">';
        echo '<div class="tc-event-info-cards">';
        foreach ( $items as $item ) {
            $val_class = 'tc-event-info-value' . ( $item['class'] ? ' ' . $item['class'] : '' );
            echo '<div class="tc-event-info-card">';
            echo '<span class="tc-event-info-icon">' . $item['icon'] . '</span>';
            echo '<span class="tc-event-info-label">' . esc_html( $item['label'] ) . '</span>';
            echo '<span class="' . esc_attr( $val_class ) . '">' . esc_html( $item['value'] ) . '</span>';
            echo '</div>';
        }
        echo '</div></div>';
    } else {
        echo '<div class="tc-event-info-wrap tc-event-info-wrap--bar' . ( $dark_class ? ' ' . $dark_class : '' ) . '">';
        echo '<div class="tc-event-info-bar">';
        $count = count( $items );
        foreach ( $items as $i => $item ) {
            $val_class = 'tc-event-info-value' . ( $item['class'] ? ' ' . $item['class'] : '' );
            echo '<div class="tc-event-info-item">';
            echo '<span class="tc-event-info-icon">' . $item['icon'] . '</span>';
            echo '<div class="tc-event-info-content">';
            echo '<span class="tc-event-info-label">' . esc_html( $item['label'] ) . '</span>';
            echo '<span class="' . esc_attr( $val_class ) . '">' . esc_html( $item['value'] ) . '</span>';
            echo '</div></div>';
            if ( $i < $count - 1 ) {
                echo '<div class="tc-event-info-divider"></div>';
            }
        }
        echo '</div></div>';
    }

    return ob_get_clean();
}
