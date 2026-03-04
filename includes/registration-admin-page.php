<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Admin-Menüseite für Anmeldungen
// ─────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=training_event',
        'Anmeldungen',
        'Anmeldungen',
        'administrator',
        'training-registrations',
        'tc_render_registrations_page'
    );
} );

function tc_render_registrations_page() {
    // Berechtigungsprüfung
    if ( ! current_user_can( 'administrator' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    // Löschen-Aktion verarbeiten
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['registration_id'] ) ) {
        check_admin_referer( 'tc_delete_registration', 'nonce' );
        tc_delete_registration( absint( $_GET['registration_id'] ) );
        echo '<div class="notice notice-success"><p>Anmeldung gelöscht.</p></div>';
    }

    // Edit-Aktion verarbeiten
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['registration_id'] ) ) {
        tc_render_registration_form( absint( $_GET['registration_id'] ) );
        return;
    }

    // Anmeldungen laden
    $registrations = tc_get_all_registrations();
    ?>

    <div class="wrap">
        <h1>Anmeldungen
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=training-registrations&action=new' ) ); ?>" class="page-title-action">
                Neue Anmeldung hinzufügen
            </a>
        </h1>

        <?php if ( empty( $registrations ) ) : ?>
            <p>Keine Anmeldungen vorhanden.</p>
        <?php else : ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th scope="col" style="width: 15%;">Vorname</th>
                        <th scope="col" style="width: 15%;">Nachname</th>
                        <th scope="col" style="width: 20%;">E-Mail</th>
                        <th scope="col" style="width: 15%;">Telefon</th>
                        <th scope="col" style="width: 20%;">Veranstaltung</th>
                        <th scope="col" style="width: 10%;">Status</th>
                        <th scope="col" style="width: 15%;">Angemeldet am</th>
                        <th scope="col" style="width: 10%;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $registrations as $reg ) : ?>
                        <tr>
                            <td><?php echo esc_html( $reg['firstname'] ); ?></td>
                            <td><?php echo esc_html( $reg['lastname'] ); ?></td>
                            <td><a href="mailto:<?php echo esc_attr( $reg['email'] ); ?>"><?php echo esc_html( $reg['email'] ); ?></a></td>
                            <td><?php echo esc_html( $reg['phone'] ?: '—' ); ?></td>
                            <td>
                                <?php if ( $reg['event_id'] ) : ?>
                                    <a href="<?php echo esc_url( get_edit_post_link( $reg['event_id'] ) ); ?>">
                                        <?php echo esc_html( get_the_title( $reg['event_id'] ) ); ?>
                                    </a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <select class="tc-status-select" data-registration-id="<?php echo esc_attr( $reg['id'] ); ?>">
                                    <option value="pending" <?php selected( $reg['status'], 'pending' ); ?>>Ausstehend</option>
                                    <option value="confirmed" <?php selected( $reg['status'], 'confirmed' ); ?>>Bestätigt</option>
                                    <option value="cancelled" <?php selected( $reg['status'], 'cancelled' ); ?>>Storniert</option>
                                </select>
                            </td>
                            <td><?php echo esc_html( date_i18n( 'd.m.Y H:i', $reg['created_at'] ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=training-registrations&action=edit&registration_id=' . $reg['id'] ), 'tc_edit_registration', 'nonce' ) ); ?>" class="button button-small">
                                    Bearbeiten
                                </a>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=training-registrations&action=delete&registration_id=' . $reg['id'] ), 'tc_delete_registration', 'nonce' ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('Möchten Sie diese Anmeldung wirklich löschen?');">
                                    Löschen
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('.tc-status-select').on('change', function() {
                var registrationId = $(this).data('registration-id');
                var newStatus = $(this).val();
                var $select = $(this);

                $.ajax({
                    type: 'POST',
                    url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                    data: {
                        action: 'tc_update_registration_status',
                        nonce: '<?php echo wp_create_nonce( 'tc_admin_nonce' ); ?>',
                        registration_id: registrationId,
                        status: newStatus
                    },
                    success: function(response) {
                        if (response.success) {
                            $select.closest('tr').find('td').fadeOut(200).fadeIn(200);
                        } else {
                            alert('Fehler beim Aktualisieren.');
                            location.reload();
                        }
                    }
                });
            });
        });
    </script>

    <?php
}

// ─────────────────────────────────────────────
// Anmeldungs-Bearbeitungsformular
// ─────────────────────────────────────────────
function tc_render_registration_form( $registration_id ) {
    check_admin_referer( 'tc_edit_registration', 'nonce' );

    $reg = tc_get_registration( $registration_id );

    if ( ! $reg ) {
        echo '<div class="wrap"><p class="error">Anmeldung nicht gefunden.</p></div>';
        return;
    }

    // Formular verarbeiten
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tc_save_registration'] ) ) {
        check_admin_referer( 'tc_save_registration_nonce', 'nonce' );

        $firstname = sanitize_text_field( $_POST['firstname'] ?? '' );
        $lastname  = sanitize_text_field( $_POST['lastname'] ?? '' );
        $email     = sanitize_email( $_POST['email'] ?? '' );
        $phone     = sanitize_text_field( $_POST['phone'] ?? '' );
        $company   = sanitize_text_field( $_POST['company'] ?? '' );
        $event_id  = absint( $_POST['event_id'] ?? 0 );
        $event_date = sanitize_text_field( $_POST['event_date'] ?? '' );
        $status    = sanitize_text_field( $_POST['status'] ?? 'pending' );
        $notes     = sanitize_textarea_field( $_POST['notes'] ?? '' );

        if ( $firstname && $lastname && $email ) {
            tc_update_registration( $registration_id, array(
                'firstname' => $firstname,
                'lastname'  => $lastname,
                'email'     => $email,
                'phone'     => $phone,
                'company'   => $company,
                'event_id'  => $event_id,
                'event_date' => $event_date,
                'status'    => $status,
                'notes'     => $notes,
            ) );

            echo '<div class="notice notice-success"><p>Anmeldung gespeichert.</p></div>';
            $reg = tc_get_registration( $registration_id );
        } else {
            echo '<div class="notice notice-error"><p>Bitte füllen Sie alle erforderlichen Felder aus.</p></div>';
        }
    }

    ?>

    <div class="wrap">
        <h1>Anmeldung bearbeiten
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=training-registrations' ) ); ?>" class="page-title-action">
                Zurück
            </a>
        </h1>

        <form method="POST" style="max-width: 600px;">
            <?php wp_nonce_field( 'tc_save_registration_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="firstname">Vorname <span style="color: red;">*</span></label></th>
                    <td><input type="text" id="firstname" name="firstname" class="regular-text" value="<?php echo esc_attr( $reg['firstname'] ); ?>" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="lastname">Nachname <span style="color: red;">*</span></label></th>
                    <td><input type="text" id="lastname" name="lastname" class="regular-text" value="<?php echo esc_attr( $reg['lastname'] ); ?>" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="email">E-Mail <span style="color: red;">*</span></label></th>
                    <td><input type="email" id="email" name="email" class="regular-text" value="<?php echo esc_attr( $reg['email'] ); ?>" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="phone">Telefon</label></th>
                    <td><input type="tel" id="phone" name="phone" class="regular-text" value="<?php echo esc_attr( $reg['phone'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="company">Unternehmen</label></th>
                    <td><input type="text" id="company" name="company" class="regular-text" value="<?php echo esc_attr( $reg['company'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="event_id">Veranstaltung <span style="color: red;">*</span></label></th>
                    <td>
                        <select id="event_id" name="event_id" required>
                            <option value="">-- Wählen --</option>
                            <?php
                            $events = get_posts( array(
                                'post_type'      => 'training_event',
                                'posts_per_page' => -1,
                                'orderby'        => 'title',
                                'order'          => 'ASC',
                            ) );

                            foreach ( $events as $event ) {
                                echo '<option value="' . esc_attr( $event->ID ) . '" ' . selected( $reg['event_id'], $event->ID, false ) . '>' . esc_html( $event->post_title ) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="event_date">Gewähltes Datum</label></th>
                    <td>
                        <input type="date" id="event_date" name="event_date" value="<?php echo esc_attr( $reg['event_date'] ); ?>" />
                        <p class="description">Relevant für mehrtägige Veranstaltungen</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="status">Status</label></th>
                    <td>
                        <select id="status" name="status">
                            <option value="pending" <?php selected( $reg['status'], 'pending' ); ?>>Ausstehend</option>
                            <option value="confirmed" <?php selected( $reg['status'], 'confirmed' ); ?>>Bestätigt</option>
                            <option value="cancelled" <?php selected( $reg['status'], 'cancelled' ); ?>>Storniert</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="notes">Notizen</label></th>
                    <td><textarea id="notes" name="notes" class="large-text" rows="5"><?php echo esc_textarea( $reg['notes'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><strong>Angemeldet am:</strong></th>
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
// AJAX: Status aktualisieren
// ─────────────────────────────────────────────
add_action( 'wp_ajax_tc_update_registration_status', function () {
    check_ajax_referer( 'tc_admin_nonce', 'nonce' );

    if ( ! current_user_can( 'administrator' ) ) {
        wp_send_json_error( 'Keine Berechtigung.' );
    }

    $registration_id = absint( $_POST['registration_id'] ?? 0 );
    $new_status      = sanitize_text_field( $_POST['status'] ?? '' );

    if ( ! $registration_id || ! in_array( $new_status, array( 'pending', 'confirmed', 'cancelled' ), true ) ) {
        wp_send_json_error( 'Ungültige Daten.' );
    }

    tc_update_registration( $registration_id, array( 'status' => $new_status ) );

    wp_send_json_success();
} );

// ─────────────────────────────────────────────
// CSS für Admin-Seite
// ─────────────────────────────────────────────
add_action( 'admin_head', function () {
    ?>
    <style>
        .tc-status-select {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
    </style>
    <?php
} );
