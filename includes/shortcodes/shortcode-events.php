<?php
/**
 * Shortcode [time_events]
 *
 * Universal event listing shortcode with multiple layouts and full
 * attribute control. Server-side rendered, no AJAX required.
 *
 * Attributes:
 *   category      – event_type slug (default: all)
 *   show_past     – include past events: true/false (default: false)
 *   limit         – max events (default: -1 = all)
 *   layout        – grid | list | cards (default: grid)
 *   columns       – 1|2|3 (default: 3)
 *   group_by      – month | none (default: none)
 *   show_image    – true/false (default: true)
 *   show_date     – true/false (default: true)
 *   show_time     – true/false (default: true)
 *   show_location – true/false (default: true)
 *   show_trainer  – true/false (default: true)
 *   show_price    – true/false (default: true)
 *   show_excerpt  – true/false (default: true)
 *   show_badge    – true/false (default: true)
 */

defined( 'ABSPATH' ) || exit;

// ── Register CSS (lazy: only enqueued when shortcode renders) ──────────────
add_action( 'wp_enqueue_scripts', function () {
	wp_register_style(
		'tc-events',
		TC_URL . 'assets/css/frontend/events.css',
		[],
		TC_VERSION
	);
} );

// ── Shortcode ──────────────────────────────────────────────────────────────
add_shortcode( 'time_events', 'tc_time_events_shortcode' );

function tc_time_events_shortcode( $atts ): string {
	$atts = shortcode_atts( [
		'category'      => '',
		'show_past'     => 'false',
		'limit'         => -1,
		'layout'        => 'grid',
		'columns'       => 3,
		'group_by'      => 'none',
		'show_image'    => 'true',
		'show_date'     => 'true',
		'show_time'     => 'true',
		'show_location' => 'true',
		'show_trainer'  => 'true',
		'show_price'    => 'true',
		'show_excerpt'  => 'true',
		'show_badge'    => 'true',
	], $atts, 'time_events' );

	// Normalize types
	$show_past = filter_var( $atts['show_past'], FILTER_VALIDATE_BOOLEAN );
	$limit     = (int) $atts['limit'];
	$columns   = max( 1, min( 3, (int) $atts['columns'] ) );
	$layout    = in_array( $atts['layout'], [ 'grid', 'list', 'cards' ], true )
		? $atts['layout'] : 'grid';
	$group_by  = $atts['group_by'] === 'month' ? 'month' : 'none';
	$category  = sanitize_key( $atts['category'] );

	// Keep normalized values back in $atts so the card renderer can read them
	$atts['layout']  = $layout;
	$atts['columns'] = $columns;

	// ── Query ──────────────────────────────────────────────────────────────
	$meta_query = [ 'relation' => 'AND' ];

	if ( $category ) {
		$meta_query[] = [
			'key'     => 'event_type',
			'value'   => $category,
			'compare' => '=',
		];
	}

	if ( ! $show_past ) {
		$meta_query[] = [
			'key'     => 'start_date',
			'value'   => wp_date( 'Y-m-d' ),
			'compare' => '>=',
			'type'    => 'DATE',
		];
	}

	$posts = get_posts( [
		'post_type'      => 'time_event',
		'post_status'    => 'publish',
		'posts_per_page' => $limit > 0 ? $limit : -1,
		'meta_key'       => 'start_date',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => $meta_query,
	] );

	// ── Enqueue CSS (now that we know the shortcode is on this page) ───────
	wp_enqueue_style( 'tc-events' );

	$dark_class = tc_get_setting( 'calendar_mode', 'light' ) === 'dark' ? ' tc-dark' : '';

	// ── Empty state ────────────────────────────────────────────────────────
	if ( empty( $posts ) ) {
		return '<div class="tc-events-wrap' . $dark_class . '"><div class="tc-events-empty">'
			. '<p>Aktuell sind keine Termine verfügbar.</p>'
			. '</div></div>';
	}

	// ── Render ─────────────────────────────────────────────────────────────
	$wrapper_class = 'tc-events-wrap tc-events-layout--' . $layout
		. ' tc-events-cols--' . $columns . $dark_class;

	ob_start();
	echo '<div class="' . esc_attr( $wrapper_class ) . '">';

	if ( $group_by === 'month' ) {
		tc_events_render_grouped( $posts, $atts );
	} else {
		echo '<div class="tc-events-grid">';
		foreach ( $posts as $post ) {
			tc_render_event_card( $post, $atts );
		}
		echo '</div>';
	}

	echo '</div>'; // .tc-events-wrap
	return ob_get_clean();
}

// ── Grouped rendering ──────────────────────────────────────────────────────
function tc_events_render_grouped( array $posts, array $atts ): void {
	static $month_names = [
		'01' => 'Januar',    '02' => 'Februar', '03' => 'März',
		'04' => 'April',     '05' => 'Mai',      '06' => 'Juni',
		'07' => 'Juli',      '08' => 'August',   '09' => 'September',
		'10' => 'Oktober',   '11' => 'November', '12' => 'Dezember',
	];

	$groups = [];

	foreach ( $posts as $post ) {
		$date = get_field( 'start_date', $post->ID );
		if ( ! $date ) {
			continue;
		}
		try {
			$dt    = new DateTime( $date );
			$key   = $dt->format( 'Y-m' );
			$label = ( $month_names[ $dt->format( 'm' ) ] ?? $dt->format( 'F' ) )
				. ' ' . $dt->format( 'Y' );
		} catch ( Exception $e ) {
			continue;
		}

		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = [ 'label' => $label, 'posts' => [] ];
		}
		$groups[ $key ]['posts'][] = $post;
	}

	foreach ( $groups as $group ) {
		echo '<div class="tc-events-group">';
		echo '<h3 class="tc-events-month-heading"><span>'
			. esc_html( $group['label'] ) . '</span></h3>';
		echo '<div class="tc-events-grid">';
		foreach ( $group['posts'] as $post ) {
			tc_render_event_card( $post, $atts );
		}
		echo '</div>';
		echo '</div>';
	}
}

// ── Card renderer (shared by all layouts) ─────────────────────────────────
function tc_render_event_card( WP_Post $post, array $atts ): void {
	global $wpdb;

	$show_image    = filter_var( $atts['show_image'],    FILTER_VALIDATE_BOOLEAN );
	$show_date     = filter_var( $atts['show_date'],     FILTER_VALIDATE_BOOLEAN );
	$show_time     = filter_var( $atts['show_time'],     FILTER_VALIDATE_BOOLEAN );
	$show_location = filter_var( $atts['show_location'], FILTER_VALIDATE_BOOLEAN );
	$show_trainer  = filter_var( $atts['show_trainer'],  FILTER_VALIDATE_BOOLEAN );
	$show_price    = filter_var( $atts['show_price'],    FILTER_VALIDATE_BOOLEAN );
	$show_excerpt  = filter_var( $atts['show_excerpt'],  FILTER_VALIDATE_BOOLEAN );
	$show_badge    = filter_var( $atts['show_badge'],    FILTER_VALIDATE_BOOLEAN );
	$layout        = $atts['layout'] ?? 'grid';

	// ── ACF fields (single batch read) ────────────────────────────────────
	$fields     = get_fields( $post->ID ) ?: [];
	$event_type = $fields['event_type']         ?? '';
	$start_date = $fields['start_date']         ?? '';
	$start_time = $fields['start_time']         ?? '';
	$end_time   = $fields['end_time']           ?? '';
	$location   = $fields['location']           ?? '';
	$trainer    = $fields['seminar_leadership'] ?? '';
	$price_raw  = $fields['normal_preis']       ?? '';
	$on_request = ! empty( $fields['price_on_request'] );
	$track_p    = ! empty( $fields['track_participants'] );
	$max_p      = (int) ( $fields['participants'] ?? 0 );

	// ── Capacity check ─────────────────────────────────────────────────────
	$is_full = false;
	if ( $track_p && $max_p > 0 ) {
		$cur_p = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}tc_registrations
			 WHERE event_id = %d AND status IN ('pending','confirmed')",
			$post->ID
		) );
		$is_full = $cur_p >= $max_p;
	}

	// ── Category data ──────────────────────────────────────────────────────
	$color    = '#4f46e5';
	$cat_name = $event_type ? ucfirst( $event_type ) : '';
	if ( $event_type ) {
		$cat = tc_get_category( $event_type );
		if ( $cat ) {
			$color    = $cat['color'] ?? $color;
			$cat_name = $cat['name']  ?? $cat_name;
		}
	}

	$url        = get_permalink( $post->ID );
	$card_class = 'tc-events-card' . ( $is_full ? ' tc-events-card--full' : '' );

	// ── Markup ─────────────────────────────────────────────────────────────
	echo '<article class="' . esc_attr( $card_class ) . '">';

	// Full-card click overlay (behind all content)
	echo '<a href="' . esc_url( $url ) . '" class="tc-events-card-link" tabindex="-1" aria-hidden="true"></a>';

	// Accent stripe (cards layout only)
	if ( $layout === 'cards' ) {
		echo '<div class="tc-events-stripe" style="background:' . esc_attr( $color ) . '"></div>';
	}

	// ── Image section ──────────────────────────────────────────────────────
	if ( $show_image ) {
		echo '<div class="tc-events-card-img">';

		if ( has_post_thumbnail( $post->ID ) ) {
			echo get_the_post_thumbnail( $post->ID, 'medium', [
				'loading' => 'lazy',
				'class'   => 'tc-events-thumb',
				'alt'     => esc_attr( get_the_title( $post->ID ) ),
			] );
		} else {
			echo '<div class="tc-events-img-placeholder" style="background:'
				. esc_attr( $color ) . '" aria-hidden="true"></div>';
		}

		if ( $is_full ) {
			echo '<span class="tc-events-sold-out">Ausgebucht</span>';
		}

		echo '</div>';
	}

	// ── Card body ──────────────────────────────────────────────────────────
	echo '<div class="tc-events-card-body">';

	// Sold-out badge (fallback when image is hidden)
	if ( $is_full && ! $show_image ) {
		echo '<span class="tc-events-sold-out tc-events-sold-out--inline">Ausgebucht</span>';
	}

	// Category badge
	if ( $show_badge && $cat_name ) {
		echo '<span class="tc-events-badge" style="--badge-color:' . esc_attr( $color ) . '">'
			. esc_html( $cat_name ) . '</span>';
	}

	// Title
	echo '<h3 class="tc-events-card-title">'
		. '<a href="' . esc_url( $url ) . '">'
		. esc_html( get_the_title( $post->ID ) )
		. '</a></h3>';

	// Excerpt
	if ( $show_excerpt ) {
		$excerpt = get_the_excerpt( $post );
		if ( ! $excerpt ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 18 );
		}
		if ( $excerpt ) {
			echo '<p class="tc-events-card-excerpt">' . esc_html( $excerpt ) . '</p>';
		}
	}

	// ── Meta list ──────────────────────────────────────────────────────────
	$has_meta = $show_date || $show_time || $show_location || $show_trainer || $show_price;

	if ( $has_meta ) {
		echo '<ul class="tc-events-meta">';

		// Date (and optionally time)
		if ( $show_date && $start_date ) {
			$time_str = '';
			if ( $show_time && $start_time ) {
				$t_start  = esc_html( substr( $start_time, 0, 5 ) );
				$time_str = $end_time
					? ' · ' . $t_start . '–' . esc_html( substr( $end_time, 0, 5 ) ) . ' Uhr'
					: ' · ' . $t_start . ' Uhr';
			}
			echo '<li class="tc-events-meta-item tc-events-meta--date">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true">'
				. '<path d="M11 1v1H5V1H4v1H2.5A1.5 1.5 0 0 0 1 3.5v9A1.5 1.5 0 0 0 2.5 14h11'
				. 'a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 13.5 2H12V1h-1zm1 2h1.5a.5.5 0 0 1'
				. ' .5.5V5H2V3.5a.5.5 0 0 1 .5-.5H4v1h1V3h6v1h1V3zM2 6h12v6.5a.5.5 0 0 1-.5.5'
				. 'h-11a.5.5 0 0 1-.5-.5V6z"/></svg>';
			echo '<span>' . esc_html( tc_events_format_date( $start_date ) ) . $time_str . '</span>';
			echo '</li>';
		} elseif ( $show_time && $start_time ) {
			echo '<li class="tc-events-meta-item tc-events-meta--date">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true">'
				. '<path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 1a6 6 0 1 1 0 12A6 6 0 0 1 8 2z'
				. 'M7.5 4.5h1v4.25l3.25 1.87-.5.87L7.5 9.28V4.5z"/></svg>';
			echo '<span>' . esc_html( substr( $start_time, 0, 5 ) ) . ' Uhr</span>';
			echo '</li>';
		}

		// Location
		if ( $show_location && $location ) {
			echo '<li class="tc-events-meta-item tc-events-meta--location">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true">'
				. '<path d="M8 1a5 5 0 0 0-5 5c0 3.5 5 9 5 9s5-5.5 5-9a5 5 0 0 0-5-5zm0 7a2 2 0 1'
				. ' 1 0-4 2 2 0 0 1 0 4z"/></svg>';
			echo '<span>' . esc_html( wp_strip_all_tags( $location ) ) . '</span>';
			echo '</li>';
		}

		// Trainer
		if ( $show_trainer && $trainer ) {
			echo '<li class="tc-events-meta-item tc-events-meta--trainer">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true">'
				. '<path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 1c-2.67 0-8 1.34-8 4v1h16v-1'
				. 'c0-2.66-5.33-4-8-4z"/></svg>';
			echo '<span>' . esc_html( $trainer ) . '</span>';
			echo '</li>';
		}

		// Price
		if ( $show_price ) {
			$price_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true">'
				. '<path d="M4 10.781c.148 1.667 1.513 2.85 3.591 3.003V15h1.043v-1.216c2.27-.179'
				. ' 3.678-1.438 3.678-3.3 0-1.59-.947-2.51-2.956-3.028l-.722-.187V3.467c1.122.11'
				. ' 1.879.714 2.07 1.616h1.47c-.166-1.6-1.54-2.748-3.54-2.875V1H7.591v1.233'
				. 'c-1.939.23-3.27 1.472-3.27 3.156 0 1.454.966 2.483 2.661 2.917l.61.162v4.031'
				. 'c-1.149-.17-1.95-.8-2.196-1.718H4zm3.87-2.31c-1.443-.379-2.24-.967-2.24-2.02'
				. ' 0-1.15.875-1.99 2.24-2.166v4.186zm1.044 1.63c1.6.44 2.4 1.04 2.4 2.19'
				. ' 0 1.22-.88 2.04-2.4 2.25V10.1z"/></svg>';

			if ( $on_request ) {
				echo '<li class="tc-events-meta-item tc-events-meta--price">'
					. $price_icon
					. '<span>Preis auf Anfrage</span>'
					. '</li>';
			} elseif ( $price_raw !== '' && $price_raw !== false ) {
				$price_fmt = number_format( (float) $price_raw, 2, ',', '.' ) . ' €';
				echo '<li class="tc-events-meta-item tc-events-meta--price">'
					. $price_icon
					. '<span>' . esc_html( $price_fmt ) . '</span>'
					. '</li>';
			}
		}

		echo '</ul>';
	}

	echo '</div>'; // .tc-events-card-body
	echo '</article>';
}

// ── Helper: German date format ─────────────────────────────────────────────
function tc_events_format_date( string $date_str ): string {
	static $months = [
		'01' => 'Jan.', '02' => 'Feb.',  '03' => 'März', '04' => 'Apr.',
		'05' => 'Mai',  '06' => 'Juni',  '07' => 'Juli', '08' => 'Aug.',
		'09' => 'Sep.', '10' => 'Okt.',  '11' => 'Nov.', '12' => 'Dez.',
	];
	static $weekdays = [ 'So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa' ];

	try {
		$dt = new DateTime( $date_str );
		return $weekdays[ (int) $dt->format( 'w' ) ]
			. ', ' . $dt->format( 'j' ) . '. '
			. ( $months[ $dt->format( 'm' ) ] ?? '' )
			. ' ' . $dt->format( 'Y' );
	} catch ( Exception $e ) {
		return $date_str;
	}
}
