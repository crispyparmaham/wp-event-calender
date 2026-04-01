<?php
/**
 * Shortcode [time_event_info]
 *
 * Compact info bar with event details for single event pages.
 * Layouts: bar (horizontal) | cards (tiles)
 *
 * Multi-date events show an accordion under the date item:
 * the next upcoming date is displayed prominently; further dates
 * collapse behind a toggle button.
 */
defined( 'ABSPATH' ) || exit;

// Enqueue CSS early so Oxygen Builder outputs it in <head>.
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

    // ── Collect field data ──────────────────────────────────────
    $fields     = get_fields( $event_id ) ?: array();
    $date_type  = $fields['event_date_type'] ?? 'single';
    $location   = wp_strip_all_tags( $fields['location']  ?? '' );
    $host       = $fields['event_host']  ?? '';
    $difficulty = $fields['difficulty']  ?? '';
    $track_p    = ! empty( $fields['registration_limit'] );
    $max_p      = (int) ( $fields['max_participants'] ?? 0 );

    // ── Date / time resolution ─────────────────────────────────
    $date_str      = '';
    $time_str      = '';
    $date_html     = ''; // overridden for multi-date accordion
    $is_multi_date = false;
    $all_past      = false;

    if ( $date_type === 'recurring' ) {
        $weekdays = array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' );
        $weekday  = $fields['recurring_weekday'] ?? null;
        $date_str = $weekday !== null ? 'Jeden ' . ( $weekdays[ (int) $weekday ] ?? '' ) : '';
        $ts       = $fields['recurring_time_start'] ?? '';
        $te       = $fields['recurring_time_end']   ?? '';
        if ( $ts ) {
            $time_str  = $ts;
            $time_str .= $te ? " – {$te} Uhr" : ' Uhr';
        }
    } else {
        $today_str   = wp_date( 'Y-m-d' );
        $all_dates   = $fields['event_dates'] ?? array();
        $upcoming    = array();

        foreach ( $all_dates as $ed ) {
            if ( ! empty( $ed['date_start'] ) && $ed['date_start'] >= $today_str ) {
                $upcoming[] = $ed;
            }
        }
        usort( $upcoming, fn( $a, $b ) => strcmp( $a['date_start'], $b['date_start'] ) );

        // Track whether all dates are in the past (before fallback assignment)
        $all_past = empty( $upcoming );

        // Fallback: all past → use last entry (for price/seats only; date display uses hint text)
        if ( $all_past && ! empty( $all_dates ) ) {
            $upcoming = [ $all_dates[ count( $all_dates ) - 1 ] ];
        }

        $primary = $upcoming[0] ?? [];

        if ( ! $all_past && ! empty( $primary['date_start'] ) ) {
            $date_str = tc_events_format_date( $primary['date_start'] );
        }

        $ts = $primary['time_start'] ?? '';
        $te = $primary['time_end']   ?? '';
        if ( ! $all_past && $ts ) {
            $time_str  = substr( $ts, 0, 5 );
            $time_str .= $te ? ' – ' . substr( $te, 0, 5 ) . ' Uhr' : ' Uhr';
        }

        // Multi-date: build accordion HTML when more than one upcoming date exists
        if ( ! $all_past && count( $upcoming ) > 1 ) {
            $is_multi_date   = true;
            $primary_display = esc_html( $date_str . ( $time_str ? ' · ' . $time_str : '' ) );

            $list_items = '';
            foreach ( array_slice( $upcoming, 1 ) as $ed ) {
                $d_label = tc_events_format_date( $ed['date_start'] );
                $t_start = isset( $ed['time_start'] ) ? substr( $ed['time_start'], 0, 5 ) : '';
                $t_end   = isset( $ed['time_end'] )   ? substr( $ed['time_end'],   0, 5 ) : '';
                $t_str   = $t_start ? ( $t_end ? " · {$t_start} – {$t_end} Uhr" : " · {$t_start} Uhr" ) : '';
                $list_items .= '<li>' . esc_html( $d_label . $t_str ) . '</li>';
            }

            $date_html = '<div class="tc-info-dates">'
                . '<div class="tc-info-dates__primary">'
                    . '<span class="tc-info-dates__value">' . $primary_display . '</span>'
                    . '<button class="tc-info-dates__toggle" aria-expanded="false" aria-label="Alle Termine anzeigen"></button>'
                . '</div>'
                . '<ul class="tc-info-dates__list" hidden>' . $list_items . '</ul>'
                . '</div>';
        }
    }

    // ── Seat availability ───────────────────────────────────────
    $seats_str   = '';
    $seats_class = '';
    if ( $show_seats && $track_p && $max_p > 0 ) {
        global $wpdb;
        $cur  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tc_registrations WHERE event_id = %d AND status IN ('pending','confirmed')",
            $event_id
        ) );
        $free = $max_p - $cur;
        if ( $free <= 0 ) {
            $seats_str   = tc_get_setting( 'label_sold_out', 'Ausgebucht' );
            $seats_class = 'tc-event-info-value--full';
        } else {
            $pct = ( $cur / $max_p ) * 100;
            $seats_str = "{$cur} / {$max_p}";
            if ( $pct > 80 )     $seats_class = 'tc-event-info-value--full';
            elseif ( $pct >= 50 ) $seats_class = 'tc-event-info-value--warning';
            else                  $seats_class = 'tc-event-info-value--available';
        }
    }

    // ── Build items list ────────────────────────────────────────
    $items = array();

    if ( $show_date && ( $date_str || $is_multi_date || $all_past ) ) {
        $date_label = $is_multi_date
            ? tc_get_setting( 'label_next_date', 'Nächster Termin' )
            : 'Datum';

        if ( $all_past ) {
            // All dates are in the past — show hint text instead of a date value
            $hint_html = '<span class="tc-info-no-dates">'
                . esc_html( tc_get_setting( 'label_no_upcoming_dates', 'Aktuell sind keine Termine verfügbar.' ) )
                . '</span>';
            $items[] = array(
                'icon'  => "\xF0\x9F\x93\x85",
                'label' => $date_label,
                'value' => '',
                'class' => '',
                'html'  => $hint_html,
            );
        } elseif ( $is_multi_date ) {
            // Pass the pre-built accordion HTML; rendered directly (not escaped)
            $items[] = array(
                'icon'   => "\xF0\x9F\x93\x85",
                'label'  => $date_label,
                'value'  => '',
                'class'  => '',
                'html'   => $date_html, // raw HTML accordion
            );
        } else {
            $items[] = array(
                'icon'  => "\xF0\x9F\x93\x85",
                'label' => $date_label,
                'value' => $date_str,
                'class' => '',
                'html'  => '',
            );
        }
    }

    if ( $show_time && $time_str && ! $is_multi_date ) {
        $items[] = array( 'icon' => "\xF0\x9F\x95\x90", 'label' => 'Uhrzeit', 'value' => $time_str, 'class' => '', 'html' => '' );
    }
    if ( $show_location && $location ) {
        $maps_url      = 'https://maps.google.com/?q=' . urlencode( $location );
        $location_html = '<span class="tc-event-info-value"><a href="' . esc_url( $maps_url )
            . '" target="_blank" rel="noopener noreferrer">' . esc_html( $location ) . '</a></span>';
        $items[] = array( 'icon' => "\xF0\x9F\x93\x8D", 'label' => 'Ort', 'value' => '', 'class' => '', 'html' => $location_html );
    }
    if ( $show_host && $host ) {
        $items[] = array( 'icon' => "\xF0\x9F\x91\xA4", 'label' => 'Leitung', 'value' => $host, 'class' => '', 'html' => '' );
    }
    if ( $show_audience && $difficulty ) {
        $items[] = array( 'icon' => "\xF0\x9F\x8E\xAF", 'label' => "F\xC3\xBCr wen geeignet", 'value' => $difficulty, 'class' => '', 'html' => '' );
    }
    if ( $show_seats && $seats_str ) {
        $items[] = array( 'icon' => "\xF0\x9F\x91\xA5", 'label' => "Pl\xC3\xA4tze", 'value' => $seats_str, 'class' => $seats_class, 'html' => '' );
    }

    if ( empty( $items ) ) {
        return '';
    }

    $dark_class = tc_dark_class();

    // ── Render ──────────────────────────────────────────────────
    ob_start();

    if ( $layout === 'cards' ) {
        echo '<div class="tc-event-info-wrap tc-event-info-wrap--cards' . ( $dark_class ? ' ' . $dark_class : '' ) . '">';
        echo '<div class="tc-event-info-cards">';
        foreach ( $items as $item ) {
            $val_class = 'tc-event-info-value' . ( $item['class'] ? ' ' . $item['class'] : '' );
            echo '<div class="tc-event-info-card">';
            echo '<span class="tc-event-info-icon">' . $item['icon'] . '</span>';
            echo '<span class="tc-event-info-label">' . esc_html( $item['label'] ) . '</span>';
            if ( $item['html'] ) {
                echo $item['html']; // pre-escaped accordion markup
            } else {
                echo '<span class="' . esc_attr( $val_class ) . '">' . esc_html( $item['value'] ) . '</span>';
            }
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
            if ( $item['html'] ) {
                echo $item['html']; // pre-escaped accordion markup
            } else {
                echo '<span class="' . esc_attr( $val_class ) . '">' . esc_html( $item['value'] ) . '</span>';
            }
            echo '</div></div>';
            if ( $i < $count - 1 ) {
                echo '<div class="tc-event-info-divider"></div>';
            }
        }
        echo '</div></div>';
    }

    // Inline JS — delegated toggle for the accordion (no jQuery, no external file)
    ?>
<script>
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.tc-info-dates__toggle');
        if (!btn) return;
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        var list = btn.closest('.tc-info-dates').querySelector('.tc-info-dates__list');
        if (!list) return;
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        if (expanded) {
            list.classList.remove('is-open');
            // Wait for transition then re-hide
            list.addEventListener('transitionend', function hide() {
                list.hidden = true;
                list.removeEventListener('transitionend', hide);
            });
        } else {
            list.hidden = false;
            // Force reflow so transition fires
            list.getBoundingClientRect();
            list.classList.add('is-open');
        }
    });
})();
</script>
    <?php
    return ob_get_clean();
}
