<?php
defined( 'ABSPATH' ) || exit;

get_header();

if ( ! have_posts() ) {
    get_footer();
    return;
}

the_post();
$post_id    = get_the_ID();
$dark_class = tc_dark_class();

// ── Felder lesen ──────────────────────────────────────────
$date_type         = get_field( 'event_date_type',   $post_id ) ?: 'single';
$event_description = get_field( 'event_description', $post_id ) ?: '';
$location_raw      = get_field( 'location',          $post_id ) ?: '';
$leadership        = get_field( 'event_host',         $post_id ) ?: '';
$event_type        = get_field( 'event_type',         $post_id ) ?: '';
$difficulty        = get_field( 'difficulty',         $post_id ) ?: '';

$price_type       = get_field( 'event_price_type', $post_id ) ?: 'fixed';
$price_on_request = ( $price_type === 'request' );
$normal_price     = (float) get_field( 'event_price', $post_id );
$eb_group         = get_field( 'early_bird', $post_id );
$eb_price         = $eb_group ? (float) ( $eb_group['early_bird_preis'] ?? 0 ) : 0;
$eb_deadline      = $eb_group ? ( $eb_group['anmeldung'] ?? '' ) : '';
$today            = current_time( 'Y-m-d' );
$eb_active        = $eb_price > 0 && $eb_deadline && $today <= $eb_deadline;

// Kategorie
$cat      = tc_get_category( $event_type );
$cat_name = $cat ? $cat['name'] : $event_type;
$cat_col  = $cat ? $cat['color'] : '#4f46e5';

// ── Datum / Zeit ──────────────────────────────────────────
$months   = [ '', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' ];
$weekdays = [ 'So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa' ];

$date_lines = array();

if ( $date_type === 'recurring' ) {
    $sd    = get_field( 'start_date', $post_id );
    $st    = get_field( 'start_time', $post_id );
    $et    = get_field( 'end_time',   $post_id );
    $until = get_field( 'recurring_until', $post_id );
    $wday  = get_field( 'recurring_weekday', $post_id );

    if ( $sd ) {
        $d     = new DateTime( $sd );
        $label = $weekdays[ (int) $d->format( 'w' ) ] . ', '
               . (int) $d->format( 'j' ) . '. ' . $months[ (int) $d->format( 'n' ) ]
               . ' ' . $d->format( 'Y' );
        if ( $st ) $label .= ' · ' . $st . ' Uhr';
        if ( $et ) $label .= ' – ' . $et . ' Uhr';
        $date_lines[] = $label;

        if ( $until ) {
            $u          = new DateTime( $until );
            $date_lines[] = 'Wiederholt sich bis ' . (int) $u->format( 'j' ) . '. '
                          . $months[ (int) $u->format( 'n' ) ] . ' ' . $u->format( 'Y' );
        }
    }
} else {
    $rows = get_field( 'event_dates', $post_id ) ?: array();
    foreach ( $rows as $row ) {
        $sd = $row['date_start'] ?? '';
        $ed = $row['date_end']   ?? '';
        $st = $row['time_start'] ?? '';
        $et = $row['time_end']   ?? '';

        if ( ! $sd ) continue;
        $d     = new DateTime( $sd );
        $label = $weekdays[ (int) $d->format( 'w' ) ] . ', '
               . (int) $d->format( 'j' ) . '. ' . $months[ (int) $d->format( 'n' ) ]
               . ' ' . $d->format( 'Y' );

        if ( $ed && $ed !== $sd ) {
            $de    = new DateTime( $ed );
            $label .= ' – ' . (int) $de->format( 'j' ) . '. '
                    . $months[ (int) $de->format( 'n' ) ]
                    . ( $de->format( 'Y' ) !== $d->format( 'Y' ) ? ' ' . $de->format( 'Y' ) : '' );
        }
        if ( $st ) {
            $label .= ' · ' . $st . ' Uhr';
            if ( $et ) $label .= ' – ' . $et . ' Uhr';
        }
        if ( ! empty( $row['notes'] ) ) $label .= ' (' . esc_html( $row['notes'] ) . ')';
        $date_lines[] = $label;
    }
}

?>
<div class="tc-single-event <?php echo esc_attr( $dark_class ); ?>">

    <!-- ── Hero ───────────────────────────────────────────── -->
    <div class="tc-se-hero">
        <?php if ( has_post_thumbnail() ) : ?>
        <div class="tc-se-hero-image">
            <?php the_post_thumbnail( 'large' ); ?>
        </div>
        <?php endif; ?>

        <div class="tc-se-hero-content">
            <?php if ( $cat_name ) : ?>
            <span class="tc-se-badge" style="background:<?php echo esc_attr( $cat_col . '22' ); ?>;color:<?php echo esc_attr( $cat_col ); ?>;">
                <span class="tc-se-badge-dot" style="background:<?php echo esc_attr( $cat_col ); ?>;"></span>
                <?php echo esc_html( $cat_name ); ?>
            </span>
            <?php endif; ?>
            <h1 class="tc-se-title"><?php the_title(); ?></h1>
            <?php if ( $difficulty ) : ?>
            <p class="tc-se-difficulty"><?php echo esc_html( $difficulty ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Body: Content + Sidebar ──────────────────────────── -->
    <div class="tc-se-body">

        <!-- Links: Beschreibung + Editor-Inhalt -->
        <div class="tc-se-main">

            <?php if ( $event_description ) : ?>
            <div class="tc-se-intro">
                <?php echo wp_kses_post( wpautop( $event_description ) ); ?>
            </div>
            <?php endif; ?>

            <?php
            $content = get_the_content();
            if ( $content ) :
            ?>
            <div class="tc-se-content">
                <?php echo apply_filters( 'the_content', $content ); ?>
            </div>
            <?php endif; ?>

            <!-- Anmeldeformular / Kontakt-Button -->
            <?php
            $reg_mode = get_field( 'registration_mode', $post_id ) ?: 'open';
            if ( $reg_mode !== 'none' && shortcode_exists( 'time_registration' ) ) :
            ?>
            <div class="tc-se-registration">
                <?php echo do_shortcode( '[time_registration event_id="' . $post_id . '"]' ); ?>
            </div>
            <?php endif; ?>

        </div><!-- .tc-se-main -->

        <!-- Rechts: Info-Box -->
        <aside class="tc-se-sidebar">
            <div class="tc-se-info-box">

                <?php if ( ! empty( $date_lines ) ) : ?>
                <div class="tc-se-info-section">
                    <div class="tc-se-info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div class="tc-se-info-content">
                        <strong>Datum</strong>
                        <?php foreach ( $date_lines as $dl ) : ?>
                        <p><?php echo esc_html( $dl ); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $location_raw ) : ?>
                <div class="tc-se-info-section">
                    <div class="tc-se-info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div class="tc-se-info-content">
                        <strong>Ort</strong>
                        <div class="tc-se-location"><?php echo wp_kses_post( $location_raw ); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $leadership ) : ?>
                <div class="tc-se-info-section">
                    <div class="tc-se-info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <div class="tc-se-info-content">
                        <strong>Leitung</strong>
                        <p><?php echo esc_html( $leadership ); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $price_on_request ) : ?>
                <div class="tc-se-info-section">
                    <div class="tc-se-info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div class="tc-se-info-content">
                        <strong>Preis</strong>
                        <p>Auf Anfrage</p>
                    </div>
                </div>
                <?php elseif ( $normal_price > 0 ) : ?>
                <div class="tc-se-info-section">
                    <div class="tc-se-info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div class="tc-se-info-content">
                        <strong>Preis</strong>
                        <?php if ( $eb_active ) : ?>
                            <p class="tc-se-price-eb">
                                <?php echo number_format( $eb_price, 2, ',', '.' ); ?> €
                                <span class="tc-se-price-eb-badge">Early Bird</span>
                            </p>
                            <p class="tc-se-price-regular tc-se-price-struck">
                                Regulär: <?php echo number_format( $normal_price, 2, ',', '.' ); ?> €
                            </p>
                            <p class="tc-se-price-hint">
                                Early-Bird-Preis gültig bis <?php
                                    $eb_d = new DateTime( $eb_deadline );
                                    echo (int) $eb_d->format( 'j' ) . '. ' . $months[ (int) $eb_d->format( 'n' ) ] . ' ' . $eb_d->format( 'Y' );
                                ?>
                            </p>
                        <?php else : ?>
                            <p class="tc-se-price-regular">
                                <?php echo number_format( $normal_price, 2, ',', '.' ); ?> €
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- .tc-se-info-box -->
        </aside><!-- .tc-se-sidebar -->

    </div><!-- .tc-se-body -->

</div><!-- .tc-single-event -->

<?php get_footer(); ?>
