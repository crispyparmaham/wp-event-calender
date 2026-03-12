<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// CSS früh laden
// ─────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'tc-registration',
        TC_URL . 'assets/css/registration.css',
        array(),
        TC_VERSION
    );
} );

// ─────────────────────────────────────────────
// Helper: Upcoming Occurrences für ein
// wiederkehrendes Event generieren.
// Gibt nur Termine >= heute zurück.
// ─────────────────────────────────────────────
function tc_get_upcoming_occurrences( $event_id ) {
    $start_date        = get_field( 'start_date',        $event_id ); // Y-m-d
    $recurring_weekday = get_field( 'recurring_weekday', $event_id ); // '0'–'6'
    $recurring_until   = get_field( 'recurring_until',   $event_id ); // Y-m-d

    if ( ! $start_date || $recurring_weekday === '' || ! $recurring_until ) {
        return array();
    }

    $today    = new DateTime( 'today' );
    $target   = (int) $recurring_weekday; // 0=So … 6=Sa
    $cur      = new DateTime( $start_date );
    $until_dt = new DateTime( $recurring_until . ' 23:59:59' );

    // Zum ersten passenden Wochentag ab Startdatum spulen
    $diff = ( $target - (int) $cur->format( 'w' ) + 7 ) % 7;
    if ( $diff > 0 ) $cur->modify( "+{$diff} days" );

    $dates = array();
    $limit = 0;

    while ( $cur <= $until_dt && $limit < 260 ) {
        // Nur zukünftige Termine
        if ( $cur >= $today ) {
            $dates[] = $cur->format( 'Y-m-d' );
        }
        $cur->modify( '+7 days' );
        $limit++;
    }

    return $dates;
}

// ─────────────────────────────────────────────
// Shortcode: [training_registration]
//
// Attribute:
//   event_id = Post-ID der Veranstaltung (optional)
//   title    = Formulartitel (optional)
// ─────────────────────────────────────────────
add_shortcode( 'training_registration', function ( $atts ) {

    $atts = shortcode_atts( array(
        'event_id' => 0,
        'title'    => 'Anmelden',
    ), $atts, 'training_registration' );

    $event_id = absint( $atts['event_id'] );

    if ( ! $event_id && is_singular( 'training_event' ) ) {
        $event_id = get_the_ID();
    }

    if ( $event_id && get_post_type( $event_id ) !== 'training_event' ) {
        return '<p class="tc-error">Veranstaltung nicht gefunden.</p>';
    }

    static $instance = 0;
    $instance++;
    $form_id = 'tc-registration-form-' . $instance;
    $nonce   = wp_create_nonce( 'tc_registration_nonce' );

    // Wiederkehrend? Termine server-seitig aufbereiten
    $is_recurring   = $event_id ? (bool) get_field( 'is_recurring', $event_id ) : false;
    $occurrences    = ( $event_id && $is_recurring ) ? tc_get_upcoming_occurrences( $event_id ) : array();
    $show_date_pick = ! empty( $occurrences );

    tc_enqueue_registration_assets();

    $dark_class = tc_get_setting( 'calendar_mode', 'light' ) === 'dark' ? 'tc-dark' : '';

    ob_start(); ?>
    <div class="tc-registration-wrap <?php echo esc_attr( $dark_class ); ?>">
        <form id="<?php echo esc_attr( $form_id ); ?>" class="tc-registration-form" method="POST">

            <h2><?php echo esc_html( $atts['title'] ); ?></h2>

            <div class="tc-form-messages" style="display:none;"></div>

            <?php if ( ! $event_id ) : ?>
                <!-- ── Event-Auswahl (kein festes Event) ───── -->
                <div class="tc-form-group">
                    <label for="<?php echo esc_attr( $form_id ); ?>-event">
                        Veranstaltung <span class="tc-required">*</span>
                    </label>
                    <select id="<?php echo esc_attr( $form_id ); ?>-event"
                            name="event_id"
                            class="tc-form-control tc-event-select"
                            required>
                        <option value="">– Bitte wählen –</option>
                        <?php
                        foreach ( get_posts( array(
                            'post_type'      => 'training_event',
                            'posts_per_page' => -1,
                            'orderby'        => 'title',
                            'order'          => 'ASC',
                        ) ) as $ev ) :
                            $sel = ( is_singular( 'training_event' ) && $ev->ID === get_the_ID() ) ? 'selected' : '';
                            echo '<option value="' . esc_attr( $ev->ID ) . '" ' . $sel . '>'
                               . esc_html( $ev->post_title ) . '</option>';
                        endforeach;
                        ?>
                    </select>
                </div>

                <!-- Event-Details (per AJAX gefüllt) -->
                <div id="<?php echo esc_attr( $form_id ); ?>-details" class="tc-event-details" style="display:none;">
                    <div class="tc-detail-item"><span class="tc-detail-label">Leitung:</span><span class="tc-detail-leadership">–</span></div>
                    <div class="tc-detail-item"><span class="tc-detail-label">Ort:</span><span class="tc-detail-location">–</span></div>
                    <div class="tc-detail-item"><span class="tc-detail-label">Datum:</span><span class="tc-detail-date">–</span></div>
                </div>

                <!-- Datum-Picker (per AJAX eingeblendet bei wiederkehrend/mehrtägig) -->
                <div id="<?php echo esc_attr( $form_id ); ?>-date-picker" class="tc-form-group" style="display:none;">
                    <label for="<?php echo esc_attr( $form_id ); ?>-event-date" class="tc-date-picker-label">
                        Wählen Sie einen Termin <span class="tc-required">*</span>
                    </label>
                    <select id="<?php echo esc_attr( $form_id ); ?>-event-date"
                            name="event_date"
                            class="tc-form-control">
                        <option value="">– Bitte wählen –</option>
                    </select>
                </div>

            <?php else : ?>
                <!-- ── Festes Event ──────────────────────── -->
                <input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">

                <?php if ( $show_date_pick ) : ?>
                <!-- Termin-Dropdown server-seitig gerendert -->
                <div class="tc-form-group">
                    <label for="<?php echo esc_attr( $form_id ); ?>-occurrence">
                        Termin wählen <span class="tc-required">*</span>
                    </label>
                    <select id="<?php echo esc_attr( $form_id ); ?>-occurrence"
                            name="event_date"
                            class="tc-form-control"
                            required>
                        <option value="">– Bitte wählen –</option>
                        <?php foreach ( $occurrences as $date ) :
                            $d = DateTime::createFromFormat( 'Y-m-d', $date ); ?>
                            <option value="<?php echo esc_attr( $date ); ?>">
                                <?php echo esc_html( $d->format( 'l, d.m.Y' ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

            <?php endif; ?>

            <!-- ── Persönliche Daten ─────────────────────── -->
            <div class="tc-form-row">
                <div class="tc-form-group">
                    <label for="<?php echo esc_attr( $form_id ); ?>-firstname">Vorname <span class="tc-required">*</span></label>
                    <input type="text" id="<?php echo esc_attr( $form_id ); ?>-firstname"
                           name="firstname" class="tc-form-control" required autocomplete="given-name">
                </div>
                <div class="tc-form-group">
                    <label for="<?php echo esc_attr( $form_id ); ?>-lastname">Nachname <span class="tc-required">*</span></label>
                    <input type="text" id="<?php echo esc_attr( $form_id ); ?>-lastname"
                           name="lastname" class="tc-form-control" required autocomplete="family-name">
                </div>
            </div>

            <div class="tc-form-row">
                <div class="tc-form-group">
                    <label for="<?php echo esc_attr( $form_id ); ?>-email">E-Mail <span class="tc-required">*</span></label>
                    <input type="email" id="<?php echo esc_attr( $form_id ); ?>-email"
                           name="email" class="tc-form-control" required autocomplete="email">
                </div>
                <div class="tc-form-group">
                    <label for="<?php echo esc_attr( $form_id ); ?>-phone">Telefon</label>
                    <input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone"
                           name="phone" class="tc-form-control" autocomplete="tel">
                </div>
            </div>

            <div class="tc-form-group">
                <label for="<?php echo esc_attr( $form_id ); ?>-company">Unternehmen</label>
                <input type="text" id="<?php echo esc_attr( $form_id ); ?>-company"
                       name="company" class="tc-form-control" autocomplete="organization">
            </div>

            <div class="tc-form-group">
                <label for="<?php echo esc_attr( $form_id ); ?>-notes">Besondere Anfragen / Notizen</label>
                <textarea id="<?php echo esc_attr( $form_id ); ?>-notes"
                          name="notes" class="tc-form-control" rows="4"
                          placeholder="z.B. Spezielle Anforderungen oder Fragen..."></textarea>
            </div>

            <div class="tc-form-group">
                <button type="submit" class="tc-btn tc-btn-primary tc-submit-btn">
                    <span class="tc-btn-text">Anmeldung absenden</span>
                    <span class="tc-btn-loader" style="display:none;">
                        <span class="tc-spinner"></span> Wird verarbeitet...
                    </span>
                </button>
            </div>

            <input type="hidden" name="action" value="tc_submit_registration">
            <input type="hidden" name="nonce"  value="<?php echo esc_attr( $nonce ); ?>">

        </form>
    </div>
    <?php
    return ob_get_clean();
} );

// ─────────────────────────────────────────────
// JS enqueuen
// ─────────────────────────────────────────────
function tc_enqueue_registration_assets() {
    static $enqueued = false;
    if ( $enqueued ) return;
    $enqueued = true;

    wp_enqueue_script(
        'tc-registration',
        TC_URL . 'assets/js/registration.js',
        array( 'jquery' ),
        TC_VERSION,
        true
    );

    wp_localize_script( 'tc-registration', 'tcRegistration', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'tc_registration_nonce' ),
    ) );
}