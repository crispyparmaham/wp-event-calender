<?php
defined( 'ABSPATH' ) || exit;

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
