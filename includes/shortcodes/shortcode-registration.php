<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// CSS frueh laden
// ─────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'tc-registration',
        TC_URL . 'assets/css/frontend/registration.css',
        array( 'tc-design-system' ),
        TC_VERSION
    );
} );

// ─────────────────────────────────────────────
// Helper: Repeater-Termine für Registrierungsformular
// Gibt zukünftige Termine aus dem event_dates Repeater zurück.
// ─────────────────────────────────────────────
function tc_get_repeater_dates_for_registration( $event_id ) {
    $rows = get_field( 'event_dates', $event_id );
    if ( empty( $rows ) || ! is_array( $rows ) ) return array();

    $today   = date( 'Y-m-d' );
    $results = array();
    foreach ( $rows as $row ) {
        $date = $row['date_start'] ?? '';
        if ( ! $date || $date < $today ) continue;
        $results[] = array(
            'date'  => $date,
            'time'  => $row['time_start'] ?? '',
            'seats' => isset( $row['seats'] ) && $row['seats'] > 0 ? (int) $row['seats'] : null,
        );
    }
    return $results;
}

// ─────────────────────────────────────────────
// Helper: Upcoming Occurrences fuer ein
// wiederkehrendes Event generieren.
// Gibt nur Termine >= heute zurueck.
// ─────────────────────────────────────────────
function tc_get_upcoming_occurrences( $event_id ) {
    $start_date        = get_field( 'start_date',        $event_id ); // Y-m-d
    $recurring_weekday = get_field( 'recurring_weekday', $event_id ); // '0'-'6'
    $recurring_until   = get_field( 'recurring_until',   $event_id ); // Y-m-d

    if ( ! $start_date || $recurring_weekday === '' || ! $recurring_until ) {
        return array();
    }

    $today    = new DateTime( 'today' );
    $target   = (int) $recurring_weekday; // 0=So ... 6=Sa
    $cur      = new DateTime( $start_date );
    $until_dt = new DateTime( $recurring_until . ' 23:59:59' );

    // Zum ersten passenden Wochentag ab Startdatum spulen
    $diff = ( $target - (int) $cur->format( 'w' ) + 7 ) % 7;
    if ( $diff > 0 ) $cur->modify( "+{$diff} days" );

    $dates = array();
    $limit = 0;

    while ( $cur <= $until_dt && $limit < 260 ) {
        // Nur zukuenftige Termine
        if ( $cur >= $today ) {
            $dates[] = $cur->format( 'Y-m-d' );
        }
        $cur->modify( '+7 days' );
        $limit++;
    }

    return $dates;
}

// ─────────────────────────────────────────────
// Shortcode: [time_registration]
//
// Attribute:
//   event_id = Post-ID der Veranstaltung (optional)
//   title    = Formulartitel (optional)
// ─────────────────────────────────────────────
add_shortcode( 'time_registration', function ( $atts ) {

    $atts = shortcode_atts( array(
        'event_id' => 0,
        'title'    => 'Anmelden',
    ), $atts, 'time_registration' );

    $event_id = absint( $atts['event_id'] );

    if ( ! $event_id && is_singular( 'time_event' ) ) {
        $event_id = get_the_ID();
    }

    if ( $event_id && get_post_type( $event_id ) !== 'time_event' ) {
        return '<p class="tc-error">Veranstaltung nicht gefunden.</p>';
    }

    // ── registration_mode prüfen ─────────────────────────────
    if ( $event_id ) {
        $reg_mode = get_field( 'registration_mode', $event_id ) ?: 'open';
        if ( $reg_mode === 'none' ) {
            return '';
        }
        if ( $reg_mode === 'request' ) {
            $contact_email  = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );
            $event_title    = get_the_title( $event_id );
            $subject        = rawurlencode( 'Anfrage: ' . $event_title );
            $dark_class     = tc_dark_class();
            $request_btn    = tc_get_setting( 'label_request_btn',    'Jetzt anfragen' );
            $request_notice = tc_get_setting( 'label_request_notice', 'Für weitere Informationen oder eine Buchungsanfrage kontaktieren Sie uns gerne direkt.' );
            ob_start(); ?>
            <div class="tc-registration-wrap <?php echo esc_attr( $dark_class ); ?>">
                <div class="tc-trial-notice">
                    <?php echo esc_html( $request_notice ); ?>
                </div>
                <a href="mailto:<?php echo esc_attr( $contact_email ); ?>?subject=<?php echo $subject; ?>"
                   class="tc-btn tc-btn-primary">
                    <?php echo esc_html( $request_btn ); ?>
                </a>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    static $instance = 0;
    $instance++;
    $form_id = 'tc-registration-form-' . $instance;
    $nonce   = wp_create_nonce( 'tc_registration_nonce' );

    // Preistyp ermitteln (request → Probetraining-Modus)
    $price_type = $event_id ? ( get_field( 'event_price_type', $event_id ) ?: 'fixed' ) : 'fixed';
    $is_trial   = in_array( $price_type, array( 'request', 'free' ), true );

    // Formulartitel: bei Probetraining angepasst, sonst Shortcode-Attribut
    $form_title = $is_trial
        ? tc_get_setting( 'label_form_title_trial', 'Kostenloses Probetraining anfragen' )
        : ( $atts['title'] !== 'Anmelden' ? esc_html( $atts['title'] ) : tc_get_setting( 'label_form_title', 'Anmelden' ) );

    // Termine aufbereiten: Repeater-Termine haben Vorrang vor Wiederholungen
    $date_type_val  = $event_id ? ( get_field( 'event_date_type', $event_id ) ?: 'single' ) : 'single';
    $is_recurring   = $date_type_val === 'recurring';
    $repeater_dates = $event_id ? tc_get_repeater_dates_for_registration( $event_id ) : array();
    $occurrences    = empty( $repeater_dates ) && $event_id && $is_recurring
        ? tc_get_upcoming_occurrences( $event_id )
        : array();
    $show_date_pick = ! empty( $repeater_dates ) || ! empty( $occurrences );

    // Ausgebucht? Warteliste prüfen
    $is_full = false;
    if ( $event_id ) {
        $track_p = get_field( 'registration_limit', $event_id );
        $max_p   = get_field( 'max_participants',  $event_id );
        if ( $track_p && $max_p ) {
            global $wpdb;
            $cur_p   = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tc_registrations WHERE event_id = %d AND status IN ('pending','confirmed')",
                $event_id
            ) );
            $is_full = $cur_p >= (int) $max_p;
        }
    }

    tc_enqueue_registration_assets();

    $dark_class = tc_dark_class();

    // ── Wartelisten-Formular (Event ausgebucht, festes event_id) ──
    if ( $is_full && $event_id ) {
        $wl_form_id = 'tc-waitlist-form-' . $instance;
        ob_start(); ?>
        <div class="tc-registration-wrap <?php echo esc_attr( $dark_class ); ?>">
            <form id="<?php echo esc_attr( $wl_form_id ); ?>" class="tc-registration-form tc-waitlist-form" method="POST">
                <h2>Warteliste – <?php echo esc_html( get_the_title( $event_id ) ); ?></h2>
                <div class="tc-waitlist-notice">
                    <strong><?php echo esc_html( tc_get_setting( 'label_full_notice', 'Diese Veranstaltung ist leider ausgebucht.' ) ); ?></strong><br>
                    <?php echo esc_html( tc_get_setting( 'label_full_subtext', 'Tragen Sie sich auf die Warteliste ein – wir benachrichtigen Sie, sobald ein Platz frei wird.' ) ); ?>
                </div>
                <div class="tc-form-messages" style="display:none;"></div>
                <input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">
                <?php if ( $show_date_pick ) : ?>
                <div class="tc-form-group">
                    <label>Termin <span class="tc-required">*</span></label>
                    <select name="event_date" class="tc-form-control" required>
                        <option value="">– Bitte wählen –</option>
                        <?php
                        $de_days = [ 1 => 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag' ];
                        foreach ( $occurrences as $occ_date ) :
                            $d_occ = DateTime::createFromFormat( 'Y-m-d', $occ_date );
                            $lbl   = $de_days[ (int) $d_occ->format( 'N' ) ] . ', ' . $d_occ->format( 'd.m.Y' );
                        ?>
                            <option value="<?php echo esc_attr( $occ_date ); ?>"><?php echo esc_html( $lbl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="tc-form-row">
                    <div class="tc-form-group">
                        <label>Vorname <span class="tc-required">*</span></label>
                        <input type="text" name="firstname" class="tc-form-control" required autocomplete="given-name">
                    </div>
                    <div class="tc-form-group">
                        <label>Nachname <span class="tc-required">*</span></label>
                        <input type="text" name="lastname" class="tc-form-control" required autocomplete="family-name">
                    </div>
                </div>
                <div class="tc-form-row">
                    <div class="tc-form-group">
                        <label>E-Mail <span class="tc-required">*</span></label>
                        <input type="email" name="email" class="tc-form-control" required autocomplete="email">
                    </div>
                    <div class="tc-form-group">
                        <label>Telefon</label>
                        <input type="tel" name="phone" class="tc-form-control" autocomplete="tel">
                    </div>
                </div>
                <div class="tc-form-group">
                    <label>Notizen (optional)</label>
                    <textarea name="notes" class="tc-form-control" rows="3"></textarea>
                </div>
                <div class="tc-form-group">
                    <button type="submit" class="tc-btn tc-btn-primary tc-submit-btn">
                        <span class="tc-btn-text"><?php echo esc_html( tc_get_setting( 'label_waitlist_btn', 'Auf Warteliste eintragen' ) ); ?></span>
                        <span class="tc-btn-loader" style="display:none;"><span class="tc-spinner"></span> Wird verarbeitet...</span>
                    </button>
                </div>
                <input type="hidden" name="action" value="tc_submit_waitlist">
                <input type="hidden" name="nonce"  value="<?php echo esc_attr( $nonce ); ?>">
            </form>
        </div>
        <script>
        jQuery(function($) {
            $('#<?php echo esc_js( $wl_form_id ); ?>').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $msg  = $form.find('.tc-form-messages');
                var $btn  = $form.find('.tc-submit-btn');
                $btn.find('.tc-btn-text').hide();
                $btn.find('.tc-btn-loader').show();
                $msg.hide().removeClass('tc-success tc-error');
                $.post(tcRegistration.ajaxUrl, $form.serialize(), function(res) {
                    if (res.success) {
                        $form.find(':input:not([type=hidden])').prop('disabled', true);
                        $msg.addClass('tc-success').html(res.data.message).show();
                        $btn.hide();
                    } else {
                        $msg.addClass('tc-error').html(res.data.message || 'Ein Fehler ist aufgetreten.').show();
                        $btn.find('.tc-btn-text').show();
                        $btn.find('.tc-btn-loader').hide();
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    ob_start(); ?>
    <div class="tc-registration-wrap <?php echo esc_attr( $dark_class ); ?>">
        <form id="<?php echo esc_attr( $form_id ); ?>" class="tc-registration-form" method="POST">

            <h2><?php echo esc_html( $form_title ); ?></h2>

            <?php if ( $is_trial ) : ?>
            <div class="tc-trial-notice">
                <strong>Kostenlos &amp; unverbindlich</strong>
                Schnupper einfach rein &ndash; ganz ohne Verpflichtungen. Wir melden uns nach deiner Anfrage zeitnah bei dir.
            </div>
            <?php endif; ?>

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
                        <option value="">&#8211; Bitte w&auml;hlen &#8211;</option>
                        <?php
                        foreach ( get_posts( array(
                            'post_type'      => 'time_event',
                            'posts_per_page' => -1,
                            'orderby'        => 'title',
                            'order'          => 'ASC',
                        ) ) as $ev ) :
                            $sel = ( is_singular( 'time_event' ) && $ev->ID === get_the_ID() ) ? 'selected' : '';
                            echo '<option value="' . esc_attr( $ev->ID ) . '" ' . $sel . '>'
                               . esc_html( $ev->post_title ) . '</option>';
                        endforeach;
                        ?>
                    </select>
                </div>

                <!-- Event-Details (per AJAX gefuellt) -->
                <div id="<?php echo esc_attr( $form_id ); ?>-details" class="tc-event-details" style="display:none;">
                    <div class="tc-detail-item"><span class="tc-detail-label">Leitung:</span><span class="tc-detail-leadership">&#8211;</span></div>
                    <div class="tc-detail-item"><span class="tc-detail-label">Ort:</span><span class="tc-detail-location">&#8211;</span></div>
                    <div class="tc-detail-item"><span class="tc-detail-label">Datum:</span><span class="tc-detail-date">&#8211;</span></div>
                </div>

                <!-- Datum-Picker (per AJAX eingeblendet bei wiederkehrend/mehrtagig) -->
                <div id="<?php echo esc_attr( $form_id ); ?>-date-picker" class="tc-form-group" style="display:none;">
                    <label for="<?php echo esc_attr( $form_id ); ?>-event-date" class="tc-date-picker-label">
                        W&auml;hlen Sie einen Termin <span class="tc-required">*</span>
                    </label>
                    <select id="<?php echo esc_attr( $form_id ); ?>-event-date"
                            name="event_date"
                            class="tc-form-control">
                        <option value="">&#8211; Bitte w&auml;hlen &#8211;</option>
                    </select>
                </div>

            <?php else : ?>
                <!-- ── Festes Event ──────────────────────── -->
                <input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">

                <?php if ( $show_date_pick ) : ?>
                <!-- Termin-Dropdown server-seitig gerendert -->
                <div class="tc-form-group">
                    <label for="<?php echo esc_attr( $form_id ); ?>-occurrence">
                        Termin w&auml;hlen <span class="tc-required">*</span>
                    </label>
                    <select id="<?php echo esc_attr( $form_id ); ?>-occurrence"
                            name="event_date"
                            class="tc-form-control"
                            required>
                        <option value="">&#8211; Bitte w&auml;hlen &#8211;</option>
                        <?php
                        $de_days = array( 1 => 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag' );
                        if ( ! empty( $repeater_dates ) ) :
                            foreach ( $repeater_dates as $rd ) :
                                $d     = DateTime::createFromFormat( 'Y-m-d', $rd['date'] );
                                $label = $de_days[ (int) $d->format( 'N' ) ] . ', ' . $d->format( 'd.m.Y' );
                                if ( $rd['time'] ) $label .= ' · ' . $rd['time'] . ' Uhr';
                                if ( $rd['seats'] !== null ) $label .= ' (noch ' . $rd['seats'] . ' Plätze)';
                        ?>
                            <option value="<?php echo esc_attr( $rd['date'] ); ?>">
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php
                            endforeach;
                        else :
                            foreach ( $occurrences as $date ) :
                                $d     = DateTime::createFromFormat( 'Y-m-d', $date );
                                $label = $de_days[ (int) $d->format( 'N' ) ] . ', ' . $d->format( 'd.m.Y' );
                        ?>
                            <option value="<?php echo esc_attr( $date ); ?>">
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php
                            endforeach;
                        endif;
                        ?>
                    </select>
                </div>
                <?php endif; ?>

            <?php endif; ?>

            <!-- ── Persoenliche Daten ─────────────────────── -->
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

            <?php if ( ! $is_trial ) : ?>
            <div class="tc-form-group">
                <label for="<?php echo esc_attr( $form_id ); ?>-address">Stra&szlig;e &amp; Hausnummer</label>
                <input type="text" id="<?php echo esc_attr( $form_id ); ?>-address"
                       name="address" class="tc-form-control" autocomplete="street-address">
            </div>

            <div class="tc-form-row">
                <div class="tc-form-group">
                    <label for="<?php echo esc_attr( $form_id ); ?>-zip">PLZ</label>
                    <input type="text" id="<?php echo esc_attr( $form_id ); ?>-zip"
                           name="zip" class="tc-form-control" autocomplete="postal-code">
                </div>
                <div class="tc-form-group">
                    <label for="<?php echo esc_attr( $form_id ); ?>-city">Ort</label>
                    <input type="text" id="<?php echo esc_attr( $form_id ); ?>-city"
                           name="city" class="tc-form-control" autocomplete="address-level2">
                </div>
            </div>
            <?php endif; ?>

            <div class="tc-form-group">
                <label for="<?php echo esc_attr( $form_id ); ?>-notes">
                    <?php echo $is_trial ? 'Nachricht / Fragen (optional)' : 'Besondere Anfragen / Notizen'; ?>
                </label>
                <textarea id="<?php echo esc_attr( $form_id ); ?>-notes"
                          name="notes" class="tc-form-control" rows="4"
                          placeholder="<?php echo $is_trial ? 'z.B. Vorerfahrungen, Fragen oder Wunschtermin...' : 'z.B. Spezielle Anforderungen oder Fragen...'; ?>"></textarea>
            </div>

            <div class="tc-form-group">
                <button type="submit" class="tc-btn tc-btn-primary tc-submit-btn">
                    <span class="tc-btn-text">
                        <?php echo $is_trial
                            ? esc_html( tc_get_setting( 'label_submit_btn_trial', 'Probetraining anfragen' ) )
                            : esc_html( tc_get_setting( 'label_submit_btn', 'Anmeldung absenden' ) ); ?>
                    </span>
                    <span class="tc-btn-loader" style="display:none;">
                        <span class="tc-spinner"></span> Wird verarbeitet...
                    </span>
                </button>
            </div>

            <input type="hidden" name="action"   value="tc_submit_registration">
            <input type="hidden" name="nonce"    value="<?php echo esc_attr( $nonce ); ?>">
            <input type="hidden" name="is_trial" value="<?php echo $is_trial ? '1' : '0'; ?>">

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
        TC_URL . 'assets/js/frontend/registration.js',
        array( 'jquery' ),
        TC_VERSION,
        true
    );

    wp_localize_script( 'tc-registration', 'tcRegistration', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'tc_registration_nonce' ),
    ) );
}
