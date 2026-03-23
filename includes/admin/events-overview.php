<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Admin-Menüseite registrieren
// ─────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=time_event',
        'Event-Übersicht',
        'Übersicht',
        'administrator',
        'training-events-overview',
        'tc_render_events_overview_page'
    );
} );

// ─────────────────────────────────────────────
// Seite rendern
// ─────────────────────────────────────────────
function tc_render_events_overview_page() {
    if ( ! current_user_can( 'administrator' ) ) wp_die( 'Keine Berechtigung.' );

    global $wpdb;
    $table = $wpdb->prefix . 'tc_registrations';

    // Alle Events laden — Sortierung nach erstem Repeater-Datum in PHP
    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft' ),
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    usort( $posts, function ( $a, $b ) {
        $da = tc_get_first_event_date( $a->ID )['date_start'] ?? '';
        $db = tc_get_first_event_date( $b->ID )['date_start'] ?? '';
        return strcmp( $da ?: '9999-12-31', $db ?: '9999-12-31' );
    } );

    // Anmeldezahlen für alle Events in einer Query laden
    $counts_raw = $wpdb->get_results(
        "SELECT event_id,
                COUNT(*) AS total,
                SUM(status = 'confirmed') AS confirmed,
                SUM(status = 'pending')   AS pending,
                SUM(status = 'cancelled') AS cancelled
         FROM {$table}
         GROUP BY event_id",
        ARRAY_A
    );

    $counts = array();
    foreach ( $counts_raw as $row ) {
        $counts[ $row['event_id'] ] = $row;
    }

    $today = date( 'Y-m-d' );
    ?>
    <div class="wrap tc-overview-wrap">
        <h1>
            <span class="dashicons dashicons-calendar-alt" style="color:#4f46e5;font-size:1.5rem;width:auto;height:auto;"></span>
            Event-Übersicht
        </h1>

        <?php if ( empty( $posts ) ) : ?>
            <p>Noch keine Events vorhanden.</p>
        <?php else : ?>

        <table class="wp-list-table widefat striped tc-overview-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Typ</th>
                    <th>Datum</th>
                    <th>Ort</th>
                    <th style="text-align:center;">Anmeldungen</th>
                    <th style="text-align:center;">Kapazität</th>
                    <th style="text-align:center;">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $posts as $post ) :
                $type        = get_field( 'event_type',  $post->ID ) ?: 'training';
                $location    = wp_strip_all_tags( get_field( 'location', $post->ID ) ?: '–' );
                $max_p       = (int) get_field( 'participants',       $post->ID );
                $track_p     = (bool) get_field( 'track_participants', $post->ID );
                $date_type_v = get_field( 'event_date_type', $post->ID ) ?: 'single';
                $is_recurring = $date_type_v === 'recurring';

                // Datum formatieren – ausschließlich aus event_dates Repeater
                $event_dates_raw = get_field( 'event_dates', $post->ID );
                $date_str        = '–';

                if ( ! empty( $event_dates_raw ) && is_array( $event_dates_raw ) ) {
                    $upcoming = array_filter( $event_dates_raw, fn( $r ) => ! empty( $r['date_start'] ) && $r['date_start'] >= $today );
                    $count    = count( $event_dates_raw );
                    if ( ! empty( $upcoming ) ) {
                        $next  = reset( $upcoming );
                        $d_obj = DateTime::createFromFormat( 'Y-m-d', $next['date_start'] );
                        $date_str = $d_obj ? $d_obj->format( 'd.m.Y' ) : $next['date_start'];
                        if ( ! empty( $next['time_start'] ) ) $date_str .= ' ' . $next['time_start'] . ' Uhr';
                        if ( ! empty( $next['date_end'] ) && $next['date_end'] !== $next['date_start'] ) {
                            $d_end    = DateTime::createFromFormat( 'Y-m-d', $next['date_end'] );
                            $date_str .= ' – ' . ( $d_end ? $d_end->format( 'd.m.Y' ) : $next['date_end'] );
                        }
                        $date_str .= ' <span style="font-size:11px;color:#6b7280;">(' . $count . ' Termin' . ( $count !== 1 ? 'e' : '' ) . ' gesamt)</span>';
                    } else {
                        $date_str = '<span style="color:#9ca3af;">Alle ' . $count . ' Termine vergangen</span>';
                    }
                } elseif ( $is_recurring ) {
                    $start_date = get_field( 'start_date', $post->ID );
                    $start_time = get_field( 'start_time', $post->ID );
                    if ( $start_date ) {
                        $d        = DateTime::createFromFormat( 'Y-m-d', $start_date );
                        $date_str = $d ? $d->format( 'd.m.Y' ) : $start_date;
                        if ( $start_time ) $date_str .= ' ' . $start_time . ' Uhr';
                        $until     = get_field( 'recurring_until', $post->ID );
                        $date_str .= $until ? ' 🔁 bis ' . DateTime::createFromFormat('Y-m-d', $until)->format('d.m.Y') : ' 🔁';
                    }
                }

                // Anmeldezahlen
                $c         = $counts[ $post->ID ] ?? array( 'total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0 );
                $active    = (int) $c['confirmed'] + (int) $c['pending'];
                $confirmed = (int) $c['confirmed'];
                $pending   = (int) $c['pending'];

                // Kapazitäts-Anzeige
                $cap_html = '–';
                $is_full  = false;
                if ( $track_p && $max_p > 0 ) {
                    $pct     = min( 100, round( $active / $max_p * 100 ) );
                    $is_full = $active >= $max_p;
                    $bar_col = $is_full ? '#dc2626' : ( $pct >= 75 ? '#f59e0b' : '#059669' );
                    $cap_html = '<div style="display:flex;align-items:center;gap:8px;justify-content:center;">'
                              . '<div style="flex:1;min-width:80px;background:#e5e7eb;border-radius:999px;height:8px;overflow:hidden;">'
                              . '<div style="width:' . $pct . '%;height:100%;background:' . $bar_col . ';border-radius:999px;transition:width .3s;"></div>'
                              . '</div>'
                              . '<span style="font-size:12px;white-space:nowrap;color:#374151;">' . $active . ' / ' . $max_p . '</span>'
                              . '</div>';
                } elseif ( $active > 0 ) {
                    $cap_html = '<span style="font-size:13px;color:#374151;">' . $active . ' angemeldet</span>';
                }

                // Event-Status
                $first_ed  = tc_get_first_event_date( $post->ID );
                $first_date = $first_ed['date_start'] ?? '';
                $is_past    = $first_date && $first_date < $today && ! $is_recurring;
                $status_badge = '';
                if ( $post->post_status === 'draft' ) {
                    $status_badge = '<span class="tc-badge tc-badge-draft">Entwurf</span>';
                } elseif ( $is_full ) {
                    $status_badge = '<span class="tc-badge tc-badge-full">Ausgebucht</span>';
                } elseif ( $is_past ) {
                    $status_badge = '<span class="tc-badge tc-badge-past">Vergangen</span>';
                } else {
                    $status_badge = '<span class="tc-badge tc-badge-active">Aktiv</span>';
                }
            ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" style="font-weight:600;">
                            <?php echo esc_html( $post->post_title ); ?>
                        </a>
                    </td>
                    <td>
                        <?php
                        $cat      = tc_get_category( $type );
                        $cat_name = $cat ? $cat['name'] : esc_html( $type );
                        $cat_col  = $cat ? $cat['color'] : '#4f46e5';
                        $cat_bg   = $cat_col . '22'; // transparent tint
                        ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;
                                     padding:2px 10px;border-radius:999px;
                                     background:<?php echo esc_attr( $cat_bg ); ?>;
                                     color:<?php echo esc_attr( $cat_col ); ?>;">
                            <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;
                                         background:<?php echo esc_attr( $cat_col ); ?>;"></span>
                            <?php echo esc_html( $cat_name ); ?>
                        </span>
                    </td>
                    <td style="font-size:13px;"><?php echo esc_html( $date_str ); ?></td>
                    <td style="font-size:13px;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo esc_attr( $location ); ?>">
                        <?php echo esc_html( $location ); ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ( $active > 0 ) : ?>
                            <div style="font-size:13px;line-height:1.6;">
                                <span style="color:#059669;font-weight:600;">✓ <?php echo $confirmed; ?></span>
                                <?php if ( $pending > 0 ) : ?>
                                    &nbsp;<span style="color:#f59e0b;">⏳ <?php echo $pending; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <span style="color:#9ca3af;font-size:13px;">–</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;min-width:140px;"><?php echo $cap_html; ?></td>
                    <td style="text-align:center;"><?php echo $status_badge; ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ( $active > 0 ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=training-registrations&event_id=' . $post->ID ) ); ?>"
                               class="button button-small">
                                Anmeldungen ansehen
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"
                           class="button button-small">Bearbeiten</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>
    </div>

    <style>
        .tc-overview-wrap h1 { display:flex; align-items:center; gap:10px; }
        .tc-overview-table td { vertical-align: middle; padding: 10px 12px; }
        .tc-overview-table th { padding: 10px 12px; }

        /* category type badges: colors inline-generated from DB */

        .tc-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .tc-badge-active { background:#d1fae5; color:#065f46; }
        .tc-badge-full   { background:#fee2e2; color:#991b1b; }
        .tc-badge-past   { background:#f3f4f6; color:#6b7280; }
        .tc-badge-draft  { background:#fef3c7; color:#92400e; }
    </style>
    <?php
}
