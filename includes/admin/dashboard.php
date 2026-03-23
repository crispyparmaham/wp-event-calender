<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────
// Menü: Dashboard als erste Unterseite eintragen (priority 5,
// damit es vor den automatisch generierten CPT-Einträgen erscheint)
// ─────────────────────────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=time_event',
        'Dashboard',
        'Dashboard',
        'administrator',
        'time-calendar-dashboard',
        'tc_render_dashboard_page'
    );
}, 5 );

// ─────────────────────────────────────────────────────────────────
// CSS: nur auf der Dashboard-Seite laden
// ─────────────────────────────────────────────────────────────────
add_action( 'admin_head', function () {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'time-calendar-dashboard' ) return;
    ?>
    <style>
    /* ── Wrapper ──────────────────────────────────────────── */
    .tc-dash { max-width: 1400px; }
    .tc-dash h1.tc-dash-title {
        display: flex;
        align-items: center;
        gap: 9px;
        font-size: 21px;
        margin-bottom: 22px;
        color: #111827;
    }
    .tc-dash h1.tc-dash-title .dashicons { font-size: 26px; width: 26px; height: 26px; color: #4f46e5; }

    /* ── KPI-Grid ─────────────────────────────────────────── */
    .tc-kpi-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .tc-kpi-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 16px;
        display: flex;
        align-items: flex-start;
        gap: 13px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    a.tc-kpi-link { text-decoration: none; transition: box-shadow .15s, transform .15s; }
    a.tc-kpi-link:hover { box-shadow: 0 4px 14px rgba(0,0,0,.11); transform: translateY(-1px); }
    .tc-kpi-icon {
        width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
    }
    .tc-kpi-icon .dashicons { font-size: 22px; width: 22px; height: 22px; }
    .tc-kpi-value { font-size: 28px; font-weight: 700; line-height: 1.1; }
    .tc-kpi-label { font-size: 12px; color: #6b7280; margin-top: 3px; font-weight: 500; }
    .tc-kpi-sub   { font-size: 11px; color: #9ca3af; margin-top: 2px; }

    /* ── Zwei-Spalten-Grid ────────────────────────────────── */
    .tc-dash-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
        align-items: start;
    }

    /* ── Cards ────────────────────────────────────────────── */
    .tc-dash-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
        margin-bottom: 20px;
    }
    .tc-dash-card:last-child { margin-bottom: 0; }
    .tc-dash-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 13px 18px;
        border-bottom: 1px solid #f3f4f6;
        background: #fafafa;
    }
    .tc-dash-card-header h2 {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: #111827;
    }
    .tc-dash-card-link { font-size: 12px; color: #4f46e5; text-decoration: none; font-weight: 500; }
    .tc-dash-card-link:hover { text-decoration: underline; }

    /* ── Tables ───────────────────────────────────────────── */
    .tc-dash-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .tc-dash-table th {
        padding: 8px 14px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #f3f4f6;
        background: #f9fafb;
    }
    .tc-dash-table td {
        padding: 9px 14px;
        border-bottom: 1px solid #f9fafb;
        vertical-align: middle;
        color: #374151;
    }
    .tc-dash-table tr:last-child td { border-bottom: none; }
    .tc-dash-table tr:hover td { background: #fafbff; }
    .tc-title-cell { max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tc-title-cell a { color: #4f46e5; text-decoration: none; font-weight: 500; }
    .tc-title-cell a:hover { text-decoration: underline; }
    .tc-action-cell { white-space: nowrap; }
    .tc-action-cell .button { padding: 2px 7px; font-size: 12px; min-height: unset; line-height: 1.7; }

    /* ── Badges ───────────────────────────────────────────── */
    .tc-status-badge, .tc-type-badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }
    .tc-status-pending   { background: #fef3c7; color: #92400e; }
    .tc-status-confirmed { background: #d1fae5; color: #065f46; }
    .tc-status-cancelled { background: #fee2e2; color: #991b1b; }
    .tc-status-waitlist  { background: #ede9fe; color: #5b21b6; }
    .tc-type-training    { background: #ede9fe; color: #5b21b6; }
    .tc-type-seminar     { background: #dbeafe; color: #1d4ed8; }

    /* ── AJAX-Buttons ─────────────────────────────────────── */
    .tc-btn-confirm.button { background: #059669; border-color: #047857; color: #fff; font-weight: 700; }
    .tc-btn-confirm.button:hover { background: #047857; color: #fff; }
    .tc-btn-cancel.button  { background: #dc2626; border-color: #b91c1c; color: #fff; font-weight: 700; }
    .tc-btn-cancel.button:hover  { background: #b91c1c; color: #fff; }

    /* ── Chart ────────────────────────────────────────────── */
    .tc-chart-wrap { padding: 16px 18px 12px; }

    /* ── Schnellzugriff ───────────────────────────────────── */
    .tc-quick-btns { display: flex; flex-wrap: wrap; gap: 10px; padding: 16px 18px; }
    .tc-quick-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        text-decoration: none;
        color: #374151;
        font-size: 13px;
        font-weight: 500;
        transition: all .15s;
    }
    .tc-quick-btn:hover { background: #e5e7eb; color: #111827; border-color: #d1d5db; }
    .tc-quick-btn-primary { background: #4f46e5; border-color: #4338ca; color: #fff; }
    .tc-quick-btn-primary:hover { background: #4338ca; color: #fff; border-color: #4338ca; }
    .tc-quick-btn .dashicons { font-size: 16px; width: 16px; height: 16px; }

    /* ── Sonstiges ────────────────────────────────────────── */
    .tc-empty { padding: 20px 18px; color: #9ca3af; font-size: 13px; font-style: italic; margin: 0; }

    @media (max-width: 1200px) {
        .tc-kpi-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 900px) {
        .tc-dash-grid   { grid-template-columns: 1fr; }
        .tc-kpi-grid    { grid-template-columns: repeat(2, 1fr); }
    }
    </style>
    <?php
} );

// ─────────────────────────────────────────────────────────────────
// Helper: KPI-Daten
// ─────────────────────────────────────────────────────────────────
function tc_dashboard_get_kpis() {
    global $wpdb;
    $table = $wpdb->prefix . 'tc_registrations';

    // Gesamte Events (publish + draft)
    $counts       = wp_count_posts( 'time_event' );
    $events_total = ( (int) ( $counts->publish ?? 0 ) ) + ( (int) ( $counts->draft ?? 0 ) );

    // Events diesen Monat (per start_date ACF-Feld)
    $month_start       = date( 'Y-m-01' );
    $month_end         = date( 'Y-m-t' );
    $events_this_month = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT p.ID)
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type    = 'time_event'
           AND p.post_status  IN ('publish','draft')
           AND pm.meta_key    = 'start_date'
           AND pm.meta_value BETWEEN %s AND %s",
        $month_start, $month_end
    ) );

    // Gesamte Anmeldungen (confirmed + pending)
    $regs_active = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE status IN (%s,%s)",
        'confirmed', 'pending'
    ) );

    // Offene Anmeldungen (pending)
    $regs_pending = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE status = %s",
        'pending'
    ) );

    // Auslastung: Events mit registration_limit ermitteln
    $tracked_ids = get_posts( [
        'post_type'      => 'time_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [ [ 'key' => 'registration_limit', 'value' => '1' ] ],
    ] );

    $capacity_total = 0;
    $capacity_used  = 0;

    if ( ! empty( $tracked_ids ) ) {
        foreach ( $tracked_ids as $eid ) {
            $max = (int) get_field( 'max_participants', $eid );
            if ( $max > 0 ) $capacity_total += $max;
        }
        $placeholders  = implode( ',', array_fill( 0, count( $tracked_ids ), '%d' ) );
        $capacity_used = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE event_id IN ({$placeholders})
                   AND status IN ('confirmed','pending')",
                ...$tracked_ids
            )
        );
    }

    $utilization = $capacity_total > 0
        ? min( 100, round( ( $capacity_used / $capacity_total ) * 100 ) )
        : 0;

    return compact(
        'events_total', 'events_this_month',
        'regs_active', 'regs_pending',
        'utilization', 'capacity_total', 'capacity_used'
    );
}

// ─────────────────────────────────────────────────────────────────
// Helper: Nächste 5 Events
// ─────────────────────────────────────────────────────────────────
function tc_dashboard_get_next_events() {
    global $wpdb;
    $table = $wpdb->prefix . 'tc_registrations';
    $today = date( 'Y-m-d' );

    $events = get_posts( [
        'post_type'      => 'time_event',
        'post_status'    => 'publish',
        'posts_per_page' => 5,
        'meta_key'       => 'start_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [ [
            'key'     => 'start_date',
            'value'   => $today,
            'compare' => '>=',
            'type'    => 'DATE',
        ] ],
    ] );

    $result = [];
    foreach ( $events as $event ) {
        $eid        = $event->ID;
        $track_p    = get_field( 'registration_limit', $eid );
        $max_p      = (int) get_field( 'max_participants',   $eid );
        $start_date = get_field( 'start_date', $eid );
        $event_type = get_field( 'event_type',  $eid );

        $confirmed = $pending = 0;
        if ( $track_p ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT status, COUNT(*) AS cnt
                 FROM {$table}
                 WHERE event_id = %d AND status IN ('confirmed','pending')
                 GROUP BY status",
                $eid
            ) );
            foreach ( $rows as $r ) {
                if ( $r->status === 'confirmed' ) $confirmed = (int) $r->cnt;
                if ( $r->status === 'pending'   ) $pending   = (int) $r->cnt;
            }
        }

        $result[] = [
            'id'         => $eid,
            'title'      => $event->post_title,
            'type'       => $event_type ?: '',
            'start_date' => $start_date ?: '',
            'track'      => (bool) $track_p,
            'max'        => $max_p,
            'confirmed'  => $confirmed,
            'pending'    => $pending,
        ];
    }

    return $result;
}

// ─────────────────────────────────────────────────────────────────
// Helper: Letzte 8 Anmeldungen
// ─────────────────────────────────────────────────────────────────
function tc_dashboard_get_last_registrations() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}tc_registrations ORDER BY created_at DESC LIMIT 8",
        ARRAY_A
    ) ?: [];
}

// ─────────────────────────────────────────────────────────────────
// Helper: Chart-Daten der letzten 30 Tage
// ─────────────────────────────────────────────────────────────────
function tc_dashboard_get_chart_data() {
    global $wpdb;
    $since = strtotime( '-29 days midnight' );

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE(FROM_UNIXTIME(created_at)) AS day, status, COUNT(*) AS cnt
         FROM {$wpdb->prefix}tc_registrations
         WHERE created_at >= %d
           AND status IN ('confirmed','pending','cancelled')
         GROUP BY day, status
         ORDER BY day ASC",
        $since
    ), ARRAY_A );

    // 30-Tage-Array initialisieren
    $data = [];
    for ( $i = 29; $i >= 0; $i-- ) {
        $data[ date( 'Y-m-d', strtotime( "-{$i} days" ) ) ] = [
            'confirmed' => 0,
            'pending'   => 0,
            'cancelled' => 0,
        ];
    }

    foreach ( $rows as $row ) {
        if ( isset( $data[ $row['day'] ][ $row['status'] ] ) ) {
            $data[ $row['day'] ][ $row['status'] ] = (int) $row['cnt'];
        }
    }

    return $data;
}

// ─────────────────────────────────────────────────────────────────
// Helper: SVG-Balkendiagramm erzeugen (stacked bars)
// ─────────────────────────────────────────────────────────────────
function tc_dashboard_render_chart( array $data ) {
    $vw         = 900;
    $vh         = 220;
    $pad_l      = 34;
    $pad_r      = 12;
    $pad_top    = 14;
    $pad_bottom = 46; // Platz für X-Labels + Legende
    $chart_w    = $vw - $pad_l - $pad_r;
    $chart_h    = $vh - $pad_top - $pad_bottom;

    $days = array_keys( $data );
    $n    = count( $days );
    if ( $n === 0 ) return '<p style="color:#9ca3af;font-size:13px;">Keine Daten.</p>';

    $bar_w = $chart_w / $n;

    // Maximalen Tageswert ermitteln
    $max_val = 1;
    foreach ( $data as $d ) {
        $total = array_sum( $d );
        if ( $total > $max_val ) $max_val = $total;
    }
    $max_val = (int) ceil( $max_val / 5 ) * 5; // auf 5er aufrunden

    $colors = [
        'confirmed' => '#059669',
        'pending'   => '#f59e0b',
        'cancelled' => '#dc2626',
    ];

    $o  = '<svg viewBox="0 0 ' . $vw . ' ' . $vh . '" xmlns="http://www.w3.org/2000/svg"';
    $o .= ' style="width:100%;height:auto;display:block;">';

    // Hintergrund
    $o .= '<rect width="' . $vw . '" height="' . $vh . '" fill="#f8f8fc" rx="8"/>';

    // Y-Achsen-Gitterlinien & Labels (5 Schritte)
    for ( $step = 0; $step <= 5; $step++ ) {
        $val = round( $max_val / 5 * $step );
        $y   = $pad_top + $chart_h - ( $chart_h * $step / 5 );
        $o  .= '<line x1="' . $pad_l . '" y1="' . $y . '" x2="' . ( $vw - $pad_r ) . '" y2="' . $y . '"'
             . ' stroke="#e5e7eb" stroke-width="1"/>';
        $o  .= '<text x="' . ( $pad_l - 4 ) . '" y="' . ( $y + 4 ) . '"'
             . ' text-anchor="end" font-size="9" fill="#9ca3af">' . $val . '</text>';
    }

    // Balken & X-Labels
    foreach ( $days as $i => $day ) {
        $day_data = $data[ $day ];
        $bw       = max( 2, $bar_w - 2 );
        $bx       = $pad_l + ( $i * $bar_w ) + 1;
        $y_cursor = $pad_top + $chart_h; // Startet unten

        // Stapel: confirmed zuerst (unten), dann pending, dann cancelled (oben)
        foreach ( [ 'confirmed', 'pending', 'cancelled' ] as $status ) {
            $cnt = $day_data[ $status ];
            if ( $cnt <= 0 ) continue;
            $bh = ( $cnt / $max_val ) * $chart_h;
            $by = $y_cursor - $bh;
            $o .= '<rect'
                . ' x="' . round( $bx, 1 ) . '"'
                . ' y="' . round( $by, 1 ) . '"'
                . ' width="' . round( $bw, 1 ) . '"'
                . ' height="' . round( $bh, 1 ) . '"'
                . ' fill="' . $colors[ $status ] . '"'
                . ' opacity="0.85">'
                . '<title>' . date_i18n( 'd.m.', strtotime( $day ) )
                . ': ' . $cnt . ' ' . esc_attr( $status ) . '</title>'
                . '</rect>';
            $y_cursor -= $bh;
        }

        // X-Label alle 5 Tage und am letzten Tag
        if ( $i % 5 === 0 || $i === $n - 1 ) {
            $lx  = round( $bx + $bw / 2 );
            $ly  = $pad_top + $chart_h + 14;
            $d_o = DateTime::createFromFormat( 'Y-m-d', $day );
            $lbl = $d_o ? htmlspecialchars( $d_o->format( 'd.m.' ), ENT_QUOTES, 'UTF-8' ) : '';
            $o  .= '<text x="' . $lx . '" y="' . $ly . '"'
                 . ' text-anchor="middle" font-size="9" fill="#6b7280">' . $lbl . '</text>';
        }
    }

    // Legende
    $legend = [
        'Bestätigt'  => '#059669',
        'Ausstehend' => '#f59e0b',
        'Storniert'  => '#dc2626',
    ];
    $lx = $pad_l + 8;
    $ly = $vh - 10;
    foreach ( $legend as $label => $color ) {
        $o .= '<rect x="' . $lx . '" y="' . ( $ly - 8 ) . '" width="10" height="10" fill="' . htmlspecialchars( $color, ENT_QUOTES, 'UTF-8' ) . '" rx="2"/>';
        $o .= '<text x="' . ( $lx + 13 ) . '" y="' . $ly . '" font-size="9" fill="#6b7280">' . htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ) . '</text>';
        $lx += 78;
    }

    $o .= '</svg>';
    return $o;
}

// ─────────────────────────────────────────────────────────────────
// Dashboard-Seite rendern
// ─────────────────────────────────────────────────────────────────
function tc_render_dashboard_page() {
    if ( ! current_user_can( 'administrator' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    $kpis        = tc_dashboard_get_kpis();
    $next_events = tc_dashboard_get_next_events();
    $last_regs   = tc_dashboard_get_last_registrations();
    $chart_svg   = tc_dashboard_render_chart( tc_dashboard_get_chart_data() );
    $nonce       = wp_create_nonce( 'tc_admin_nonce' );

    // ── KPI-Karten-Daten ──────────────────────────────────────────
    $util_color = $kpis['utilization'] >= 80 ? '#dc2626'
                : ( $kpis['utilization'] >= 50 ? '#d97706' : '#059669' );

    $kpi_cards = [
        [
            'label' => 'Gesamte Events',
            'value' => $kpis['events_total'],
            'color' => '#4f46e5',
            'icon'  => 'dashicons-calendar-alt',
            'link'  => admin_url( 'edit.php?post_type=time_event' ),
        ],
        [
            'label' => 'Events diesen Monat',
            'value' => $kpis['events_this_month'],
            'color' => '#0891b2',
            'icon'  => 'dashicons-clock',
            'link'  => '',
        ],
        [
            'label' => 'Anmeldungen gesamt',
            'value' => $kpis['regs_active'],
            'color' => '#059669',
            'icon'  => 'dashicons-groups',
            'link'  => admin_url( 'admin.php?page=training-registrations' ),
        ],
        [
            'label' => 'Offene Anmeldungen',
            'value' => $kpis['regs_pending'],
            'color' => '#d97706',
            'icon'  => 'dashicons-warning',
            'link'  => admin_url( 'admin.php?page=training-registrations' ),
        ],
        [
            'label' => 'Auslastung gesamt',
            'value' => $kpis['utilization'] . '%',
            'sub'   => $kpis['capacity_used'] . ' / ' . $kpis['capacity_total'] . ' Pl&auml;tze',
            'color' => $util_color,
            'icon'  => 'dashicons-chart-pie',
            'link'  => '',
        ],
    ];
    ?>
    <div class="wrap tc-dash">
        <h1 class="tc-dash-title">
            <span class="dashicons dashicons-chart-area"></span>
            Drag & Drop Event Calendar &mdash; Dashboard
        </h1>

        <!-- ── Schnellzugriff ──────────────────────────────────── -->
        <div class="tc-dash-card">
            <div class="tc-dash-card-header">
                <h2>Schnellzugriff</h2>
            </div>
            <div class="tc-quick-btns">
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=time_event' ) ); ?>"
                   class="tc-quick-btn tc-quick-btn-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Neues Event anlegen
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=time_event&page=time-calendar' ) ); ?>"
                   class="tc-quick-btn">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    Kalender &ouml;ffnen
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=training-registrations' ) ); ?>"
                   class="tc-quick-btn">
                    <span class="dashicons dashicons-groups"></span>
                    Alle Anmeldungen
                </a>
                <a href="<?php echo esc_url( wp_nonce_url(
                    admin_url( 'admin.php?page=training-registrations&tc_export=csv' ),
                    'tc_export_csv', 'nonce'
                ) ); ?>" class="tc-quick-btn">
                    <span class="dashicons dashicons-download"></span>
                    CSV exportieren
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=time-calendar-settings' ) ); ?>"
                   class="tc-quick-btn">
                    <span class="dashicons dashicons-admin-settings"></span>
                    Einstellungen
                </a>
            </div>
        </div>

        <!-- ── KPI-Karten ──────────────────────────────────────── -->
        <div class="tc-kpi-grid">
            <?php foreach ( $kpi_cards as $card ) :
                $tag   = $card['link'] ? 'a' : 'div';
                $attrs = $card['link']
                    ? ' href="' . esc_url( $card['link'] ) . '" class="tc-kpi-card tc-kpi-link"'
                    : ' class="tc-kpi-card"';
                echo '<' . $tag . $attrs . '>';
            ?>
                <div class="tc-kpi-icon"
                     style="background:<?php echo esc_attr( $card['color'] ); ?>1a;
                            color:<?php echo esc_attr( $card['color'] ); ?>;">
                    <span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>"></span>
                </div>
                <div class="tc-kpi-body">
                    <div class="tc-kpi-value" style="color:<?php echo esc_attr( $card['color'] ); ?>;">
                        <?php echo esc_html( $card['value'] ); ?>
                    </div>
                    <div class="tc-kpi-label"><?php echo esc_html( $card['label'] ); ?></div>
                    <?php if ( ! empty( $card['sub'] ) ) : ?>
                        <div class="tc-kpi-sub"><?php echo wp_kses( $card['sub'], [] ); ?></div>
                    <?php endif; ?>
                </div>
            <?php echo '</' . $tag . '>'; endforeach; ?>
        </div>

        <!-- ── Zwei-Spalten-Layout ─────────────────────────────── -->
        <div class="tc-dash-grid">

            <!-- Nächste Events -->
            <div class="tc-dash-card">
                <div class="tc-dash-card-header">
                    <h2>N&auml;chste Events</h2>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=time_event' ) ); ?>"
                       class="tc-dash-card-link">Alle anzeigen &rarr;</a>
                </div>
                <?php if ( empty( $next_events ) ) : ?>
                    <p class="tc-empty">Keine bevorstehenden Events.</p>
                <?php else : ?>
                <table class="tc-dash-table">
                    <thead>
                        <tr>
                            <th>Titel</th><th>Typ</th><th>Datum</th>
                            <th>Anmeldungen</th><th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $next_events as $ev ) :
                        $d        = $ev['start_date']
                                    ? DateTime::createFromFormat( 'Y-m-d', $ev['start_date'] )
                                    : false;
                        $date_str = $d ? $d->format( 'd.m.Y' ) : '&ndash;';
                        $total    = $ev['confirmed'] + $ev['pending'];

                        if ( $ev['track'] && $ev['max'] > 0 ) {
                            $pct       = round( $total / $ev['max'] * 100 );
                            $reg_color = $pct >= 100 ? '#dc2626' : ( $pct >= 75 ? '#d97706' : '#059669' );
                            $reg_label = $total . ' / ' . $ev['max'];
                        } else {
                            $reg_color = '#6b7280';
                            $reg_label = $total;
                        }

                        $type_map   = [ 'training' => 'Training', 'seminar' => 'Seminar' ];
                        $type_label = $type_map[ $ev['type'] ] ?? ucfirst( $ev['type'] );
                    ?>
                    <tr>
                        <td class="tc-title-cell">
                            <a href="<?php echo esc_url( get_edit_post_link( $ev['id'] ) ); ?>">
                                <?php echo esc_html( $ev['title'] ); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ( $ev['type'] ) : ?>
                            <span class="tc-type-badge tc-type-<?php echo esc_attr( $ev['type'] ); ?>">
                                <?php echo esc_html( $type_label ); ?>
                            </span>
                            <?php else : ?>&ndash;<?php endif; ?>
                        </td>
                        <td><?php echo $date_str; ?></td>
                        <td>
                            <strong style="color:<?php echo esc_attr( $reg_color ); ?>;">
                                <?php echo esc_html( $reg_label ); ?>
                            </strong>
                        </td>
                        <td class="tc-action-cell">
                            <a href="<?php echo esc_url( admin_url(
                                'admin.php?page=training-registrations&event_id=' . $ev['id']
                            ) ); ?>" class="button button-small" title="Anmeldungen ansehen">&#128101;</a>
                            <a href="<?php echo esc_url( get_edit_post_link( $ev['id'] ) ); ?>"
                               class="button button-small" title="Event bearbeiten">&#9999;&#65039;</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Letzte Anmeldungen -->
            <div class="tc-dash-card">
                <div class="tc-dash-card-header">
                    <h2>Letzte Anmeldungen</h2>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=training-registrations' ) ); ?>"
                       class="tc-dash-card-link">Alle anzeigen &rarr;</a>
                </div>
                <?php if ( empty( $last_regs ) ) : ?>
                    <p class="tc-empty">Keine Anmeldungen vorhanden.</p>
                <?php else : ?>
                <table class="tc-dash-table" id="tc-dash-regs-table">
                    <thead>
                        <tr>
                            <th>Name</th><th>Event</th><th>Datum</th>
                            <th>Status</th><th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $last_regs as $reg ) :
                        $status      = $reg['status'];
                        $event_title = $reg['event_id'] ? get_the_title( $reg['event_id'] ) : '&ndash;';
                    ?>
                    <tr id="tc-dash-reg-<?php echo esc_attr( $reg['id'] ); ?>">
                        <td><strong><?php echo esc_html( $reg['firstname'] . ' ' . $reg['lastname'] ); ?></strong></td>
                        <td class="tc-title-cell"><?php echo esc_html( $event_title ); ?></td>
                        <td><?php echo esc_html( date_i18n( 'd.m.Y', $reg['created_at'] ) ); ?></td>
                        <td>
                            <span class="tc-status-badge tc-status-<?php echo esc_attr( $status ); ?>">
                                <?php echo match( $status ) {
                                    'confirmed' => '&#10003; Best&auml;tigt',
                                    'cancelled' => '&#10007; Storniert',
                                    'waitlist'  => '&#9208; Warteliste',
                                    default     => '&#9203; Ausstehend',
                                }; ?>
                            </span>
                        </td>
                        <td class="tc-action-cell">
                            <?php if ( $status !== 'confirmed' ) : ?>
                            <button class="tc-btn-confirm button button-small"
                                    data-id="<?php echo esc_attr( $reg['id'] ); ?>"
                                    data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                    title="Best&auml;tigen">&#10003;</button>
                            <?php endif; ?>
                            <?php if ( $status !== 'cancelled' ) : ?>
                            <button class="tc-btn-cancel button button-small"
                                    data-id="<?php echo esc_attr( $reg['id'] ); ?>"
                                    data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                    title="Stornieren">&#10007;</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        </div><!-- /.tc-dash-grid -->

        <!-- ── Anmeldungen der letzten 30 Tage (Chart) ────────── -->
        <div class="tc-dash-card" style="margin-bottom:20px;">
            <div class="tc-dash-card-header">
                <h2>Anmeldungen der letzten 30 Tage</h2>
            </div>
            <div class="tc-chart-wrap">
                <?php echo $chart_svg; ?>
            </div>
        </div>

    </div><!-- /.wrap.tc-dash -->

    <!-- ── AJAX für Quick-Actions ──────────────────────────────── -->
    <script>
    jQuery(function($) {
        var table = '#tc-dash-regs-table';

        function updateRow(id, newStatus, newLabel, newClass) {
            var $row = $('#tc-dash-reg-' + id);
            $row.find('.tc-status-badge')
                .attr('class', 'tc-status-badge tc-status-' + newClass)
                .html(newLabel);
            // Buttons neu setzen
            var $actions = $row.find('.tc-action-cell');
            if (newStatus === 'confirmed') {
                $actions.find('.tc-btn-confirm').remove();
            } else if (newStatus === 'cancelled') {
                $actions.find('.tc-btn-cancel').remove();
            }
        }

        $(document).on('click', table + ' .tc-btn-confirm', function() {
            var $btn = $(this), id = $btn.data('id');
            if (!confirm('Anmeldung bestätigen und Bestätigungsmail senden?')) return;
            $btn.prop('disabled', true).text('…');
            $.post(ajaxurl, {
                action: 'tc_update_registration_status',
                nonce: $btn.data('nonce'),
                registration_id: id,
                status: 'confirmed',
                send_confirmation_mail: 1
            }, function(res) {
                if (res.success) {
                    updateRow(id, 'confirmed', '&#10003; Bestätigt', 'confirmed');
                } else {
                    alert(res.data || 'Fehler.');
                    $btn.prop('disabled', false).text('✓');
                }
            });
        });

        $(document).on('click', table + ' .tc-btn-cancel', function() {
            var $btn = $(this), id = $btn.data('id');
            if (!confirm('Anmeldung stornieren?')) return;
            $btn.prop('disabled', true).text('…');
            $.post(ajaxurl, {
                action: 'tc_update_registration_status',
                nonce: $btn.data('nonce'),
                registration_id: id,
                status: 'cancelled',
                send_confirmation_mail: 1
            }, function(res) {
                if (res.success) {
                    updateRow(id, 'cancelled', '&#10007; Storniert', 'cancelled');
                } else {
                    alert(res.data || 'Fehler.');
                    $btn.prop('disabled', false).text('✗');
                }
            });
        });
    });
    </script>
    <?php
}

