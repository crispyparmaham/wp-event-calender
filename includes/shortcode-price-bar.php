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
        'post_id'         => get_the_ID(),
        'link'            => '#anmelden',
        'link_text'       => 'Jetzt anmelden',
        'request_text'    => 'Jetzt anfragen',
        'no_price_text'   => 'Probetraining anfragen',
        'no_price_teaser' => 'Probier’s aus – ganz entspannt, ohne Verpflichtungen.',
    ), $atts, 'training_price_bar' );

    $post_id           = intval( $atts['post_id'] );
    $link              = esc_url( $atts['link'] );
    $link_text         = esc_html( $atts['link_text'] );
    $request_text      = esc_html( $atts['request_text'] );
    $no_price_text     = esc_html( $atts['no_price_text'] );
    $no_price_teaser   = esc_html( $atts['no_price_teaser'] );

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

    // Kapazitätsprüfung
    $track_participants = get_field( 'track_participants', $post_id );
    $max_participants = get_field( 'participants', $post_id );
    $is_full = false;

    if ( $track_participants && $max_participants ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tc_registrations';
        $current_registrations = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE event_id = %d AND status IN ('pending', 'confirmed')",
            $post_id
        ) );
        $is_full = $current_registrations >= $max_participants;
    }

    $show_early  = $price_date && $early_price && $price_date_string >= $today;
    $has_price   = ! $price_on_request && $normal_price;

    // Dark/Light Mode Klasse
    $calendar_mode = tc_get_setting( 'calendar_mode', 'light' );
    $dark_class = ( $calendar_mode === 'dark' ) ? 'tc-dark' : '';

    tc_enqueue_price_bar_assets();

    ob_start(); ?>
    <div class="tc-price-bar-wrapper <?php echo esc_attr( $dark_class ); ?>">
        <div class="tc-price-bar">
            <div class="tc-price-bar-inner">

                <div class="tc-price-bar-info">
                    <?php if ( $is_full ) : ?>

                        <div class="tc-price-bar-label tc-price-bar-label--full">Ausgebucht</div>
                        <div class="tc-price-bar-amount tc-price-bar-amount--full">
                            Leider keine Plätze mehr verfügbar.
                        </div>

                    <?php elseif ( $price_on_request ) : ?>

                        <div class="tc-price-bar-label">Preis</div>
                        <div class="tc-price-bar-amount tc-price-bar-amount--request">
                            <?php echo esc_html( $request_label ); ?>
                        </div>

                    <?php elseif ( ! $has_price ) : ?>

                        <div class="tc-price-bar-teaser">
                            <strong>Neugierig geworden?</strong>
                            <?php echo $no_price_teaser; ?>
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

                    <?php else : ?>

                        <div class="tc-price-bar-label">Regulärer Preis</div>
                        <div class="tc-price-bar-amount">
                            <?php echo esc_html( $normal_price ); ?>€
                            <span>inkl. MwSt.</span>
                        </div>

                    <?php endif; ?>
                </div>

                <a href="<?php echo $link; ?>"
                   class="tc-price-bar-btn <?php echo ( $show_early && $has_price ) ? 'tc-price-bar-btn--early' : ''; ?>"
                   <?php echo $is_full ? 'style="pointer-events:none;opacity:.5;cursor:not-allowed;"' : ''; ?>>
                    <?php
                    if ( $is_full )           echo 'Ausgebucht';
                    elseif ( $price_on_request )  echo $request_text;
                    elseif ( ! $has_price )   echo $no_price_text;
                    else                      echo $link_text;
                    ?>
                </a>

            </div><!-- /.tc-price-bar-inner -->
        </div><!-- /.tc-price-bar -->
    </div><!-- /.tc-price-bar-wrapper -->
    <?php
    return ob_get_clean();
} );

// ─────────────────────────────────────────────
// Assets Price Bar – früh einreihen damit
// Oxygen Builder die Styles im <head> ausgibt.
// ─────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'tc-price-bar',
        TC_URL . 'assets/css/price-bar.css',
        array(),
        TC_VERSION
    );
} );

function tc_enqueue_price_bar_assets() {
    // Leer – Styles werden bereits via wp_enqueue_scripts geladen.
    // Funktion bleibt für Rückwärtskompatibilität erhalten.
}