<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function () {

    if (
        ! isset( $_GET['page'], $_GET['tc_export'] ) ||
        $_GET['page'] !== 'training-registrations'   ||
        $_GET['tc_export'] !== 'csv'
    ) return;

    if ( ! current_user_can( 'administrator' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    check_admin_referer( 'tc_export_csv', 'nonce' );

    $filter_event  = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
    $all           = tc_get_all_registrations();
    $registrations = $filter_event
        ? array_values( array_filter( $all, fn( $r ) => (int) $r['event_id'] === $filter_event ) )
        : $all;

    $event_slug = $filter_event ? sanitize_title( get_the_title( $filter_event ) ) : 'alle';
    $filename   = 'anmeldungen-' . $event_slug . '-' . date( 'Y-m-d' ) . '.csv';

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $out = fopen( 'php://output', 'w' );
    fputs( $out, "\xEF\xBB\xBF" );

    fputcsv( $out, [
        'Vorname', 'Nachname', 'E-Mail', 'Telefon', 'Unternehmen',
        'Veranstaltung', 'Datum', 'Status', 'Angemeldet am', 'Notizen',
    ], ';' );

    $status_map = [
        'confirmed' => 'Bestätigt',
        'cancelled' => 'Storniert',
        'pending'   => 'Ausstehend',
        'waitlist'  => 'Warteliste',
    ];

    foreach ( $registrations as $reg ) {
        $event_title = $reg['event_id'] ? get_the_title( $reg['event_id'] ) : '';

        $date_str = '';
        if ( $reg['event_date'] ) {
            $d        = DateTime::createFromFormat( 'Y-m-d', $reg['event_date'] );
            $date_str = $d ? $d->format( 'd.m.Y' ) : $reg['event_date'];
        } elseif ( $reg['event_id'] ) {
            $sd = get_field( 'start_date', $reg['event_id'] );
            if ( $sd ) {
                $d        = DateTime::createFromFormat( 'Y-m-d', $sd );
                $date_str = $d ? $d->format( 'd.m.Y' ) : $sd;
            }
        }

        fputcsv( $out, [
            $reg['firstname'],
            $reg['lastname'],
            $reg['email'],
            $reg['phone'] ?: '',
            $reg['company'] ?? '',
            $event_title,
            $date_str,
            $status_map[ $reg['status'] ] ?? $reg['status'],
            date_i18n( 'd.m.Y H:i', $reg['created_at'] ),
            $reg['notes'] ?: '',
        ], ';' );
    }

    fclose( $out );
    exit;
} );
