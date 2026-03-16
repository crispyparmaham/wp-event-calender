<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────
// Rewrite-Regel: /events/{post-slug}/ical/
// ─────────────────────────────────────────────────────────────────
add_action( 'init', function () {
    add_rewrite_tag( '%tc_ical_slug%', '([^/]+)' );
    add_rewrite_rule(
        '^events/([^/]+)/ical/?$',
        'index.php?tc_ical_slug=$matches[1]',
        'top'
    );
} );

// ─────────────────────────────────────────────────────────────────
// Endpoint-Handler: ICS-Datei ausgeben
// ─────────────────────────────────────────────────────────────────
add_action( 'template_redirect', function () {
    $slug = get_query_var( 'tc_ical_slug' );
    if ( ! $slug ) return;

    $post = get_page_by_path( $slug, OBJECT, 'time_event' );
    if ( ! $post ) {
        wp_die( 'Veranstaltung nicht gefunden.', 'Nicht gefunden', [ 'response' => 404 ] );
    }

    $ics = tc_build_ics( $post );

    $filename = sanitize_title( $post->post_title ) . '.ics';
    header( 'Content-Type: text/calendar; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Pragma: no-cache' );
    echo $ics;
    exit;
} );

// ─────────────────────────────────────────────────────────────────
// ICS-Datei aufbauen
// ─────────────────────────────────────────────────────────────────
function tc_build_ics( WP_Post $post ) {
    $event_id   = $post->ID;
    $title      = $post->post_title;
    $description = wp_strip_all_tags( get_field( 'intro_text', $event_id ) ?: '' );
    $location   = wp_strip_all_tags( get_field( 'location',   $event_id ) ?: '' );
    $start_date = get_field( 'start_date', $event_id ); // Y-m-d
    $start_time = get_field( 'start_time', $event_id ); // H:i or empty
    $end_date   = get_field( 'end_date',   $event_id ); // Y-m-d
    $is_recurring      = (bool) get_field( 'is_recurring',       $event_id );
    $recurring_weekday = get_field( 'recurring_weekday', $event_id );
    $recurring_until   = get_field( 'recurring_until',   $event_id );
    $more_days         = (bool) get_field( 'more_days', $event_id );

    // Welche Termine sollen zu VEVENTs werden?
    $occurrences = []; // array of [ 'start' => Y-m-d, 'end' => Y-m-d ]

    if ( $more_days && $end_date ) {
        // Mehrtägige Veranstaltung → ein VEVENT für den Gesamtzeitraum
        $occurrences[] = [ 'start' => $start_date, 'end' => $end_date ];

    } elseif ( $is_recurring && $recurring_weekday !== '' && $recurring_until ) {
        // Wiederkehrend → alle Occurrences
        $target   = (int) $recurring_weekday;
        $cur      = new DateTime( $start_date );
        $until_dt = new DateTime( $recurring_until . ' 23:59:59' );

        $diff = ( $target - (int) $cur->format( 'w' ) + 7 ) % 7;
        if ( $diff > 0 ) $cur->modify( "+{$diff} days" );

        $limit = 0;
        while ( $cur <= $until_dt && $limit < 260 ) {
            $occurrences[] = [ 'start' => $cur->format( 'Y-m-d' ), 'end' => $cur->format( 'Y-m-d' ) ];
            $cur->modify( '+7 days' );
            $limit++;
        }
    } else {
        // Einzeltermin
        $occurrences[] = [ 'start' => $start_date, 'end' => $end_date ?: $start_date ];
    }

    $uid_base = sanitize_title( $title ) . '-' . $event_id . '@' . parse_url( home_url(), PHP_URL_HOST );
    $dtstamp  = gmdate( 'Ymd\THis\Z' );

    $vevent_blocks = '';
    foreach ( $occurrences as $i => $occ ) {
        $dtstart = tc_ical_dt( $occ['start'], $start_time );
        $dtend   = tc_ical_dt_end( $occ['start'], $occ['end'], $start_time );
        $uid     = $uid_base . '-' . $i;

        $vevent_blocks .= "BEGIN:VEVENT\r\n";
        $vevent_blocks .= "UID:{$uid}\r\n";
        $vevent_blocks .= "DTSTAMP:{$dtstamp}\r\n";
        $vevent_blocks .= $dtstart . "\r\n";
        $vevent_blocks .= $dtend   . "\r\n";
        $vevent_blocks .= "SUMMARY:" . tc_ical_escape( $title ) . "\r\n";
        if ( $description ) {
            $vevent_blocks .= tc_ical_fold( 'DESCRIPTION:' . tc_ical_escape( $description ) ) . "\r\n";
        }
        if ( $location ) {
            $vevent_blocks .= "LOCATION:" . tc_ical_escape( $location ) . "\r\n";
        }
        $vevent_blocks .= "URL:" . get_permalink( $event_id ) . "\r\n";
        $vevent_blocks .= "END:VEVENT\r\n";
    }

    $cal  = "BEGIN:VCALENDAR\r\n";
    $cal .= "VERSION:2.0\r\n";
    $cal .= "PRODID:-//Time Calendar//DE\r\n";
    $cal .= "CALSCALE:GREGORIAN\r\n";
    $cal .= "METHOD:PUBLISH\r\n";
    $cal .= "X-WR-CALNAME:" . tc_ical_escape( $title ) . "\r\n";
    $cal .= $vevent_blocks;
    $cal .= "END:VCALENDAR\r\n";

    return $cal;
}

// ─────────────────────────────────────────────────────────────────
// Hilfsfunktionen für ICS-Formatierung
// ─────────────────────────────────────────────────────────────────

/** DTSTART-Zeile: mit Zeit (DATETIME) oder ohne (DATE) */
function tc_ical_dt( $date_ymd, $time_hi ) {
    if ( $time_hi ) {
        $dt = DateTime::createFromFormat( 'Y-m-d H:i', $date_ymd . ' ' . $time_hi );
        return 'DTSTART:' . $dt->format( 'Ymd\THis' );
    }
    return 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $date_ymd );
}

/** DTEND-Zeile: +1 Stunde bei Zeitangabe, nächster Tag bei Nur-Datum */
function tc_ical_dt_end( $start_ymd, $end_ymd, $time_hi ) {
    if ( $time_hi ) {
        $dt = DateTime::createFromFormat( 'Y-m-d H:i', $start_ymd . ' ' . $time_hi );
        $dt->modify( '+1 hour' );
        return 'DTEND:' . $dt->format( 'Ymd\THis' );
    }
    // Ganztägig: DTEND ist exklusiv (nächster Tag)
    $dt = DateTime::createFromFormat( 'Y-m-d', $end_ymd );
    $dt->modify( '+1 day' );
    return 'DTEND;VALUE=DATE:' . $dt->format( 'Ymd' );
}

/** Sonderzeichen gemäß RFC 5545 escapen */
function tc_ical_escape( $text ) {
    $text = str_replace( '\\', '\\\\', $text );
    $text = str_replace( ';',  '\;',   $text );
    $text = str_replace( ',',  '\,',   $text );
    $text = str_replace( "\n", '\n',   $text );
    return $text;
}

/** Lange Zeilen auf 75 Zeichen umbrechen (RFC 5545) */
function tc_ical_fold( $line ) {
    $out   = '';
    $bytes = mb_str_split( $line, 1, 'UTF-8' );
    $col   = 0;
    foreach ( $bytes as $char ) {
        if ( $col >= 75 ) {
            $out .= "\r\n ";
            $col  = 1;
        }
        $out .= $char;
        $col++;
    }
    return $out;
}

// ─────────────────────────────────────────────────────────────────
// Shortcode: [training_ical_button]
// Gibt einen Download-Button für die .ics-Datei der aktuellen
// oder einer per event_id angegebenen Veranstaltung aus.
// ─────────────────────────────────────────────────────────────────
add_shortcode( 'training_ical_button', function ( $atts ) {
    $atts = shortcode_atts( [
        'event_id' => 0,
        'label'    => 'Zum Kalender hinzufügen',
    ], $atts, 'training_ical_button' );

    $event_id = absint( $atts['event_id'] );

    if ( ! $event_id && is_singular( 'time_event' ) ) {
        $event_id = get_the_ID();
    }

    if ( ! $event_id || get_post_type( $event_id ) !== 'time_event' ) {
        return '';
    }

    $slug = get_post_field( 'post_name', $event_id );
    $url  = home_url( '/events/' . $slug . '/ical/' );

    return sprintf(
        '<a href="%s" class="tc-ical-btn" download>%s</a>',
        esc_url( $url ),
        esc_html( $atts['label'] )
    );
} );

// Minimales CSS für den Button
add_action( 'wp_head', function () { ?>
<style>
.tc-ical-btn {
    display: inline-block;
    padding: 8px 18px;
    background: #4f46e5;
    color: #fff;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}
.tc-ical-btn:hover { background: #4338ca; color: #fff; }
</style>
<?php } );

// ─────────────────────────────────────────────────────────────────
// Rewrite-Regeln beim Aktivieren des Plugins flushen
// (wird in functions.php über register_activation_hook erledigt)
// ─────────────────────────────────────────────────────────────────
function tc_flush_rewrite_rules_for_ical() {
    add_rewrite_tag( '%tc_ical_slug%', '([^/]+)' );
    add_rewrite_rule(
        '^events/([^/]+)/ical/?$',
        'index.php?tc_ical_slug=$matches[1]',
        'top'
    );
    flush_rewrite_rules();
}
