<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Shortcode: [time_price_bar]
//
// Zeigt eine fixe Preisleiste am unteren Bildschirmrand.
// Liest ACF-Felder des aktuellen Posts aus.
//
// Attribute:
//   post_id   = Post-ID (default: aktueller Post)
//   link      = Anker oder URL fuer den CTA-Button (default: "#anmelden")
//   link_text = Button-Text wenn Preis vorhanden (default: "Jetzt anmelden")
//
// Bei aktiviertem Probetraining-Modus (price_on_request) wird ein
// fester Teaser und der Button "Probetraining anfragen" angezeigt.
// ─────────────────────────────────────────────
add_shortcode( 'time_price_bar', function ( $atts ) {

    $atts = shortcode_atts( array(
        'post_id'   => get_the_ID(),
        'link'      => '#anmelden',
        'link_text' => 'Jetzt anmelden',
    ), $atts, 'time_price_bar' );

    $post_id   = intval( $atts['post_id'] );
    $link      = esc_url( $atts['link'] );
    $link_text = esc_html( $atts['link_text'] );

    $today        = date( 'Y-m-d' );
    $price_type   = get_field( 'event_price_type', $post_id ) ?: 'fixed';
    $normal_price = get_field( 'event_price',      $post_id );
    $early_bird   = get_field( 'early_bird',       $post_id );
    $early_price  = $early_bird['early_bird_preis'] ?? null;
    $price_date_str = $early_bird['anmeldung']      ?? null;
    $price_date     = $price_date_str
        ? date_create_from_format( 'Y-m-d', $price_date_str )
        : null;

    // Kapazitaetspruefung
    $track_participants = get_field( 'registration_limit', $post_id );
    $max_participants   = get_field( 'max_participants',   $post_id );
    $is_full            = false;

    if ( $track_participants && $max_participants ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tc_registrations';
        $current_registrations = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE event_id = %d AND status IN ('pending', 'confirmed')",
            $post_id
        ) );
        $is_full = $current_registrations >= $max_participants;
    }

    $show_early = $price_date && $early_price && $price_date_str >= $today;
    $has_price  = $price_type === 'fixed' && $normal_price;

    // Dark/Light Mode Klasse
    $dark_class = tc_dark_class();

    tc_enqueue_price_bar_assets();

    ob_start(); ?>
    <div class="tc-price-bar-wrapper <?php echo esc_attr( $dark_class ); ?>">
        <div class="tc-price-bar">
            <div class="tc-price-bar-inner">

                <div class="tc-price-bar-info">
                    <?php if ( $is_full ) : ?>

                        <div class="tc-price-bar-label tc-price-bar-label--full">Ausgebucht</div>
                        <div class="tc-price-bar-amount tc-price-bar-amount--full">
                            Leider keine Pl&auml;tze mehr verf&uuml;gbar.
                        </div>

                    <?php elseif ( $price_type === 'free' ) : ?>

                        <div class="tc-price-bar-label">Preis</div>
                        <div class="tc-price-bar-amount">Kostenlos</div>

                    <?php elseif ( $price_type === 'request' ) : ?>

                        <div class="tc-price-bar-teaser">
                            <strong>Neugierig geworden?</strong>
                            Dann melde dich jetzt f&uuml;r ein kostenloses Probetraining an.
                        </div>

                    <?php elseif ( $show_early ) : ?>

                        <div class="tc-price-bar-badge">Early Bird</div>
                        <div class="tc-price-bar-amount tc-price-bar-amount--early">
                            <?php echo esc_html( $early_price ); ?>&euro;
                            <span>inkl. MwSt.</span>
                        </div>
                        <div class="tc-price-bar-deadline">
                            Anmeldung bis <strong><?php echo date_format( $price_date, 'd.m.Y' ); ?></strong>
                            &mdash; danach <?php echo esc_html( $normal_price ); ?>&euro;
                        </div>

                    <?php else : ?>

                        <div class="tc-price-bar-label">Regul&auml;rer Preis</div>
                        <div class="tc-price-bar-amount">
                            <?php echo esc_html( $normal_price ); ?>&euro;
                            <span>inkl. MwSt.</span>
                        </div>

                    <?php endif; ?>
                </div>

                <a href="<?php echo $link; ?>"
                   class="tc-price-bar-btn <?php echo ( $show_early && $has_price ) ? 'tc-price-bar-btn--early' : ''; ?>"
                   <?php echo $is_full ? 'style="pointer-events:none;opacity:.5;cursor:not-allowed;"' : ''; ?>>
                    <?php
                    if ( $is_full )                        echo 'Ausgebucht';
                    elseif ( $price_type === 'request' )   echo 'Probetraining anfragen';
                    else                                   echo $link_text;
                    ?>
                </a>

            </div><!-- /.tc-price-bar-inner -->
        </div><!-- /.tc-price-bar -->
    </div><!-- /.tc-price-bar-wrapper -->
    <?php
    return ob_get_clean();
} );

// ─────────────────────────────────────────────
// Assets Price Bar – frueh einreihen damit
// Oxygen Builder die Styles im <head> ausgibt.
// ─────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'tc-price-bar',
        TC_URL . 'assets/css/frontend/price-bar.css',
        array( 'tc-design-system' ),
        TC_VERSION
    );
} );

function tc_enqueue_price_bar_assets() {
    // Leer – Styles werden bereits via wp_enqueue_scripts geladen.
    // Funktion bleibt fuer Rueckwaertskompatibilitaet erhalten.
}
