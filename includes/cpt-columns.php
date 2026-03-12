<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// 1. Spalten definieren
// ─────────────────────────────────────────────
add_filter( 'manage_training_event_posts_columns', function ( $cols ) {
    return array(
        'cb'           => $cols['cb'],
        'title'        => 'Event',
        'tc_type'      => 'Typ',
        'tc_date'      => 'Datum',
        'tc_time'      => 'Uhrzeit',
        'tc_location'  => 'Ort',
        'tc_trainer'   => 'Leitung',
        'tc_price'     => 'Preis',
        'tc_registrations' => 'Anmeldungen',
        'tc_status'    => 'Status',
        'date'         => 'Erstellt',
    );
} );

// ─────────────────────────────────────────────
// 2. Spalteninhalte ausgeben
// ─────────────────────────────────────────────
add_action( 'manage_training_event_posts_custom_column', function ( $col, $post_id ) {
    global $wpdb;

    $today = date( 'Y-m-d' );

    switch ( $col ) {

        case 'tc_type':
            $type  = get_field( 'event_type', $post_id ) ?: 'training';
            $label = $type === 'seminar' ? 'Seminar' : 'Gruppentraining';
            $color = $type === 'seminar' ? 'seminar' : 'training';
            echo '<span class="tc-col-badge tc-col-badge--' . $color . '">' . esc_html( $label ) . '</span>';
            break;

        case 'tc_date':
            $start      = get_field( 'start_date', $post_id ); // Y-m-d
            $end        = get_field( 'end_date',   $post_id );
            $recurring  = (bool) get_field( 'is_recurring',     $post_id );
            $until      = get_field( 'recurring_until', $post_id );

            if ( ! $start ) { echo '<span class="tc-col-muted">–</span>'; break; }

            $d = DateTime::createFromFormat( 'Y-m-d', $start );
            echo '<span class="tc-col-date">' . esc_html( $d->format( 'd.m.Y' ) ) . '</span>';

            if ( $end && $end !== $start ) {
                $de = DateTime::createFromFormat( 'Y-m-d', $end );
                echo '<br><span class="tc-col-muted" style="font-size:11px;">bis ' . esc_html( $de->format( 'd.m.Y' ) ) . '</span>';
            }

            if ( $recurring && $until ) {
                $du = DateTime::createFromFormat( 'Y-m-d', $until );
                echo '<br><span class="tc-col-recurring">🔁 wöchentlich bis ' . esc_html( $du->format( 'd.m.Y' ) ) . '</span>';
            }
            break;

        case 'tc_time':
            $start_time = get_field( 'start_time', $post_id );
            $end_time   = get_field( 'end_time',   $post_id );
            if ( $start_time ) {
                echo '<span class="tc-col-time">' . esc_html( $start_time ) . '</span>';
                if ( $end_time ) echo '<br><span class="tc-col-muted" style="font-size:11px;">bis ' . esc_html( $end_time ) . '</span>';
            } else {
                echo '<span class="tc-col-muted">–</span>';
            }
            break;

        case 'tc_location':
            $loc = wp_strip_all_tags( get_field( 'location', $post_id ) ?: '' );
            if ( $loc ) {
                echo '<span class="tc-col-location" title="' . esc_attr( $loc ) . '">' . esc_html( $loc ) . '</span>';
            } else {
                echo '<span class="tc-col-muted">–</span>';
            }
            break;

        case 'tc_trainer':
            $trainer = get_field( 'seminar_leadership', $post_id );
            echo $trainer
                ? '<span class="tc-col-trainer">' . esc_html( $trainer ) . '</span>'
                : '<span class="tc-col-muted">–</span>';
            break;

        case 'tc_price':
            $on_request = get_field( 'price_on_request', $post_id );
            $price      = get_field( 'normal_preis',     $post_id );
            $eb         = get_field( 'early_bird',       $post_id );
            $eb_price   = $eb['early_bird_preis'] ?? null;
            $eb_until   = $eb['anmeldung']        ?? null;

            if ( $on_request ) {
                echo '<span class="tc-col-badge tc-col-badge--request">Auf Anfrage</span>';
            } elseif ( $price ) {
                echo '<span class="tc-col-price">' . esc_html( number_format( $price, 2, ',', '.' ) ) . ' €</span>';
                if ( $eb_price && $eb_until && $eb_until >= $today ) {
                    echo '<br><span class="tc-col-badge tc-col-badge--eb">EB ' . esc_html( number_format( $eb_price, 2, ',', '.' ) ) . ' €</span>';
                }
            } else {
                echo '<span class="tc-col-muted">–</span>';
            }
            break;

        case 'tc_registrations':
            $table      = $wpdb->prefix . 'tc_registrations';
            $track      = (bool) get_field( 'track_participants', $post_id );
            $max        = (int)  get_field( 'participants',       $post_id );
            $confirmed  = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status = 'confirmed'", $post_id
            ) );
            $pending = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status = 'pending'", $post_id
            ) );
            $total = $confirmed + $pending;

            if ( $total === 0 ) {
                echo '<span class="tc-col-muted">–</span>';
                break;
            }

            echo '<a href="' . esc_url( admin_url( 'admin.php?page=training-registrations&event_id=' . $post_id ) ) . '" class="tc-col-reg-link">';
            echo '<span class="tc-col-reg-confirmed" title="Bestätigt">✓ ' . $confirmed . '</span>';
            if ( $pending ) echo ' <span class="tc-col-reg-pending" title="Ausstehend">⏳ ' . $pending . '</span>';

            if ( $track && $max > 0 ) {
                $pct     = min( 100, round( $total / $max * 100 ) );
                $bar_col = $total >= $max ? '#dc2626' : ( $pct >= 75 ? '#f59e0b' : '#059669' );
                echo '<div class="tc-col-bar-wrap"><div class="tc-col-bar" style="width:' . $pct . '%;background:' . $bar_col . ';"></div></div>';
                echo '<span class="tc-col-cap">' . $total . ' / ' . $max . '</span>';
            }
            echo '</a>';
            break;

        case 'tc_status':
            $start_date  = get_field( 'start_date', $post_id );
            $is_recurring = (bool) get_field( 'is_recurring', $post_id );
            $until        = get_field( 'recurring_until', $post_id );
            $track        = (bool) get_field( 'track_participants', $post_id );
            $max          = (int)  get_field( 'participants',       $post_id );

            $table = $wpdb->prefix . 'tc_registrations';
            $active = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status IN ('confirmed','pending')", $post_id
            ) );

            $is_full = $track && $max > 0 && $active >= $max;
            $is_past = $start_date && ! $is_recurring && $start_date < $today;
            $is_past_recurring = $is_recurring && $until && $until < $today;

            if ( get_post_status( $post_id ) === 'draft' ) {
                echo '<span class="tc-col-status tc-col-status--draft">Entwurf</span>';
            } elseif ( $is_full ) {
                echo '<span class="tc-col-status tc-col-status--full">Ausgebucht</span>';
            } elseif ( $is_past || $is_past_recurring ) {
                echo '<span class="tc-col-status tc-col-status--past">Vergangen</span>';
            } else {
                echo '<span class="tc-col-status tc-col-status--active">Aktiv</span>';
            }
            break;
    }
}, 10, 2 );

// ─────────────────────────────────────────────
// 3. Sortierbare Spalten
// ─────────────────────────────────────────────
add_filter( 'manage_edit-training_event_sortable_columns', function ( $cols ) {
    $cols['tc_date']  = 'tc_date';
    $cols['tc_type']  = 'tc_type';
    $cols['tc_price'] = 'tc_price';
    return $cols;
} );

add_action( 'pre_get_posts', function ( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'training_event' ) return;

    $orderby = $query->get( 'orderby' );

    if ( $orderby === 'tc_date' ) {
        $query->set( 'meta_key', 'start_date' );
        $query->set( 'orderby',  'meta_value' );
    }
    if ( $orderby === 'tc_type' ) {
        $query->set( 'meta_key', 'event_type' );
        $query->set( 'orderby',  'meta_value' );
    }
    if ( $orderby === 'tc_price' ) {
        $query->set( 'meta_key', 'normal_preis' );
        $query->set( 'orderby',  'meta_value_num' );
    }
} );

// Standard-Sortierung: nächste Events zuerst
add_action( 'pre_get_posts', function ( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'training_event' ) return;
    if ( $query->get( 'orderby' ) ) return; // nur wenn kein manuelles Sorting aktiv

    $query->set( 'meta_key', 'start_date' );
    $query->set( 'orderby',  'meta_value' );
    $query->set( 'order',    'ASC' );
} );

// ─────────────────────────────────────────────
// 4. Styles
// ─────────────────────────────────────────────
add_action( 'admin_head', function () {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'training_event' || $screen->base !== 'edit' ) return;
    ?>
    <style>
        /* ── Spaltenbreiten ─────────────────────────────── */
        .column-tc_type         { width: 130px; }
        .column-tc_date         { width: 160px; }
        .column-tc_time         { width: 90px; }
        .column-tc_location     { width: 130px; }
        .column-tc_trainer      { width: 130px; }
        .column-tc_price        { width: 110px; }
        .column-tc_registrations{ width: 130px; }
        .column-tc_status       { width: 100px; }
        .column-date            { width: 110px; }

        /* Zellen vertikal zentrieren */
        #the-list td { vertical-align: middle; padding: 10px 8px; }
        #the-list tr { transition: background .1s; }

        /* ── Typbadge ───────────────────────────────────── */
        .tc-col-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .03em;
            white-space: nowrap;
        }
        .tc-col-badge--training  { background: #ede9fe; color: #4f46e5; }
        .tc-col-badge--seminar   { background: #d1fae5; color: #059669; }
        .tc-col-badge--request   { background: #fef3c7; color: #92400e; }
        .tc-col-badge--eb        { background: #ecfccb; color: #3f6212; margin-top: 3px; }

        /* ── Datum / Zeit ───────────────────────────────── */
        .tc-col-date  { font-weight: 600; color: #111827; font-size: 13px; }
        .tc-col-time  { font-weight: 500; color: #374151; font-size: 13px; }
        .tc-col-recurring { font-size: 11px; color: #6366f1; display: block; margin-top: 2px; }
        .tc-col-muted { color: #9ca3af; font-size: 13px; }

        /* ── Ort / Trainer ──────────────────────────────── */
        .tc-col-location {
            display: block;
            max-width: 130px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 13px;
            color: #374151;
        }
        .tc-col-trainer { font-size: 13px; color: #374151; }

        /* ── Preis ──────────────────────────────────────── */
        .tc-col-price { font-weight: 700; font-size: 14px; color: #111827; }

        /* ── Anmeldungen ────────────────────────────────── */
        .tc-col-reg-link { text-decoration: none; display: block; }
        .tc-col-reg-confirmed { font-weight: 700; color: #059669; font-size: 13px; }
        .tc-col-reg-pending   { color: #f59e0b; font-size: 13px; }
        .tc-col-cap { font-size: 11px; color: #6b7280; display: block; margin-top: 2px; }

        .tc-col-bar-wrap {
            margin-top: 5px;
            height: 5px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }
        .tc-col-bar {
            height: 100%;
            border-radius: 999px;
            transition: width .3s;
        }

        /* ── Status ─────────────────────────────────────── */
        .tc-col-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .tc-col-status--active { background: #d1fae5; color: #065f46; }
        .tc-col-status--full   { background: #fee2e2; color: #991b1b; }
        .tc-col-status--past   { background: #f3f4f6; color: #6b7280; }
        .tc-col-status--draft  { background: #fef3c7; color: #92400e; }

        /* ── Post-Titel etwas größer ────────────────────── */
        #the-list .column-title .row-title { font-size: 14px; }
    </style>
    <?php
} );