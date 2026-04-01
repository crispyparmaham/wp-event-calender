<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Shortcode: [time_price_bar]
//
// Renders a sticky price bar at the bottom of the screen.
// Layout varies by event_price_type:
//   fixed          – amount + period (no label)
//   fixed + action – badge + struck original + action price + deadline
//   free           – free text, no label
//   request        – request text, outline button
//   full           – sold-out state (overrides all price variants)
//
// Attributes:
//   post_id   – post ID (default: current post)
//   link      – anchor or URL for the CTA button (default: "#anmelden")
//   link_text – button label for paid events (default: from settings)
// ─────────────────────────────────────────────
add_shortcode( 'time_price_bar', function ( $atts ) {

	$atts = shortcode_atts( [
		'post_id'   => get_the_ID(),
		'link'      => '#anmelden',
		'link_text' => tc_get_setting( 'label_price_bar_cta', 'Jetzt anmelden' ),
	], $atts, 'time_price_bar' );

	$post_id   = intval( $atts['post_id'] );
	$link      = esc_url( $atts['link'] );
	$link_text = esc_html( $atts['link_text'] );

	$today  = wp_date( 'Y-m-d' );
	$fields = get_fields( $post_id ) ?: [];

	$price_type   = $fields['event_price_type'] ?? 'fixed';
	$normal_price = $fields['event_price']      ?? '';
	$price_from   = ! empty( $fields['price_from'] );
	$price_prefix = ( $price_type === 'fixed' && $price_from ) ? tc_get_setting( 'label_price_from', 'ab' ) . ' ' : '';

	// Action price (ACF group field)
	$action_group = $fields['action_price']             ?? [];
	$early_value  = $action_group['action_price_value'] ?? null;
	$action_until = $action_group['action_price_until'] ?? null;

	// Action price is active only while the deadline hasn't passed
	$show_action = $early_value && $action_until && $action_until >= $today;

	// Price period label
	$price_period = $fields['price_period'] ?? 'once';
	$period_label = match ( $price_period ) {
		'monthly' => tc_get_setting( 'label_period_monthly', '/ Monat' ),
		'yearly'  => tc_get_setting( 'label_period_yearly',  '/ Jahr' ),
		default   => tc_get_setting( 'label_period_once',    'einmalig' ),
	};

	// Format price amounts
	$normal_fmt = ( $normal_price !== '' && $normal_price !== false )
		? number_format( (float) $normal_price, 2, ',', '.' ) . ' €'
		: '';
	$action_fmt = $early_value
		? number_format( (float) $early_value, 2, ',', '.' ) . ' €'
		: '';

	// Capacity check
	$track_p = ! empty( $fields['registration_limit'] );
	$max_p   = (int) ( $fields['max_participants'] ?? 0 );
	$is_full = false;

	if ( $track_p && $max_p > 0 ) {
		global $wpdb;
		$cur_p = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}tc_registrations WHERE event_id = %d AND status IN ('pending','confirmed')",
			$post_id
		) );
		$is_full = $cur_p >= $max_p;
	}

	// Determine variant modifier class
	if ( $is_full ) {
		$variant = 'tc-price-bar--full';
	} elseif ( $price_type === 'free' ) {
		$variant = 'tc-price-bar--free';
	} elseif ( $price_type === 'request' ) {
		$variant = 'tc-price-bar--request';
	} elseif ( $show_action ) {
		$variant = 'tc-price-bar--fixed tc-price-bar--action';
	} else {
		$variant = 'tc-price-bar--fixed';
	}

	$dark_class = tc_dark_class();
	tc_enqueue_price_bar_assets();

	ob_start(); ?>
	<div class="tc-price-bar-wrapper <?php echo esc_attr( $dark_class ); ?>">
		<div class="tc-price-bar <?php echo esc_attr( $variant ); ?>">

			<?php if ( $is_full ) : ?>

				<div class="tc-price-bar__left">
					<span class="tc-price-bar__full-label">
						<?php echo esc_html( tc_get_setting( 'label_price_bar_full', 'Ausgebucht' ) ); ?>
					</span>
					<span class="tc-price-bar__full-sub">
						<?php echo esc_html( tc_get_setting( 'label_price_bar_full_sub', 'Leider keine Plätze mehr verfügbar.' ) ); ?>
					</span>
				</div>

			<?php elseif ( $price_type === 'free' ) : ?>

				<div class="tc-price-bar__left">
					<span class="tc-price-bar__free-text">
						<?php echo esc_html( tc_get_setting( 'label_price_free', 'Kostenlose Teilnahme' ) ); ?>
					</span>
				</div>

			<?php elseif ( $price_type === 'request' ) : ?>

				<div class="tc-price-bar__left">
					<span class="tc-price-bar__request-text">
						<?php echo esc_html( tc_get_setting( 'label_price_request', 'Preis auf Anfrage' ) ); ?>
					</span>
				</div>

			<?php elseif ( $show_action ) : ?>

				<div class="tc-price-bar__left">
					<span class="tc-price-bar__badge">
						<?php echo esc_html( tc_get_setting( 'label_price_action_badge', 'Aktionspreis' ) ); ?>
					</span>
					<div class="tc-price-bar__prices">
						<?php if ( $normal_fmt ) : ?>
							<span class="tc-price-bar__strike"><?php echo esc_html( $normal_fmt ); ?></span>
						<?php endif; ?>
						<span class="tc-price-bar__amount tc-price-bar__amount--accent">
							<?php echo esc_html( $price_prefix . $action_fmt ); ?>
						</span>
						<span class="tc-price-bar__period"><?php echo esc_html( $period_label ); ?></span>
					</div>
					<?php if ( $action_until ) : ?>
						<span class="tc-price-bar__deadline">
							<?php
							echo esc_html( tc_get_setting( 'label_price_action_until', 'Noch bis' ) )
								. ' '
								. esc_html( date_i18n( 'j. F Y', strtotime( $action_until ) ) );
							?>
						</span>
					<?php endif; ?>
				</div>

			<?php else : ?>

				<div class="tc-price-bar__left">
					<div class="tc-price-bar__prices">
						<span class="tc-price-bar__amount"><?php echo esc_html( $price_prefix . $normal_fmt ); ?></span>
						<span class="tc-price-bar__period"><?php echo esc_html( $period_label ); ?></span>
					</div>
				</div>

			<?php endif; ?>

			<a href="<?php echo $link; ?>"
			   class="tc-price-bar__btn<?php
					if ( $price_type === 'request' && ! $is_full ) echo ' tc-price-bar__btn--outline';
					if ( $is_full ) echo ' tc-price-bar__btn--disabled';
			   ?>"
			   <?php echo $is_full ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
				<?php
				if ( $is_full ) :
					echo esc_html( tc_get_setting( 'label_price_bar_cta_full', 'Ausgebucht' ) );
				elseif ( $price_type === 'request' ) :
					echo esc_html( tc_get_setting( 'label_price_bar_cta_request', 'Anfrage senden' ) );
				else :
					echo $link_text;
				endif;
				?>
			</a>

		</div><!-- /.tc-price-bar -->
	</div><!-- /.tc-price-bar-wrapper -->
	<?php
	return ob_get_clean();
} );

// ─────────────────────────────────────────────
// Enqueue price bar styles early so Oxygen Builder
// outputs them in the <head>.
// ─────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'tc-price-bar',
		TC_URL . 'assets/css/frontend/price-bar.css',
		[ 'tc-design-system' ],
		TC_VERSION
	);
} );

function tc_enqueue_price_bar_assets() {
	// Empty — styles are already loaded via wp_enqueue_scripts.
	// Kept for backwards compatibility.
}
