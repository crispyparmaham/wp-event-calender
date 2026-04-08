<?php
defined( 'ABSPATH' ) || exit;

// ---------------------------------------------
// Tabelle anlegen / aktualisieren
// ---------------------------------------------
function tc_create_registrations_table() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'tc_registrations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        firstname varchar(255) NOT NULL,
        lastname varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(20),
        address varchar(255),
        zip varchar(20),
        city varchar(100),
        event_id bigint(20) NOT NULL,
        event_date date DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        source varchar(50) DEFAULT 'form',
        notes longtext,
        reminder_sent tinyint(1) NOT NULL DEFAULT 0,
        cancel_token varchar(64) DEFAULT NULL,
        created_at bigint(20) NOT NULL,
        PRIMARY KEY (id),
        KEY email (email),
        KEY event_id (event_id),
        KEY status (status),
        KEY cancel_token (cancel_token),
        KEY email_event (email, event_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Migration for existing installs: add source column if missing
    $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN IF NOT EXISTS `source` VARCHAR(50) DEFAULT 'form' AFTER `status`" );
}

// ---------------------------------------------
// DB Helpers
// ---------------------------------------------
function tc_get_all_registrations() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}tc_registrations ORDER BY created_at DESC",
        ARRAY_A
    );
    return $rows ?: array();
}

function tc_get_registration( $id ) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_registrations WHERE id = %d", $id ),
        ARRAY_A
    );
}

function tc_update_registration( $id, $data ) {
    global $wpdb;
    $allowed = array( 'firstname', 'lastname', 'email', 'phone', 'address', 'zip', 'city', 'event_id', 'event_date', 'status', 'notes', 'reminder_sent', 'cancel_token' );
    $clean   = array_intersect_key( $data, array_flip( $allowed ) );
    if ( empty( $clean ) ) return false;
    return $wpdb->update( "{$wpdb->prefix}tc_registrations", $clean, array( 'id' => $id ) );
}

function tc_delete_registration( $id ) {
    global $wpdb;
    return $wpdb->delete( "{$wpdb->prefix}tc_registrations", array( 'id' => $id ), array( '%d' ) );
}

// ---------------------------------------------
// Helper: Nächsten Occurrence-Termin berechnen
// ---------------------------------------------
function tc_get_next_occurrence_date( $weekday, int $interval = 1 ): ?string {
    if ( $weekday === null || $weekday === '' ) return null;

    $target = (int) $weekday;
    $today  = new DateTime( 'today' );
    $cur    = clone $today;

    $diff = ( $target - (int) $cur->format( 'w' ) + 7 ) % 7;
    // Falls heute der richtige Wochentag ist, nächste Occurrence = in $interval Wochen
    if ( $diff === 0 ) $diff = 7 * $interval;
    $cur->modify( "+{$diff} days" );

    return $cur->format( 'Y-m-d' );
}

// ---------------------------------------------
// Helper: Event-Infos für Mails aufbereiten
// ---------------------------------------------
function tc_get_event_mail_info( $event_id, $event_date = '' ): array {
    $event    = get_post( $event_id );
    $title    = $event ? $event->post_title : '-';
    $location = wp_strip_all_tags( get_field( 'location', $event_id ) ?: '' );

    $date_type = get_field( 'event_date_type', $event_id );

    // ── Wiederkehrende Events ────────────────────────────────────────
    if ( $date_type === 'recurring' ) {
        $weekday  = get_field( 'recurring_weekday',    $event_id );
        $interval = (int) ( get_field( 'recurring_interval', $event_id ) ?: 1 );
        $t_start  = get_field( 'recurring_time_start', $event_id );
        $t_end    = get_field( 'recurring_time_end',   $event_id );

        $weekday_names   = array(
            '0' => 'Sonntag', '1' => 'Montag',  '2' => 'Dienstag',
            '3' => 'Mittwoch','4' => 'Donnerstag','5' => 'Freitag','6' => 'Samstag',
        );
        $interval_labels = array( 1 => 'wöchentlich', 2 => 'alle 2 Wochen', 3 => 'alle 3 Wochen' );

        $day_name      = $weekday_names[ (string) $weekday ] ?? '';
        $interval_text = $interval_labels[ $interval ]       ?? '';
        $turnus        = 'Jeden ' . $day_name . ( $interval_text ? ', ' . $interval_text : '' );
        if ( $t_start ) {
            $turnus .= ' · ' . $t_start;
            if ( $t_end ) $turnus .= ' – ' . $t_end;
            $turnus .= ' Uhr';
        }

        // Nächsten konkreten Termin für den Mail-Block berechnen
        $next_ymd  = tc_get_next_occurrence_date( $weekday, $interval );
        $next_d    = $next_ymd ? DateTime::createFromFormat( 'Y-m-d', $next_ymd ) : null;
        $next_date = $next_d ? $next_d->format( 'd.m.Y' ) : 'Wiederkehrender Termin';
        if ( $t_start ) {
            $next_date .= ' · ' . $t_start;
            if ( $t_end ) $next_date .= ' – ' . $t_end;
            $next_date .= ' Uhr';
        }

        return array(
            'title'        => $title,
            'date'         => $turnus,     // Turnus-Text für {{event_datum}}
            'next_date'    => $next_date,  // Nächster konkreter Termin
            'location'     => $location ?: '-',
            'is_recurring' => true,
        );
    }

    // ── Single / Repeater Events ─────────────────────────────────────
    $first      = tc_get_first_event_date( $event_id );
    $start_date = $first['date_start'] ?? '';
    $start_time = $first['time_start'] ?? '';
    $end_date   = $first['date_end']   ?? '';

    if ( $event_date ) {
        $d        = DateTime::createFromFormat( 'Y-m-d', $event_date );
        $date_str = $d ? $d->format( 'd.m.Y' ) : $event_date;
        if ( $start_time ) $date_str .= ' um ' . $start_time . ' Uhr';
    } elseif ( $start_date ) {
        $d        = DateTime::createFromFormat( 'Y-m-d', $start_date );
        $date_str = $d ? $d->format( 'd.m.Y' ) : $start_date;
        if ( $start_time ) $date_str .= ' um ' . $start_time . ' Uhr';
        if ( $end_date && $end_date !== $start_date ) {
            $de        = DateTime::createFromFormat( 'Y-m-d', $end_date );
            $date_str .= ' – ' . ( $de ? $de->format( 'd.m.Y' ) : $end_date );
        }
    } else {
        $date_str = '-';
    }

    return array(
        'title'    => $title,
        'date'     => $date_str,
        'location' => $location ?: '-',
    );
}

// ---------------------------------------------
// Helper: Preis-Suffix für Abrechnungszeitraum
// ---------------------------------------------
function tc_price_period_suffix( int $event_id ): string {
    $period = get_field( 'price_period', $event_id ) ?: 'once';
    switch ( $period ) {
        case 'monthly': return ' / Monat';
        case 'yearly':  return ' / Jahr';
        default:        return '';
    }
}

