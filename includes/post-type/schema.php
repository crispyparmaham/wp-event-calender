<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Schema.org JSON-LD für time_event Single-Posts
// ─────────────────────────────────────────────
add_action( 'wp_head', 'tc_output_schema_json_ld', 10 );

function tc_output_schema_json_ld() {
    if ( ! tc_get_setting( 'schema_enabled', '1' ) ) return;
    if ( ! is_singular( 'time_event' ) ) return;

    $post    = get_post();
    $post_id = $post->ID;

    // ── Basisdaten ─────────────────────────────
    $name        = get_the_title( $post_id );
    $url         = get_permalink( $post_id );
    $description = wp_strip_all_tags( get_field( 'intro_text', $post_id ) ?: wp_trim_words( $post->post_content, 40, '…' ) );
    $date_type   = get_field( 'event_date_type', $post_id ) ?: 'single';

    // ── Bild ───────────────────────────────────
    $image = '';
    if ( has_post_thumbnail( $post_id ) ) {
        $img   = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
        $image = $img ? $img[0] : '';
    }

    // ── Datum & Uhrzeit ────────────────────────
    $start_date_iso = '';
    $end_date_iso   = '';

    if ( $date_type === 'recurring' ) {
        $sd = get_field( 'start_date', $post_id );   // Y-m-d
        $st = get_field( 'start_time', $post_id );   // H:i
        $et = get_field( 'end_time', $post_id );     // H:i
        if ( $sd ) {
            $start_date_iso = $sd . 'T' . ( $st ? $st . ':00' : '00:00:00' );
            $end_date_iso   = $sd . 'T' . ( $et ? $et . ':00' : '00:00:00' );
        }
    } else {
        $first = tc_get_first_event_date( $post_id );
        $sd    = $first['date_start'] ?? '';
        $ed    = $first['date_end']   ?? '';
        $st    = $first['time_start'] ?? '';
        $et    = $first['time_end']   ?? '';
        if ( $sd ) {
            $start_date_iso = $sd . 'T' . ( $st ? $st . ':00' : '00:00:00' );
            // Enddatum: explizites date_end oder gleicher Tag mit Endzeit
            $end_day        = $ed ?: $sd;
            $end_date_iso   = $end_day . 'T' . ( $et ? $et . ':00' : '00:00:00' );
        }
    }

    if ( ! $start_date_iso ) return; // Kein Datum → kein Schema

    // ── Ort ────────────────────────────────────
    $location_raw = wp_strip_all_tags( get_field( 'location', $post_id ) ?: '' );
    $location_obj = null;
    if ( $location_raw ) {
        // Einfaches Place-Objekt; kein strukturiertes Adress-Parsing nötig
        $location_obj = array(
            '@type' => 'Place',
            'name'  => $location_raw,
        );
    }

    // ── Veranstalter / Performer ───────────────
    $site_name  = get_bloginfo( 'name' );
    $site_url   = get_bloginfo( 'url' );
    $organizer  = array(
        '@type' => 'Organization',
        'name'  => $site_name,
        'url'   => $site_url,
    );

    $leadership = get_field( 'seminar_leadership', $post_id );
    $performer  = $leadership ? array( '@type' => 'Person', 'name' => $leadership ) : null;

    // ── Preis / Angebote ───────────────────────
    $price_on_request = (bool) get_field( 'price_on_request', $post_id );
    $offers           = null;

    if ( ! $price_on_request ) {
        $normal_price = (float) get_field( 'normal_preis', $post_id );
        $eb_group     = get_field( 'early_bird', $post_id );
        $eb_price     = $eb_group ? (float) ( $eb_group['early_bird_preis'] ?? 0 ) : 0;
        $eb_deadline  = $eb_group ? ( $eb_group['anmeldung'] ?? '' ) : ''; // Y-m-d

        $today      = current_time( 'Y-m-d' );
        $eb_active  = $eb_price > 0 && $eb_deadline && $today <= $eb_deadline;

        if ( $eb_active ) {
            // Zwei Angebote: Early Bird + regulärer Preis
            $offers = array(
                array(
                    '@type'         => 'Offer',
                    'name'          => 'Early Bird',
                    'price'         => number_format( $eb_price, 2, '.', '' ),
                    'priceCurrency' => 'EUR',
                    'validThrough'  => $eb_deadline . 'T23:59:59',
                    'availability'  => 'https://schema.org/InStock',
                    'url'           => $url,
                ),
                array(
                    '@type'         => 'Offer',
                    'name'          => 'Regulär',
                    'price'         => number_format( $normal_price, 2, '.', '' ),
                    'priceCurrency' => 'EUR',
                    'availability'  => 'https://schema.org/InStock',
                    'url'           => $url,
                ),
            );
        } elseif ( $normal_price > 0 ) {
            $offers = array(
                array(
                    '@type'         => 'Offer',
                    'price'         => number_format( $normal_price, 2, '.', '' ),
                    'priceCurrency' => 'EUR',
                    'availability'  => 'https://schema.org/InStock',
                    'url'           => $url,
                ),
            );
        }
    }

    // ── Schema zusammenbauen ───────────────────
    $schema = array(
        '@context'  => 'https://schema.org',
        '@type'     => 'Event',
        'name'      => $name,
        'url'       => $url,
        'startDate' => $start_date_iso,
        'endDate'   => $end_date_iso,
        'organizer' => $organizer,
    );

    if ( $description ) {
        $schema['description'] = $description;
    }
    if ( $image ) {
        $schema['image'] = $image;
    }
    if ( $location_obj ) {
        $schema['location'] = $location_obj;
    }
    if ( $performer ) {
        $schema['performer'] = $performer;
    }
    if ( $offers ) {
        $schema['offers'] = $offers;
    }

    $json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    echo "\n<script type=\"application/ld+json\">\n" . $json . "\n</script>\n";
}
