<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// DB-Tabelle anlegen / aktualisieren
// ─────────────────────────────────────────────
function tc_create_categories_table() {
    global $wpdb;
    $table           = $wpdb->prefix . 'tc_event_categories';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id        int(11)      NOT NULL AUTO_INCREMENT,
        name      varchar(255) NOT NULL,
        slug      varchar(255) NOT NULL,
        color     varchar(20)  NOT NULL DEFAULT '#4f46e5',
        sort_order int(11)     NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

add_action( 'admin_init', function () {
    tc_create_categories_table();
    tc_install_default_categories();
} );

// ─────────────────────────────────────────────
// Standard-Kategorien eintragen (nur wenn Tabelle leer)
// ─────────────────────────────────────────────
function tc_install_default_categories() {
    global $wpdb;
    $table = $wpdb->prefix . 'tc_event_categories';
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    if ( $count > 0 ) return;

    $wpdb->insert( $table, array( 'name' => 'Gruppentraining', 'slug' => 'training', 'color' => '#4f46e5', 'sort_order' => 0 ) );
    $wpdb->insert( $table, array( 'name' => 'Seminar',         'slug' => 'seminar',  'color' => '#059669', 'sort_order' => 1 ) );
}

// ─────────────────────────────────────────────
// Helper-Funktionen
// ─────────────────────────────────────────────
function tc_get_all_categories() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}tc_event_categories ORDER BY sort_order ASC, id ASC",
        ARRAY_A
    );
    return $rows ?: array();
}

function tc_get_category( $slug ) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tc_event_categories WHERE slug = %s",
            $slug
        ),
        ARRAY_A
    );
}

function tc_get_category_color( $slug ) {
    $cat = tc_get_category( $slug );
    return $cat ? $cat['color'] : '#4f46e5';
}

// ─────────────────────────────────────────────
// Admin-Menü registrieren
// ─────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=time_event',
        'Kategorien',
        'Kategorien',
        'administrator',
        'tc-event-categories',
        'tc_render_categories_page'
    );
} );

// ─────────────────────────────────────────────
// CRUD-Aktionen verarbeiten (vor dem Rendern)
// ─────────────────────────────────────────────
add_action( 'admin_init', function () {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'tc-event-categories' ) return;
    if ( ! current_user_can( 'administrator' ) ) return;

    $action = $_POST['tc_cat_action'] ?? '';

    // ── Neue Kategorie anlegen ──
    if ( $action === 'add' ) {
        check_admin_referer( 'tc_cat_add' );
        $name  = sanitize_text_field( $_POST['cat_name']  ?? '' );
        $slug  = sanitize_title(      $_POST['cat_slug']  ?? $name );
        $color = sanitize_hex_color(  $_POST['cat_color'] ?? '#4f46e5' ) ?: '#4f46e5';
        if ( $name && $slug ) {
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'tc_event_categories',
                array( 'name' => $name, 'slug' => $slug, 'color' => $color, 'sort_order' => 99 )
            );
        }
        wp_redirect( add_query_arg( array( 'page' => 'tc-event-categories', 'saved' => '1' ), admin_url( 'edit.php?post_type=time_event' ) ) );
        exit;
    }

    // ── Kategorie updaten ──
    if ( $action === 'update' ) {
        check_admin_referer( 'tc_cat_update' );
        $id    = absint(              $_POST['cat_id']    ?? 0 );
        $name  = sanitize_text_field( $_POST['cat_name']  ?? '' );
        $slug  = sanitize_title(      $_POST['cat_slug']  ?? '' );
        $color = sanitize_hex_color(  $_POST['cat_color'] ?? '#4f46e5' ) ?: '#4f46e5';
        $order = absint(              $_POST['cat_order'] ?? 0 );
        if ( $id && $name && $slug ) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'tc_event_categories',
                array( 'name' => $name, 'slug' => $slug, 'color' => $color, 'sort_order' => $order ),
                array( 'id'   => $id )
            );
        }
        wp_redirect( add_query_arg( array( 'page' => 'tc-event-categories', 'saved' => '1' ), admin_url( 'edit.php?post_type=time_event' ) ) );
        exit;
    }

    // ── Kategorie löschen ──
    if ( isset( $_GET['tc_cat_delete'] ) ) {
        check_admin_referer( 'tc_cat_delete_' . absint( $_GET['tc_cat_delete'] ) );
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'tc_event_categories',
            array( 'id' => absint( $_GET['tc_cat_delete'] ) )
        );
        wp_redirect( add_query_arg( array( 'page' => 'tc-event-categories', 'deleted' => '1' ), admin_url( 'edit.php?post_type=time_event' ) ) );
        exit;
    }
} );

// ─────────────────────────────────────────────
// Seite rendern
// ─────────────────────────────────────────────
function tc_render_categories_page() {
    if ( ! current_user_can( 'administrator' ) ) wp_die( 'Keine Berechtigung.' );

    $categories = tc_get_all_categories();
    $edit_id    = absint( $_GET['edit'] ?? 0 );
    $edit_cat   = $edit_id ? tc_get_category_by_id( $edit_id ) : null;

    $saved   = isset( $_GET['saved'] );
    $deleted = isset( $_GET['deleted'] );
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span class="dashicons dashicons-tag" style="font-size:1.5rem;width:auto;height:auto;color:#4f46e5;"></span>
            Event-Kategorien
        </h1>

        <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible"><p>Kategorie wurde gespeichert.</p></div>
        <?php endif; ?>
        <?php if ( $deleted ) : ?>
            <div class="notice notice-success is-dismissible"><p>Kategorie wurde gelöscht.</p></div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 340px;gap:32px;margin-top:20px;align-items:start;">

            <!-- ── Tabelle ── -->
            <div>
                <?php if ( empty( $categories ) ) : ?>
                    <p>Noch keine Kategorien vorhanden.</p>
                <?php else : ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th style="width:40px;">Reihenfolge</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Farbe</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $categories as $cat ) : ?>
                        <tr>
                            <td style="text-align:center;color:#6b7280;"><?php echo (int) $cat['sort_order']; ?></td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span style="width:12px;height:12px;border-radius:50%;background:<?php echo esc_attr( $cat['color'] ); ?>;flex-shrink:0;"></span>
                                    <strong><?php echo esc_html( $cat['name'] ); ?></strong>
                                </span>
                            </td>
                            <td><code><?php echo esc_html( $cat['slug'] ); ?></code></td>
                            <td>
                                <span style="font-family:monospace;font-size:12px;background:#f3f4f6;padding:2px 8px;border-radius:4px;">
                                    <?php echo esc_html( $cat['color'] ); ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tc-event-categories', 'edit' => $cat['id'] ), admin_url( 'edit.php?post_type=time_event' ) ) ); ?>"
                                   class="button button-small">Bearbeiten</a>
                                <?php
                                $del_url = wp_nonce_url(
                                    add_query_arg( array( 'page' => 'tc-event-categories', 'tc_cat_delete' => $cat['id'] ), admin_url( 'edit.php?post_type=time_event' ) ),
                                    'tc_cat_delete_' . $cat['id']
                                );
                                ?>
                                <a href="<?php echo esc_url( $del_url ); ?>"
                                   class="button button-small"
                                   style="color:#dc2626;"
                                   onclick="return confirm('Kategorie wirklich löschen?');">Löschen</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- ── Formular ── -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
                <h2 style="margin-top:0;font-size:1rem;">
                    <?php echo $edit_cat ? 'Kategorie bearbeiten' : 'Neue Kategorie'; ?>
                </h2>

                <?php if ( $edit_cat ) : ?>
                <form method="post">
                    <?php wp_nonce_field( 'tc_cat_update' ); ?>
                    <input type="hidden" name="tc_cat_action" value="update">
                    <input type="hidden" name="cat_id"    value="<?php echo (int) $edit_cat['id']; ?>">

                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th scope="row"><label for="cat_name_e">Name</label></th>
                            <td><input type="text" id="cat_name_e" name="cat_name" class="regular-text"
                                       value="<?php echo esc_attr( $edit_cat['name'] ); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cat_slug_e">Slug</label></th>
                            <td>
                                <input type="text" id="cat_slug_e" name="cat_slug" class="regular-text"
                                       value="<?php echo esc_attr( $edit_cat['slug'] ); ?>" required
                                       pattern="[a-z0-9\-_]+" title="Nur Kleinbuchstaben, Zahlen, Bindestrich">
                                <p class="description">Wird intern verwendet (z.B. <code>training</code>). Nicht ändern wenn bereits Events mit diesem Slug existieren.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cat_color_e">Farbe</label></th>
                            <td>
                                <input type="color" id="cat_color_e" name="cat_color"
                                       value="<?php echo esc_attr( $edit_cat['color'] ); ?>"
                                       style="height:36px;width:60px;padding:2px;cursor:pointer;">
                                <span id="cat_color_e_val" style="margin-left:8px;font-family:monospace;font-size:12px;"><?php echo esc_html( $edit_cat['color'] ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cat_order_e">Reihenfolge</label></th>
                            <td><input type="number" id="cat_order_e" name="cat_order" class="small-text"
                                       value="<?php echo (int) $edit_cat['sort_order']; ?>" min="0"></td>
                        </tr>
                    </table>

                    <div style="margin-top:16px;display:flex;gap:8px;">
                        <?php submit_button( 'Speichern', 'primary', 'submit', false ); ?>
                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=time_event&page=tc-event-categories' ) ); ?>"
                           class="button">Abbrechen</a>
                    </div>
                </form>

                <script>
                document.getElementById('cat_color_e').addEventListener('input', function(){
                    document.getElementById('cat_color_e_val').textContent = this.value;
                });
                </script>

                <?php else : ?>

                <form method="post">
                    <?php wp_nonce_field( 'tc_cat_add' ); ?>
                    <input type="hidden" name="tc_cat_action" value="add">

                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th scope="row"><label for="cat_name_n">Name</label></th>
                            <td><input type="text" id="cat_name_n" name="cat_name" class="regular-text"
                                       placeholder="z.B. Workshop" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cat_slug_n">Slug</label></th>
                            <td>
                                <input type="text" id="cat_slug_n" name="cat_slug" class="regular-text"
                                       placeholder="z.B. workshop"
                                       pattern="[a-z0-9\-_]+" title="Nur Kleinbuchstaben, Zahlen, Bindestrich">
                                <p class="description">Leer lassen = automatisch aus Name generiert.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cat_color_n">Farbe</label></th>
                            <td>
                                <input type="color" id="cat_color_n" name="cat_color"
                                       value="#4f46e5"
                                       style="height:36px;width:60px;padding:2px;cursor:pointer;">
                                <span id="cat_color_n_val" style="margin-left:8px;font-family:monospace;font-size:12px;">#4f46e5</span>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top:16px;">
                        <?php submit_button( 'Kategorie anlegen', 'primary', 'submit', false ); ?>
                    </div>
                </form>

                <script>
                document.getElementById('cat_color_n').addEventListener('input', function(){
                    document.getElementById('cat_color_n_val').textContent = this.value;
                });
                // Slug auto-generieren aus Name
                document.getElementById('cat_name_n').addEventListener('input', function(){
                    var slug = document.getElementById('cat_slug_n');
                    if ( slug.value === '' ) {
                        slug.value = this.value.toLowerCase()
                            .replace(/[äöü]/g, function(m){ return {ä:'ae',ö:'oe',ü:'ue'}[m]; })
                            .replace(/[^a-z0-9]+/g, '-')
                            .replace(/^-+|-+$/g, '');
                    }
                });
                </script>

                <?php endif; ?>
            </div><!-- /form -->

        </div><!-- /grid -->
    </div>
    <?php
}

// Helper: Kategorie per ID laden
function tc_get_category_by_id( $id ) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_event_categories WHERE id = %d", $id ),
        ARRAY_A
    );
}
