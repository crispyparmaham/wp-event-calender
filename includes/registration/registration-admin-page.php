<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Admin-Menüseite für Anmeldungen
// ─────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=time_event',
        'Anmeldungen',
        'Anmeldungen',
        'administrator',
        'training-registrations',
        'tc_render_registrations_page'
    );
} );

function tc_render_registrations_page() {
    if ( ! current_user_can( 'administrator' ) ) wp_die( 'Keine Berechtigung.' );

    // Löschen
    if ( isset( $_GET['action'], $_GET['registration_id'] ) && $_GET['action'] === 'delete' ) {
        check_admin_referer( 'tc_delete_registration', 'nonce' );
        tc_delete_registration( absint( $_GET['registration_id'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>Anmeldung gelöscht.</p></div>';
    }

    // Bearbeiten
    if ( isset( $_GET['action'], $_GET['registration_id'] ) && $_GET['action'] === 'edit' ) {
        tc_render_registration_form( absint( $_GET['registration_id'] ) );
        return;
    }

    $filter_event  = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
    $all           = tc_get_all_registrations();
    $registrations = $filter_event
        ? array_values( array_filter( $all, fn( $r ) => (int) $r['event_id'] === $filter_event ) )
        : $all;

    $filter_title = $filter_event ? get_the_title( $filter_event ) : '';
    $nonce_confirm = wp_create_nonce( 'tc_admin_nonce' );
    ?>
    <div class="wrap">
        <h1>
            Anmeldungen
            <?php if ( $filter_title ) : ?>
                <span style="font-size:14px;font-weight:400;color:#6b7280;margin-left:8px;">
                    gefiltert: <?php echo esc_html( $filter_title ); ?>
                    &nbsp;<a href="<?php echo esc_url( admin_url( 'admin.php?page=training-registrations' ) ); ?>" style="font-size:13px;">× Filter aufheben</a>
                </span>
            <?php endif; ?>
        </h1>

        <?php
    $export_url = wp_nonce_url(
        add_query_arg( [
            'page'       => 'training-registrations',
            'tc_export'  => 'csv',
            'event_id'   => $filter_event ?: '',
        ], admin_url( 'admin.php' ) ),
        'tc_export_csv',
        'nonce'
    );
    ?>
    <p>
        <a href="<?php echo esc_url( $export_url ); ?>" class="button">
            ⬇ Als CSV exportieren
        </a>
    </p>

    <?php if ( empty( $registrations ) ) : ?>
            <p>Keine Anmeldungen vorhanden.</p>
        <?php else : ?>
        <table class="wp-list-table widefat striped tc-reg-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Telefon</th>
                    <th>Veranstaltung</th>
                    <th>Datum</th>
                    <th>Status</th>
                    <th>Angemeldet am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $registrations as $reg ) :
                    $status      = $reg['status'];
                    $event_title = $reg['event_id'] ? get_the_title( $reg['event_id'] ) : '–';

                    $date_str = '–';
                    if ( $reg['event_date'] ) {
                        $d = DateTime::createFromFormat( 'Y-m-d', $reg['event_date'] );
                        $date_str = $d ? $d->format( 'd.m.Y' ) : $reg['event_date'];
                    } elseif ( $reg['event_id'] ) {
                        $sd = get_field( 'start_date', $reg['event_id'] );
                        if ( $sd ) {
                            $d = DateTime::createFromFormat( 'Y-m-d', $sd );
                            $date_str = $d ? $d->format( 'd.m.Y' ) : $sd;
                        }
                    }
                ?>
                <tr id="tc-reg-row-<?php echo esc_attr( $reg['id'] ); ?>">
                    <td><strong><?php echo esc_html( $reg['firstname'] . ' ' . $reg['lastname'] ); ?></strong></td>
                    <td><a href="mailto:<?php echo esc_attr( $reg['email'] ); ?>"><?php echo esc_html( $reg['email'] ); ?></a></td>
                    <td><?php echo esc_html( $reg['phone'] ?: '–' ); ?></td>
                    <td>
                        <?php if ( $reg['event_id'] ) : ?>
                            <a href="<?php echo esc_url( get_edit_post_link( $reg['event_id'] ) ); ?>"><?php echo esc_html( $event_title ); ?></a>
                        <?php else : ?>–<?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $date_str ); ?></td>
                    <td>
                        <span class="tc-status-badge tc-status-<?php echo esc_attr( $status ); ?>">
                            <?php echo match( $status ) {
                                'confirmed' => '✓ Bestätigt',
                                'cancelled' => '✗ Storniert',
                                'waitlist'  => '⏸ Warteliste',
                                default     => '⏳ Ausstehend',
                            }; ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( date_i18n( 'd.m.Y H:i', $reg['created_at'] ) ); ?></td>
                    <td class="tc-action-btns">
                        <?php if ( $status !== 'confirmed' ) : ?>
                            <button class="tc-btn-confirm button button-small"
                                    data-id="<?php echo esc_attr( $reg['id'] ); ?>"
                                    data-nonce="<?php echo esc_attr( $nonce_confirm ); ?>"
                                    title="Bestätigen &amp; Bestätigungsmail senden">✓</button>
                        <?php endif; ?>
                        <?php if ( $status !== 'cancelled' ) : ?>
                            <button class="tc-btn-cancel button button-small"
                                    data-id="<?php echo esc_attr( $reg['id'] ); ?>"
                                    data-nonce="<?php echo esc_attr( $nonce_confirm ); ?>"
                                    title="Stornieren">✗</button>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( wp_nonce_url(
                            admin_url( 'admin.php?page=training-registrations&action=edit&registration_id=' . $reg['id'] ),
                            'tc_edit_registration', 'nonce'
                        ) ); ?>" class="button button-small" title="Bearbeiten">✏️</a>
                        <a href="<?php echo esc_url( wp_nonce_url(
                            admin_url( 'admin.php?page=training-registrations&action=delete&registration_id=' . $reg['id'] ),
                            'tc_delete_registration', 'nonce'
                        ) ); ?>" class="button button-small button-link-delete"
                           onclick="return confirm('Anmeldung wirklich löschen?');" title="Löschen">🗑</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <script>
    jQuery(function($) {
        $(document).on('click', '.tc-btn-confirm', function() {
            var $btn = $(this);
            var id   = $btn.data('id');
            if ( ! confirm('Anmeldung bestätigen und Bestätigungsmail senden?') ) return;
            $btn.prop('disabled', true).text('…');
            $.post(ajaxurl, {
                action: 'tc_update_registration_status',
                nonce:  $btn.data('nonce'),
                registration_id: id,
                status: 'confirmed',
                send_confirmation_mail: 1
            }, function(res) {
                if ( res.success ) {
                    var $row = $('#tc-reg-row-' + id);
                    $row.find('.tc-status-badge')
                        .attr('class', 'tc-status-badge tc-status-confirmed')
                        .text('✓ Bestätigt');
                    $btn.remove();
                } else {
                    alert(res.data || 'Fehler.');
                    $btn.prop('disabled', false).text('✓');
                }
            });
        });

        $(document).on('click', '.tc-btn-cancel', function() {
            var $btn = $(this);
            var id   = $btn.data('id');
            if ( ! confirm('Anmeldung stornieren?') ) return;
            $btn.prop('disabled', true).text('…');
            $.post(ajaxurl, {
                action: 'tc_update_registration_status',
                nonce:  $btn.data('nonce'),
                registration_id: id,
                status: 'cancelled',
                send_confirmation_mail: 1
            }, function(res) {
                if ( res.success ) {
                    var $row = $('#tc-reg-row-' + id);
                    $row.find('.tc-status-badge')
                        .attr('class', 'tc-status-badge tc-status-cancelled')
                        .text('✗ Storniert');
                    $btn.remove();
                } else {
                    alert(res.data || 'Fehler.');
                    $btn.prop('disabled', false).text('✗');
                }
            });
        });
    });
    </script>
    <?php
}

// ─────────────────────────────────────────────
// AJAX: Status aktualisieren (+ opt. Mail)
// ─────────────────────────────────────────────
add_action( 'wp_ajax_tc_update_registration_status', function () {
    check_ajax_referer( 'tc_admin_nonce', 'nonce' );

    if ( ! current_user_can( 'administrator' ) ) {
        wp_send_json_error( 'Keine Berechtigung.' );
    }

    $id         = absint( $_POST['registration_id'] ?? 0 );
    $new_status = sanitize_text_field( $_POST['status'] ?? '' );
    $send_mail  = ! empty( $_POST['send_confirmation_mail'] );

    if ( ! $id || ! in_array( $new_status, array( 'pending', 'confirmed', 'cancelled', 'waitlist' ), true ) ) {
        wp_send_json_error( 'Ungültige Daten.' );
    }

    $old_reg    = tc_get_registration( $id );
    $old_status = $old_reg ? $old_reg['status'] : '';

    tc_update_registration( $id, array( 'status' => $new_status ) );

    if ( $new_status === 'confirmed' && $send_mail ) {
        tc_send_confirmation_mail( $id );
    }

    if ( $new_status === 'cancelled' && $send_mail ) {
        tc_send_cancellation_mail( $id );
    }

    if ( $new_status === 'cancelled' && $old_status === 'confirmed' ) {
        do_action( 'tc_registration_cancelled', $id );
    }

    wp_send_json_success();
} );

// ─────────────────────────────────────────────
// Bearbeitungsformular
// ─────────────────────────────────────────────
function tc_render_registration_form( $registration_id ) {
    check_admin_referer( 'tc_edit_registration', 'nonce' );
    $reg = tc_get_registration( $registration_id );
    if ( ! $reg ) {
        echo '<div class="wrap"><p class="notice notice-error">Anmeldung nicht gefunden.</p></div>';
        return;
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tc_save_registration'] ) ) {
        check_admin_referer( 'tc_save_registration_nonce', 'nonce' );
        $update = array(
            'firstname'  => sanitize_text_field(     $_POST['firstname']  ?? '' ),
            'lastname'   => sanitize_text_field(     $_POST['lastname']   ?? '' ),
            'email'      => sanitize_email(          $_POST['email']      ?? '' ),
            'phone'      => sanitize_text_field(     $_POST['phone']      ?? '' ),
            'address'    => sanitize_text_field(     $_POST['address']    ?? '' ),
            'zip'        => sanitize_text_field(     $_POST['zip']        ?? '' ),
            'city'       => sanitize_text_field(     $_POST['city']       ?? '' ),
            'event_id'   => absint(                  $_POST['event_id']   ?? 0  ),
            'event_date' => sanitize_text_field(     $_POST['event_date'] ?? '' ),
            'status'     => sanitize_text_field(     $_POST['status']     ?? 'pending' ),
            'notes'      => sanitize_textarea_field( $_POST['notes']      ?? '' ),
        );
        if ( $update['firstname'] && $update['lastname'] && $update['email'] ) {
            tc_update_registration( $registration_id, $update );
            echo '<div class="notice notice-success is-dismissible"><p>Anmeldung gespeichert.</p></div>';
            $reg = tc_get_registration( $registration_id );
        } else {
            echo '<div class="notice notice-error"><p>Pflichtfelder fehlen.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Anmeldung bearbeiten
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=training-registrations' ) ); ?>" class="page-title-action">← Zurück</a>
        </h1>
        <form method="POST" style="max-width:600px;">
            <?php wp_nonce_field( 'tc_save_registration_nonce' ); ?>
            <table class="form-table">
                <?php
                $fields = array(
                    array( 'firstname', 'Vorname',              'text',  true  ),
                    array( 'lastname',  'Nachname',             'text',  true  ),
                    array( 'email',     'E-Mail',               'email', true  ),
                    array( 'phone',     'Telefon',              'tel',   false ),
                    array( 'address',   'Straße & Hausnummer',  'text',  false ),
                    array( 'zip',       'PLZ',                  'text',  false ),
                    array( 'city',      'Ort',                  'text',  false ),
                );
                foreach ( $fields as [ $name, $label, $type, $req ] ) : ?>
                <tr>
                    <th><label for="<?php echo $name; ?>"><?php echo $label; ?><?php if ( $req ) echo ' <span style="color:red">*</span>'; ?></label></th>
                    <td><input type="<?php echo $type; ?>" id="<?php echo $name; ?>" name="<?php echo $name; ?>"
                               class="regular-text" value="<?php echo esc_attr( $reg[ $name ] ); ?>" <?php if ( $req ) echo 'required'; ?> /></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <th><label for="event_id">Veranstaltung <span style="color:red">*</span></label></th>
                    <td>
                        <select id="event_id" name="event_id" required>
                            <option value="">– Wählen –</option>
                            <?php foreach ( get_posts( array( 'post_type' => 'time_event', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $ev ) : ?>
                                <option value="<?php echo esc_attr( $ev->ID ); ?>" <?php selected( $reg['event_id'], $ev->ID ); ?>><?php echo esc_html( $ev->post_title ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="event_date">Gewähltes Datum</label></th>
                    <td><input type="date" id="event_date" name="event_date" value="<?php echo esc_attr( $reg['event_date'] ); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="status">Status</label></th>
                    <td>
                        <select id="status" name="status">
                            <option value="pending"   <?php selected( $reg['status'], 'pending' ); ?>>Ausstehend</option>
                            <option value="confirmed" <?php selected( $reg['status'], 'confirmed' ); ?>>Bestätigt</option>
                            <option value="cancelled" <?php selected( $reg['status'], 'cancelled' ); ?>>Storniert</option>
                            <option value="waitlist"  <?php selected( $reg['status'], 'waitlist' ); ?>>Warteliste</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="notes">Notizen</label></th>
                    <td><textarea id="notes" name="notes" class="large-text" rows="4"><?php echo esc_textarea( $reg['notes'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th>Angemeldet am</th>
                    <td><?php echo esc_html( date_i18n( 'd.m.Y H:i', $reg['created_at'] ) ); ?></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="tc_save_registration" class="button button-primary">Speichern</button>
            </p>
        </form>
    </div>
    <?php
}

// ─────────────────────────────────────────────
// Admin Styles
// ─────────────────────────────────────────────
add_action( 'admin_head', function () { ?>
    <style>
        .tc-reg-table td { vertical-align: middle; }
        .tc-status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .tc-status-pending   { background: #fef3c7; color: #92400e; }
        .tc-status-confirmed { background: #d1fae5; color: #065f46; }
        .tc-status-cancelled { background: #fee2e2; color: #991b1b; }
        .tc-status-waitlist  { background: #ede9fe; color: #5b21b6; }
        .tc-action-btns { white-space: nowrap; display: flex; gap: 4px; align-items: center; }
        .tc-btn-confirm.button {
            background: #059669; border-color: #047857; color: #fff; font-weight: 700; min-width: 30px;
        }
        .tc-btn-confirm.button:hover { background: #047857; color: #fff; }
        .tc-btn-cancel.button {
            background: #dc2626; border-color: #b91c1c; color: #fff; font-weight: 700; min-width: 30px;
        }
        .tc-btn-cancel.button:hover { background: #b91c1c; color: #fff; }
    </style>
<?php } );
