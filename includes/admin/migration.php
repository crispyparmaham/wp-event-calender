<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────
// Post-Type-Migration: training_event → time_event
// ─────────────────────────────────────────────────────────────────

/**
 * Prüft ob noch Posts mit dem alten post_type existieren.
 *
 * @return int Anzahl der Posts mit post_type = 'training_event'
 */
function tc_migration_count_old_posts() {
    global $wpdb;
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
            'training_event'
        )
    );
}

/**
 * Prüft ob die Migration bereits durchgeführt wurde.
 *
 * @return bool
 */
function tc_migration_is_done() {
    return (bool) get_option( 'tc_migration_done', false );
}

/**
 * Führt die eigentliche Migration durch.
 *
 * @return int Anzahl der migrierten Posts
 */
function tc_migrate_post_type() {
    if ( ! current_user_can( 'administrator' ) ) {
        return 0;
    }

    if ( tc_migration_is_done() ) {
        return 0;
    }

    global $wpdb;

    $count = tc_migration_count_old_posts();

    if ( $count === 0 ) {
        update_option( 'tc_migration_done', true );
        return 0;
    }

    $migrated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
            'time_event',
            'training_event'
        )
    );

    // Rewrite-Regeln neu generieren
    flush_rewrite_rules();

    // Migration als abgeschlossen markieren
    update_option( 'tc_migration_done', true );

    return (int) $migrated;
}

// ─────────────────────────────────────────────────────────────────
// Admin-Notice: Migration steht aus
// ─────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    if ( tc_migration_is_done() ) {
        return;
    }

    // Nur im Admin anzeigen
    if ( ! is_admin() ) {
        return;
    }

    add_action( 'admin_notices', function () {
        if ( ! current_user_can( 'administrator' ) ) {
            return;
        }

        // Nicht auf der Migrations-Seite selbst anzeigen
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'tc-migration' ) {
            return;
        }

        $count = tc_migration_count_old_posts();
        if ( $count === 0 ) {
            return;
        }

        $migration_url = admin_url( 'edit.php?post_type=time_event&page=tc-migration' );
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>Time Calendar – Migration erforderlich:</strong>
                <?php echo esc_html( $count ); ?> Events müssen von <code>training_event</code>
                zu <code>time_event</code> migriert werden.
                <a href="<?php echo esc_url( $migration_url ); ?>">Jetzt migrieren →</a>
            </p>
        </div>
        <?php
    } );
} );

// ─────────────────────────────────────────────────────────────────
// Admin-Menü: Migrations-Seite (nur sichtbar wenn nicht erledigt)
// ─────────────────────────────────────────────────────────────────
add_action( 'admin_menu', function () {
    if ( tc_migration_is_done() ) {
        return;
    }

    add_submenu_page(
        'edit.php?post_type=time_event',
        'Migration',
        'Migration',
        'administrator',
        'tc-migration',
        'tc_render_migration_page'
    );
} );

// ─────────────────────────────────────────────────────────────────
// Migrations-Seite rendern
// ─────────────────────────────────────────────────────────────────
function tc_render_migration_page() {
    if ( ! current_user_can( 'administrator' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    $count          = tc_migration_count_old_posts();
    $migration_done = false;
    $migrated_count = 0;

    // Migration ausführen wenn Button geklickt
    if ( isset( $_POST['tc_run_migration'] ) ) {
        check_admin_referer( 'tc_migration_nonce', '_tc_migration_nonce' );

        if ( ! current_user_can( 'administrator' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        $migrated_count = tc_migrate_post_type();
        $migration_done = true;
    }

    // Backup-Plugin erkennen
    $backup_plugins = array(
        'updraftplus/updraftplus.php'       => 'UpdraftPlus',
        'backwpup/backwpup.php'             => 'BackWPup',
        'duplicator/duplicator.php'         => 'Duplicator',
        'all-in-one-wp-migration/all-in-one-wp-migration.php' => 'All-in-One WP Migration',
        'blogvault-real-time-backup/developer_developer.php'   => 'BlogVault',
    );

    $active_backup = '';
    foreach ( $backup_plugins as $plugin_file => $plugin_name ) {
        if ( is_plugin_active( $plugin_file ) ) {
            $active_backup = $plugin_name;
            break;
        }
    }

    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span class="dashicons dashicons-database-export" style="font-size:1.5rem;width:auto;height:auto;color:#4f46e5;"></span>
            Time Calendar – Post-Type Migration
        </h1>

        <?php if ( $migration_done ) : ?>
            <!-- ── Erfolg ─────────────────────────────── -->
            <div class="notice notice-success" style="margin-top:20px;padding:16px;">
                <p style="font-size:15px;margin:0;">
                    <strong>Migration erfolgreich abgeschlossen!</strong><br>
                    <?php echo esc_html( $migrated_count ); ?> Post(s) wurden von
                    <code>training_event</code> zu <code>time_event</code> migriert.
                </p>
                <p style="margin:12px 0 0;">
                    <strong>Wichtig:</strong> Bitte gehen Sie jetzt zu
                    <a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>">Einstellungen → Permalinks</a>
                    und klicken Sie auf „Änderungen speichern", um die Permalinks neu zu generieren.
                </p>
            </div>

        <?php elseif ( $count === 0 ) : ?>
            <!-- ── Keine Migration nötig ──────────────── -->
            <div class="notice notice-info" style="margin-top:20px;padding:16px;">
                <p style="font-size:15px;margin:0;">
                    <strong>Keine Migration nötig.</strong><br>
                    Es gibt keine Posts mit dem alten Post-Type <code>training_event</code>.
                </p>
            </div>

        <?php else : ?>
            <!-- ── Migration steht aus ────────────────── -->
            <div style="max-width:700px;margin-top:20px;">

                <!-- Warnung -->
                <div class="notice notice-warning" style="padding:16px;margin:0 0 20px;">
                    <p style="font-size:15px;margin:0;">
                        <strong><?php echo esc_html( $count ); ?> Post(s)</strong> werden von
                        <code>training_event</code> zu <code>time_event</code> migriert.
                    </p>
                    <p style="margin:8px 0 0;color:#92400e;">
                        <strong>Bitte erstellen Sie vorher ein Datenbank-Backup!</strong>
                    </p>
                </div>

                <!-- Backup-Hinweis -->
                <?php if ( $active_backup ) : ?>
                    <div class="notice notice-info" style="padding:16px;margin:0 0 20px;">
                        <p style="margin:0;">
                            <span class="dashicons dashicons-shield" style="color:#059669;"></span>
                            <strong><?php echo esc_html( $active_backup ); ?></strong> ist aktiv.
                            Bitte erstellen Sie über dieses Plugin ein Backup, bevor Sie fortfahren.
                        </p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-error" style="padding:16px;margin:0 0 20px;">
                        <p style="margin:0;">
                            <span class="dashicons dashicons-warning" style="color:#dc2626;"></span>
                            <strong>Kein Backup-Plugin erkannt.</strong>
                            Bitte erstellen Sie manuell ein Datenbank-Backup (z.B. über phpMyAdmin),
                            bevor Sie die Migration starten.
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Was passiert? -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:20px;">
                    <h3 style="margin-top:0;">Was passiert bei der Migration?</h3>
                    <ul style="list-style:disc;margin-left:20px;line-height:1.8;">
                        <li>In der Tabelle <code>wp_posts</code> wird <code>post_type</code>
                            von <code>training_event</code> auf <code>time_event</code> geändert.</li>
                        <li>Die Rewrite-Regeln (Permalinks) werden neu generiert.</li>
                        <li>Die Migration wird als abgeschlossen markiert und läuft nur einmal.</li>
                        <li>Alle bestehenden Daten (ACF-Felder, Anmeldungen, etc.) bleiben erhalten.</li>
                    </ul>
                </div>

                <!-- Dry-Run / Vorschau -->
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px;margin-bottom:20px;">
                    <h3 style="margin-top:0;color:#059669;">
                        <span class="dashicons dashicons-visibility"></span> Vorschau (Dry Run)
                    </h3>
                    <p style="margin:0;">
                        <strong><?php echo esc_html( $count ); ?></strong> Post(s) mit
                        <code>post_type = 'training_event'</code> gefunden.<br>
                        Diese werden zu <code>time_event</code> migriert.
                    </p>
                </div>

                <!-- Migrations-Button -->
                <form method="POST">
                    <?php wp_nonce_field( 'tc_migration_nonce', '_tc_migration_nonce' ); ?>
                    <button type="submit"
                            name="tc_run_migration"
                            value="1"
                            class="button button-hero button-primary"
                            style="font-size:16px;padding:10px 40px;height:auto;"
                            onclick="return confirm('Haben Sie ein Datenbank-Backup erstellt? Die Migration kann nicht automatisch rückgängig gemacht werden.');">
                        Jetzt migrieren
                    </button>
                </form>

            </div>
        <?php endif; ?>
    </div>
    <?php
}
