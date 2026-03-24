<?php
defined( 'ABSPATH' ) || exit;

// ---------------------------------------------
// Tabelle anlegen / aktualisieren
// ---------------------------------------------
function tc_create_registrations_table() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'tc_registrations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        firstname varchar(255) NOT NULL,
        lastname varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(20),
        address varchar(255),
        zip varchar(20),
        city varchar(100),
        event_id bigint(20) NOT NULL,
        event_date date DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        notes longtext,
        reminder_sent tinyint(1) NOT NULL DEFAULT 0,
        cancel_token varchar(64) DEFAULT NULL,
        created_at bigint(20) NOT NULL,
        PRIMARY KEY (id),
        KEY email (email),
        KEY event_id (event_id),
        KEY status (status),
        KEY cancel_token (cancel_token),
        KEY email_event (email, event_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// Nur beim Plugin-Aktivieren ausführen (register_activation_hook in functions.php),
// nicht bei jedem admin_init.

// ---------------------------------------------
// Helpers
// ---------------------------------------------
function tc_get_all_registrations() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}tc_registrations ORDER BY created_at DESC",
        ARRAY_A
    );
    return $rows ?: array();
}

function tc_get_registration( $id ) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_registrations WHERE id = %d", $id ),
        ARRAY_A
    );
}

function tc_update_registration( $id, $data ) {
    global $wpdb;
    $allowed = array( 'firstname', 'lastname', 'email', 'phone', 'address', 'zip', 'city', 'event_id', 'event_date', 'status', 'notes', 'reminder_sent', 'cancel_token' );
    $clean   = array_intersect_key( $data, array_flip( $allowed ) );
    if ( empty( $clean ) ) return false;
    return $wpdb->update( "{$wpdb->prefix}tc_registrations", $clean, array( 'id' => $id ) );
}

function tc_delete_registration( $id ) {
    global $wpdb;
    return $wpdb->delete( "{$wpdb->prefix}tc_registrations", array( 'id' => $id ), array( '%d' ) );
}

// ---------------------------------------------
// Placeholder-Engine für Mail-Templates
// ---------------------------------------------
function tc_resolve_placeholders( string $text, array $data, int $event_id = 0, string $event_date = '' ): string {
    $mode = tc_get_setting( 'anrede_mode', 'sie' );
    if ( $mode === 'du' ) {
        $anrede_vals = array(
            'anrede'           => 'du',
            'anrede_possessiv' => 'deine',
            'anrede_akkusativ' => 'dich',
            'anrede_dativ'     => 'dir',
            'anrede_imperativ' => 'Bitte melde dich',
        );
    } else {
        $anrede_vals = array(
            'anrede'           => 'Sie',
            'anrede_possessiv' => 'Ihre',
            'anrede_akkusativ' => 'Sie',
            'anrede_dativ'     => 'Ihnen',
            'anrede_imperativ' => 'Bitte melden Sie sich',
        );
    }

    $info = $event_id
        ? tc_get_event_mail_info( $event_id, $event_date )
        : array( 'title' => '', 'date' => $event_date, 'location' => '' );

    $storno_url = ! empty( $data['cancel_token'] )
        ? esc_url( add_query_arg( 'tc_cancel', $data['cancel_token'], home_url( '/' ) ) )
        : '';

    $pairs = array(
        '{{anrede}}'           => esc_html( $anrede_vals['anrede'] ),
        '{{anrede_possessiv}}' => esc_html( $anrede_vals['anrede_possessiv'] ),
        '{{anrede_akkusativ}}' => esc_html( $anrede_vals['anrede_akkusativ'] ),
        '{{anrede_dativ}}'     => esc_html( $anrede_vals['anrede_dativ'] ),
        '{{anrede_imperativ}}' => esc_html( $anrede_vals['anrede_imperativ'] ),
        '{{firstname}}'        => esc_html( $data['firstname'] ?? '' ),
        '{{lastname}}'         => esc_html( $data['lastname']  ?? '' ),
        '{{event_title}}'      => esc_html( $info['title'] ),
        '{{event_date}}'       => esc_html( $info['date'] ),
        '{{event_location}}'   => esc_html( $info['location'] ),
        '{{storno_url}}'       => $storno_url,
        '{{blogname}}'         => esc_html( get_option( 'blogname' ) ),
    );

    return str_replace( array_keys( $pairs ), array_values( $pairs ), $text );
}

// ---------------------------------------------
// Helper: Event-Infos für Mails aufbereiten
// ---------------------------------------------
function tc_get_event_mail_info( $event_id, $event_date = '' ) {
    $event    = get_post( $event_id );
    $title    = $event ? $event->post_title : '-';
    $location = wp_strip_all_tags( get_field( 'location', $event_id ) ?: '' );

    $date_type = get_field( 'event_date_type', $event_id );

    if ( $date_type === 'recurring' ) {
        $start_date = wp_date( 'Y-m-d' ); // nächste Occurrence als Referenz
        $start_time = get_field( 'recurring_time_start', $event_id );
        $end_date   = '';
    } else {
        $first      = tc_get_first_event_date( $event_id );
        $start_date = $first['date_start'] ?? '';
        $start_time = $first['time_start'] ?? '';
        $end_date   = $first['date_end']   ?? '';
    }

    if ( $event_date ) {
        $d        = DateTime::createFromFormat( 'Y-m-d', $event_date );
        $date_str = $d ? $d->format( 'd.m.Y' ) : $event_date;
        if ( $start_time ) $date_str .= ' um ' . $start_time . ' Uhr';
    } elseif ( $start_date ) {
        $d        = DateTime::createFromFormat( 'Y-m-d', $start_date );
        $date_str = $d ? $d->format( 'd.m.Y' ) : $start_date;
        if ( $start_time ) $date_str .= ' um ' . $start_time . ' Uhr';
        if ( $end_date && $end_date !== $start_date ) {
            $de        = DateTime::createFromFormat( 'Y-m-d', $end_date );
            $date_str .= ' - ' . ( $de ? $de->format( 'd.m.Y' ) : $end_date );
        }
    } else {
        $date_str = '-';
    }

    return array(
        'title'    => $title,
        'date'     => $date_str,
        'location' => $location ?: '-',
    );
}

// ---------------------------------------------
// Helper: Preis-Suffix für Abrechnungszeitraum
// ---------------------------------------------
function tc_price_period_suffix( int $event_id ): string {
    $period = get_field( 'price_period', $event_id ) ?: 'once';
    switch ( $period ) {
        case 'monthly': return ' / Monat';
        case 'yearly':  return ' / Jahr';
        default:        return '';
    }
}

// ---------------------------------------------
// Mail-Subject Defaults + Hilfsfunktionen
// ---------------------------------------------
function tc_get_mail_default( string $mail_id, string $field ): string {
    static $d = null;
    if ( $d === null ) {
        $d = array(
            'thankyou'      => array(
                'subject' => 'Vielen Dank für {{anrede_possessiv}} Anmeldung – {{event_title}}',
                'anrede'  => 'Hallo {{firstname}} {{lastname}},',
            ),
            'confirm'       => array(
                'subject' => '{{anrede_possessiv}} Anmeldung ist bestätigt – {{event_title}}',
                'anrede'  => 'Hallo {{firstname}} {{lastname}},',
            ),
            'cancel'        => array(
                'subject' => '{{anrede_possessiv}} Anmeldung konnte leider nicht bestätigt werden – {{event_title}}',
                'anrede'  => 'Hallo {{firstname}} {{lastname}},',
            ),
            'waitlist'      => array(
                'subject' => '{{anrede}} stehen auf der Warteliste – {{event_title}}',
                'anrede'  => 'Hallo {{firstname}} {{lastname}},',
            ),
            'waitlist_slot' => array(
                'subject' => 'Ein Platz ist frei geworden – {{event_title}}',
                'anrede'  => 'Hallo {{firstname}} {{lastname}},',
            ),
            'reminder'      => array(
                'subject' => 'Erinnerung: {{event_title}} – in 3 Tagen',
                'anrede'  => 'Hallo {{firstname}} {{lastname}},',
            ),
            'admin'         => array(
                'subject' => 'Neue Anmeldung: {{event_title}} – {{firstname}} {{lastname}}',
                'anrede'  => '',
            ),
        );
    }
    return $d[ $mail_id ][ $field ] ?? '';
}

/**
 * Liest eine Mail-Einstellung; fällt bei leerem Wert auf den Built-in-Default zurück.
 * Nötig weil tc_get_setting() einen gespeicherten Leer-String nicht durch den
 * Fallback-Parameter ersetzen kann (??-Operator prüft nur null).
 */
function tc_get_mail_setting( string $mail_id, string $field ): string {
    $val = tc_get_setting( 'mail_' . $mail_id . '_' . $field, '' );
    if ( $val !== '' ) return $val;
    return tc_get_mail_default( $mail_id, $field );
}

// ---------------------------------------------
// Mail 1: Dankes-Mail direkt nach Anmeldung
// ---------------------------------------------
function tc_send_thank_you_mail( $data ) {
    $event_id   = (int) ( $data['event_id'] ?? 0 );
    $event_date = $data['event_date'] ?? '';
    $is_trial   = in_array( get_field( 'event_price_type', $event_id ) ?: 'fixed', array( 'request', 'free' ), true );
    $headers    = array( 'Content-Type: text/html; charset=UTF-8' );

    $subject_tpl = tc_get_mail_setting( 'thankyou', 'subject' );
    $subject     = tc_resolve_placeholders( $subject_tpl, $data, $event_id, $event_date );
    $msg         = tc_build_mail_body( 'thankyou', $data, $event_id, $event_date );

    wp_mail( $data['email'], $subject, $msg, $headers );
}

// ---------------------------------------------
// Mail 2: Bestaetigung nach Admin-Freigabe
// ---------------------------------------------
function tc_send_confirmation_mail( $registration_id ) {
    $reg = tc_get_registration( $registration_id );
    if ( ! $reg ) return;

    $event_id   = (int) $reg['event_id'];
    $event_date = $reg['event_date'] ?? '';
    $is_trial   = in_array( get_field( 'event_price_type', $event_id ) ?: 'fixed', array( 'request', 'free' ), true );
    $headers    = array( 'Content-Type: text/html; charset=UTF-8' );

    $subject_tpl = tc_get_mail_setting( 'confirm', 'subject' );
    $subject     = tc_resolve_placeholders( $subject_tpl, $reg, $event_id, $event_date );
    $msg         = tc_build_mail_body( 'confirm', $reg, $event_id, $event_date );

    wp_mail( $reg['email'], $subject, $msg, $headers );
}

// ---------------------------------------------
// Mail 3: Absage nach Admin-Stornierung
// ---------------------------------------------
function tc_send_cancellation_mail( $registration_id ) {
    $reg = tc_get_registration( $registration_id );
    if ( ! $reg ) return;

    $event_id   = (int) $reg['event_id'];
    $event_date = $reg['event_date'] ?? '';
    $is_trial   = in_array( get_field( 'event_price_type', $event_id ) ?: 'fixed', array( 'request', 'free' ), true );
    $headers    = array( 'Content-Type: text/html; charset=UTF-8' );

    $subject_tpl = tc_get_mail_setting( 'cancel', 'subject' );
    $subject     = tc_resolve_placeholders( $subject_tpl, $reg, $event_id, $event_date );
    $msg         = tc_build_mail_body( 'cancel', $reg, $event_id, $event_date );

    wp_mail( $reg['email'], $subject, $msg, $headers );
}

// ---------------------------------------------
// Mail: Admin-Benachrichtigung (neue Anmeldung)
// ---------------------------------------------
function tc_send_admin_notification( $data ) {
    $admin_email = tc_get_setting( 'registration_email', get_option( 'admin_email' ) );
    if ( $admin_email === $data['email'] ) return;

    $event_id   = (int) ( $data['event_id'] ?? 0 );
    $event_date = $data['event_date'] ?? '';
    $is_trial   = in_array( get_field( 'event_price_type', $event_id ) ?: 'fixed', array( 'request', 'free' ), true );
    $headers    = array( 'Content-Type: text/html; charset=UTF-8' );

    $subject_tpl = tc_get_mail_setting( 'admin', 'subject' );
    $subject     = tc_resolve_placeholders( $subject_tpl, $data, $event_id, $event_date );
    $msg         = tc_build_mail_body( 'admin', $data, $event_id, $event_date );

    wp_mail( $admin_email, $subject, $msg, $headers );
}

// ---------------------------------------------
// Mail-Hilfsfunktionen (DRY)
// ---------------------------------------------
function tc_mail_wrapper_open( $blogname ) {
    return '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;">'
         . '<div style="max-width:600px;margin:0 auto;padding:24px;background:#f9f9f9;'
         . 'border:1px solid #e0e0e0;border-radius:8px;">';
}

function tc_mail_wrapper_close() {
    return '</div></body></html>';
}

function tc_mail_signature( $blogname ) {
    return '<hr style="border:none;border-top:1px solid #ddd;margin:20px 0;">'
         . '<p style="font-size:.9em;color:#666;">Mit freundlichen Gruessen<br>'
         . '<strong>' . esc_html( $blogname ) . '</strong></p>';
}

function tc_event_info_block( $info ) {
    return '<div style="background:#eef2ff;border-left:4px solid #4f46e5;padding:14px 18px;'
         . 'border-radius:4px;margin:16px 0;">'
         . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
         . tc_mail_row( 'Veranstaltung', esc_html( $info['title'] ) )
         . tc_mail_row( 'Datum',         esc_html( $info['date'] ) )
         . tc_mail_row( 'Ort',           esc_html( $info['location'] ) )
         . '</table></div>';
}

function tc_mail_cancel_block( string $cancel_url ): string {
    return '<div style="margin-top:20px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:13px;color:#6b7280;">'
         . 'Möchten Sie Ihre Anmeldung stornieren? '
         . '<a href="' . esc_url( $cancel_url ) . '" style="color:#dc2626;text-decoration:underline;">Hier klicken</a>.'
         . '</div>';
}

function tc_mail_row( $label, $value ) {
    return '<tr>'
         . '<td style="padding:4px 12px 4px 0;font-weight:600;white-space:nowrap;vertical-align:top;color:#374151;">'
         . esc_html( $label ) . ':</td>'
         . '<td style="padding:4px 0;color:#111827;">' . $value . '</td>'
         . '</tr>';
}

// ---------------------------------------------
// Strukturierte Mail-Body-Generierung
// ---------------------------------------------
function tc_build_event_block( int $event_id, string $event_date = '' ): string {
    if ( ! $event_id ) return '';
    $info = tc_get_event_mail_info( $event_id, $event_date );
    return tc_event_info_block( $info );
}

function tc_build_mail_body( string $mail_id, array $data, int $event_id = 0, string $event_date = '' ): string {
    static $field_defaults = null;
    if ( $field_defaults === null ) {
        $sig = "Mit freundlichen Grüßen\n{{blogname}}";
        $field_defaults = array(
            'thankyou'      => array(
                'anrede'     => 'Hallo {{firstname}} {{lastname}},',
                'haupttext'  => 'wir haben {{anrede_possessiv}} Anmeldung erhalten und melden uns zeitnah mit einer Bestätigung bei {{anrede_dativ}}.',
                'show_event' => '1',
                'abschluss'  => 'Bei Fragen stehen wir {{anrede_dativ}} gerne zur Verfügung.',
                'signatur'   => $sig,
            ),
            'confirm'       => array(
                'anrede'     => 'Hallo {{firstname}} {{lastname}},',
                'haupttext'  => 'wir freuen uns, {{anrede_possessiv}} Anmeldung hiermit offiziell zu bestätigen. Wir sehen uns beim Termin!',
                'show_event' => '1',
                'abschluss'  => 'Bei Fragen stehen wir {{anrede_dativ}} gerne zur Verfügung.',
                'signatur'   => $sig,
            ),
            'cancel'        => array(
                'anrede'     => 'Hallo {{firstname}} {{lastname}},',
                'haupttext'  => 'leider müssen wir {{anrede_dativ}} mitteilen, dass {{anrede_possessiv}} Anmeldung für den folgenden Termin nicht bestätigt werden konnte.',
                'show_event' => '1',
                'abschluss'  => 'Bei Fragen oder wenn {{anrede}} einen alternativen Termin buchen möchten, melden {{anrede}} sich gerne bei uns.',
                'signatur'   => $sig,
            ),
            'waitlist'      => array(
                'anrede'     => 'Hallo {{firstname}} {{lastname}},',
                'haupttext'  => 'vielen Dank für {{anrede_possessiv}} Interesse! {{anrede}} wurden auf die Warteliste für folgende Veranstaltung eingetragen:',
                'show_event' => '1',
                'abschluss'  => 'Wir benachrichtigen {{anrede_akkusativ}} umgehend, sobald ein Platz frei wird.',
                'signatur'   => $sig,
            ),
            'waitlist_slot' => array(
                'anrede'     => 'Hallo {{firstname}} {{lastname}},',
                'haupttext'  => 'gute Neuigkeit! Für folgende Veranstaltung ist ein Platz frei geworden:',
                'show_event' => '1',
                'abschluss'  => '{{anrede_possessiv}} Anfrage wird nun bearbeitet. {{anrede}} erhalten zeitnah eine Bestätigung.',
                'signatur'   => $sig,
            ),
            'reminder'      => array(
                'anrede'     => 'Hallo {{firstname}} {{lastname}},',
                'haupttext'  => 'wir möchten {{anrede_akkusativ}} daran erinnern, dass in 3 Tagen folgender Termin stattfindet:',
                'show_event' => '1',
                'abschluss'  => 'Wir freuen uns auf {{anrede_akkusativ}}!',
                'signatur'   => $sig,
            ),
            'admin'         => array(
                'anrede'     => 'Neue Anmeldung eingegangen',
                'haupttext'  => 'Für folgende Veranstaltung wurde eine neue Anmeldung von {{firstname}} {{lastname}} eingegangen.',
                'show_event' => '1',
                'abschluss'  => '',
                'signatur'   => '{{blogname}}',
            ),
        );
    }

    $d        = $field_defaults[ $mail_id ] ?? array();
    $blogname = get_option( 'blogname' );
    $pfx      = 'mail_' . $mail_id . '_';

    // 1. Experten-Modus → expert_html verwenden
    $expert_mode = tc_get_setting( $pfx . 'expert_mode', '0' );
    if ( $expert_mode === '1' ) {
        $html = tc_get_setting( $pfx . 'expert_html', '' );
        if ( ! $html ) $html = tc_get_setting( $pfx . 'body', '' );
        if ( $html ) {
            if ( strpos( $html, '<!-- EVENT_BLOCK -->' ) !== false ) {
                $html = str_replace( '<!-- EVENT_BLOCK -->', tc_build_event_block( $event_id, $event_date ), $html );
            }
            $html = tc_resolve_placeholders( $html, $data, $event_id, $event_date );
            return tc_mail_wrapper_open( $blogname ) . $html . tc_mail_wrapper_close();
        }
    }

    // 2. Legacy-Body (Rückwärtskompatibilität)
    $legacy_body = tc_get_setting( $pfx . 'body', '' );
    if ( $legacy_body ) {
        $legacy_body = tc_resolve_placeholders( $legacy_body, $data, $event_id, $event_date );
        return tc_mail_wrapper_open( $blogname ) . $legacy_body . tc_mail_wrapper_close();
    }

    // 3. Strukturierte Felder
    $anrede    = tc_get_setting( $pfx . 'anrede',     $d['anrede']     ?? '' );
    $haupttext = tc_get_setting( $pfx . 'haupttext',  $d['haupttext']  ?? '' );
    $show_ev   = tc_get_setting( $pfx . 'show_event', $d['show_event'] ?? '1' );
    $abschluss = tc_get_setting( $pfx . 'abschluss',  $d['abschluss']  ?? '' );
    $signatur  = tc_get_setting( $pfx . 'signatur',   $d['signatur']   ?? '' );

    $body = '';
    if ( $anrede )         $body .= '<p>' . nl2br( esc_html( $anrede ) )    . '</p>' . "\n";
    if ( $haupttext )      $body .= '<p>' . nl2br( esc_html( $haupttext ) ) . '</p>' . "\n";
    if ( $show_ev === '1' ) $body .= tc_build_event_block( $event_id, $event_date );
    if ( $abschluss )      $body .= '<p>' . nl2br( esc_html( $abschluss ) ) . '</p>' . "\n";
    if ( $signatur )       $body .= '<hr style="border:none;border-top:1px solid #ddd;margin:20px 0;">' . "\n"
                                  . '<p style="font-size:.9em;color:#666;">' . nl2br( esc_html( $signatur ) ) . '</p>' . "\n";

    $body = tc_resolve_placeholders( $body, $data, $event_id, $event_date );
    return tc_mail_wrapper_open( $blogname ) . $body . tc_mail_wrapper_close();
}

// ---------------------------------------------
// Hook: nach Anmeldung - Dankes-Mail + Admin
// ---------------------------------------------
add_action( 'tc_registration_submitted', function ( $registration_id, $data ) {
    tc_send_thank_you_mail( $data );
    tc_send_admin_notification( $data );
}, 10, 2 );

// ---------------------------------------------
// AJAX: Anmeldung erstellen
// ---------------------------------------------
add_action( 'wp_ajax_nopriv_' . TC_AJAX_SUBMIT_REGISTRATION, 'tc_handle_registration_submission' );
add_action( 'wp_ajax_' . TC_AJAX_SUBMIT_REGISTRATION,        'tc_handle_registration_submission' );

function tc_handle_registration_submission() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

    // Rate Limiting: max. TC_RATE_LIMIT_COUNT Anmeldungen pro IP innerhalb von TC_RATE_LIMIT_SECONDS
    $ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
    $rl_key    = 'tc_reg_limit_' . md5( $ip );
    $rl_count  = (int) get_transient( $rl_key );
    if ( $rl_count >= TC_RATE_LIMIT_COUNT ) {
        wp_send_json_error( array( 'message' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.' ) );
    }
    set_transient( $rl_key, $rl_count + 1, TC_RATE_LIMIT_SECONDS );

    global $wpdb;
    $table_name = $wpdb->prefix . 'tc_registrations';

    $firstname  = sanitize_text_field(    $_POST['firstname']  ?? '' );
    $lastname   = sanitize_text_field(    $_POST['lastname']   ?? '' );
    $email      = sanitize_email(         $_POST['email']      ?? '' );
    $phone      = sanitize_text_field(    $_POST['phone']      ?? '' );
    $address    = sanitize_text_field(    $_POST['address']    ?? '' );
    $zip        = sanitize_text_field(    $_POST['zip']        ?? '' );
    $city       = sanitize_text_field(    $_POST['city']       ?? '' );
    $event_id   = absint(                 $_POST['event_id']   ?? 0  );
    $event_date = sanitize_text_field(    $_POST['event_date'] ?? '' );
    $notes      = sanitize_textarea_field($_POST['notes']      ?? '' );

    if ( $event_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $event_date ) ) {
        wp_send_json_error( array( 'message' => 'Ungültiges Datumsformat.' ) );
    }

    if ( ! $firstname || ! $lastname || ! $email || ! is_email( $email ) || ! $event_id ) {
        wp_send_json_error( array( 'message' => 'Bitte fuellen Sie alle erforderlichen Felder aus.' ) );
    }

    if ( get_post_type( $event_id ) !== 'time_event' ) {
        wp_send_json_error( array( 'message' => 'Diese Veranstaltung existiert nicht.' ) );
    }

    $is_trial = in_array( get_field( 'event_price_type', $event_id ) ?: 'fixed', array( 'request', 'free' ), true );

    $track_p = get_field( 'registration_limit', $event_id );
    $max_p   = get_field( 'max_participants',   $event_id );
    if ( $track_p && $max_p ) {
        $cur = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE event_id = %d AND status IN ('pending','confirmed')",
            $event_id
        ) );
        if ( $cur >= (int) $max_p ) {
            wp_send_json_error( array( 'message' => 'Leider ist dieser Termin bereits ausgebucht.' ) );
        }
    }

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE email = %s AND event_id = %d",
        $email, $event_id
    ) );
    if ( $existing ) {
        $dup_msg = $is_trial
            ? tc_get_setting( 'label_duplicate_msg_trial', 'Du hast bereits eine Probetraining-Anfrage für diese Veranstaltung gestellt.' )
            : tc_get_setting( 'label_duplicate_msg',       'Sie sind bereits für diese Veranstaltung angemeldet.' );
        wp_send_json_error( array( 'message' => $dup_msg ) );
    }

    $cancel_token = bin2hex( random_bytes( 32 ) );

    $inserted = $wpdb->insert(
        $table_name,
        array(
            'firstname'    => $firstname,
            'lastname'     => $lastname,
            'email'        => $email,
            'phone'        => $phone,
            'address'      => $address,
            'zip'          => $zip,
            'city'         => $city,
            'event_id'     => $event_id,
            'event_date'   => $event_date ?: null,
            'status'       => 'pending',
            'notes'        => $notes,
            'cancel_token' => $cancel_token,
            'created_at'   => time(),
        ),
        array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d' )
    );

    if ( $inserted === false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'TC Registration DB error: ' . $wpdb->last_error );
        }
        wp_send_json_error( array( 'message' => 'Fehler beim Speichern der Anmeldung.' ) );
    }

    $new_id = $wpdb->insert_id;

    do_action( 'tc_registration_submitted', $new_id, array(
        'firstname'    => $firstname,
        'lastname'     => $lastname,
        'email'        => $email,
        'phone'        => $phone,
        'address'      => $address,
        'zip'          => $zip,
        'city'         => $city,
        'event_id'     => $event_id,
        'event_date'   => $event_date,
        'notes'        => $notes,
        'cancel_token' => $cancel_token,
    ) );

    $success_msg = $is_trial
        ? tc_get_setting( 'label_success_msg_trial', 'Vielen Dank! Deine Anfrage für ein Probetraining wurde erfolgreich übermittelt. Wir melden uns zeitnah bei dir.' )
        : tc_get_setting( 'label_success_msg',       'Vielen Dank! Ihre Anmeldung wurde erfolgreich gespeichert.' );

    wp_send_json_success( array(
        'message'         => $success_msg,
        'registration_id' => $new_id,
    ) );
}

// ---------------------------------------------
// AJAX: Event-Details laden
// ---------------------------------------------
add_action( 'wp_ajax_nopriv_' . TC_AJAX_GET_EVENT_DETAILS, 'tc_get_event_details_ajax' );
add_action( 'wp_ajax_' . TC_AJAX_GET_EVENT_DETAILS,        'tc_get_event_details_ajax' );

function tc_get_event_details_ajax() {
    check_ajax_referer( 'tc_registration_nonce', 'nonce' );

    $event_id = absint( $_POST['event_id'] ?? 0 );
    if ( ! $event_id || get_post_type( $event_id ) !== 'time_event' ) {
        wp_send_json_error( array( 'message' => 'Event nicht gefunden.' ) );
    }

    $event  = get_post( $event_id );
    $fields = get_fields( $event_id ) ?: array();

    $leadership = $fields['event_host']         ?? null;
    $location   = $fields['location']           ?? null;
    $track_p    = $fields['registration_limit'] ?? null;
    $max_p      = $fields['max_participants']   ?? null;
    $date_type  = $fields['event_date_type']    ?? 'single';

    $dates = array(); $is_multiday = false; $is_recurring_event = false;
    $start_date = null; $start_time = null;

    if ( $date_type === 'recurring' ) {
        $start_time        = $fields['recurring_time_start'] ?? null;
        $recurring_weekday = $fields['recurring_weekday']    ?? null;
        $interval          = max( 1, min( 3, (int) ( $fields['recurring_interval'] ?? 1 ) ) );

        if ( $recurring_weekday !== '' && $recurring_weekday !== null ) {
            $is_recurring_event = true;
            $start_date         = wp_date( 'Y-m-d' ); // rolling window: use today
            try {
                $target   = (int) $recurring_weekday;
                $today    = new DateTime( 'today' );
                $until_dt = new DateTime( '+52 weeks 23:59:59' );
                $cur      = clone $today;
                $diff     = ( $target - (int) $cur->format('w') + 7 ) % 7;
                $cur->modify( "+{$diff} days" );
                $limit = 0;
                while ( $cur <= $until_dt && $limit < TC_RECURRING_LIMIT ) {
                    $dates[] = $cur->format('Y-m-d');
                    $cur->modify( "+{$interval} weeks" );
                    $limit++;
                }
            } catch ( Exception $e ) {
                // ignore
            }
        }
    } else {
        // Einzeltermin: Datum-Logik ausschließlich über event_dates Repeater
        $rows = get_field( 'event_dates', $event_id ) ?: array();
        foreach ( $rows as $row ) {
            if ( ! empty( $row['date_start'] ) ) {
                $dates[] = $row['date_start'];
            }
        }
        $start_date  = $rows[0]['date_start'] ?? null;
        $start_time  = $rows[0]['time_start'] ?? null;
        $is_multiday = (bool) array_filter( $rows, fn( $r ) => ! empty( $r['date_end'] ) );
    }

    $current_reg = 0; $is_full = false;
    if ( $track_p && $max_p ) {
        global $wpdb;
        $current_reg = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tc_registrations WHERE event_id = %d AND status IN ('pending','confirmed')",
            $event_id
        ) );
        $is_full = $current_reg >= (int) $max_p;
    }

    wp_send_json_success( array(
        'title'                 => $event->post_title,
        'leadership'            => $leadership ?: 'Nicht angegeben',
        'location'              => wp_strip_all_tags( $location ?: 'Nicht angegeben' ),
        'start_date'            => $start_date ?: 'Nicht angegeben',
        'start_time'            => $start_time ?: '',
        'is_multiday'           => (bool) $is_multiday,
        'is_recurring'          => (bool) $is_recurring_event,
        'dates'                 => $dates,
        'track_participants'    => (bool) $track_p,
        'max_participants'      => $max_p ? (int) $max_p : 0,
        'current_registrations' => $current_reg,
        'is_full'               => $is_full,
    ) );
}
