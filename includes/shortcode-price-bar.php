<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Shortcode: [training_price_bar]
//
// Zeigt eine fixe Preisleiste am unteren Bildschirmrand.
// Liest ACF-Felder des aktuellen Posts aus.
//
// Attribute:
//   post_id      = Post-ID (default: aktueller Post)
//   link         = Anker oder URL für den CTA-Button (default: "#anmelden")
//   link_text    = Button-Text wenn Preis vorhanden (default: "Jetzt anmelden")
//   request_text = Button-Text bei "Preis auf Anfrage" (default: "Jetzt anfragen")
// ─────────────────────────────────────────────
add_shortcode( 'training_price_bar', function ( $atts ) {

    $atts = shortcode_atts( array(
        'post_id'      => get_the_ID(),
        'link'         => '#anmelden',
        'link_text'    => 'Jetzt anmelden',
        'request_text' => 'Jetzt anfragen',
    ), $atts, 'training_price_bar' );

    $post_id           = intval( $atts['post_id'] );
    $link              = esc_url( $atts['link'] );
    $link_text         = esc_html( $atts['link_text'] );
    $request_text      = esc_html( $atts['request_text'] );

    $today             = date( 'Y-m-d' );
    $price_on_request  = get_field( 'price_on_request',       $post_id );
    $request_label     = get_field( 'price_on_request_label', $post_id ) ?: 'Probetraining anfragen';
    $normal_price      = get_field( 'normal_preis',           $post_id );
    $early_bird        = get_field( 'early_bird',             $post_id );
    $early_price       = $early_bird['early_bird_preis'] ?? null;
    $price_date_string = $early_bird['anmeldung']        ?? null;
    $price_date        = $price_date_string
        ? date_create_from_format( 'Y-m-d', $price_date_string )
        : null;

    $show_early = $price_date && $early_price && $price_date_string >= $today;

    tc_enqueue_price_bar_assets();

    ob_start(); ?>
    <div class="tc-price-bar">
        <div class="tc-price-bar-inner">

            <div class="tc-price-bar-info">
                <?php if ( $price_on_request ) : ?>

                    <div class="tc-price-bar-label">Preis</div>
                    <div class="tc-price-bar-amount tc-price-bar-amount--request">
                        <?php echo esc_html( $request_label ); ?>
                    </div>

                <?php elseif ( $show_early ) : ?>

                    <div class="tc-price-bar-badge">Early Bird</div>
                    <div class="tc-price-bar-amount tc-price-bar-amount--early">
                        <?php echo esc_html( $early_price ); ?>€
                        <span>inkl. MwSt.</span>
                    </div>
                    <div class="tc-price-bar-deadline">
                        Anmeldung bis <strong><?php echo date_format( $price_date, 'd.m.Y' ); ?></strong>
                        — danach <?php echo esc_html( $normal_price ); ?>€
                    </div>

                <?php elseif ( $normal_price ) : ?>

                    <div class="tc-price-bar-label">Regulärer Preis</div>
                    <div class="tc-price-bar-amount">
                        <?php echo esc_html( $normal_price ); ?>€
                        <span>inkl. MwSt.</span>
                    </div>

                <?php endif; ?>
            </div>

            <a href="<?php echo $link; ?>"
               class="tc-price-bar-btn <?php echo ( $show_early && ! $price_on_request ) ? 'tc-price-bar-btn--early' : ''; ?>">
                <?php echo $price_on_request ? $request_text : $link_text; ?>
            </a>

        </div>
    </div>
    <?php
    return ob_get_clean();
} );

// ─────────────────────────────────────────────
// Assets Price Bar (einmalig laden)
// ─────────────────────────────────────────────
function tc_enqueue_price_bar_assets() {
    static $loaded = false;
    if ( $loaded ) return;
    $loaded = true;

    wp_enqueue_style(
        'tc-price-bar',
        TC_URL . 'assets/css/price-bar.css',
        array(),
        TC_VERSION
    );
}