<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Admin-Menüseite
// ─────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=time_event',
        'Kalenderübersicht',
        'Kalender',
        'administrator',
        'time-calendar',
        'tc_render_calendar_page'
    );
} );

function tc_render_calendar_page() { ?>
    <div class="wrap tc-wrap">
        <h1>Kalenderübersicht</h1>

        <div class="tc-legend">
            <span class="tc-legend-item tc-training">Training</span>
            <span class="tc-legend-item tc-seminar">Seminar</span>
            <span class="tc-legend-item tc-recurring">Wiederkehrend 🔁</span>
        </div>

        <div id="tc-calendar"></div>

        <!-- ── Modal: Neues Event anlegen ─────────────── -->
        <div id="tc-modal" class="tc-modal" style="display:none;">
            <div class="tc-modal-inner">
                <h2>Neues Event anlegen</h2>

                <label>Titel <span class="required">*</span>
                    <input type="text" id="tc-modal-title" placeholder="z.B. Kettlebell Kurs" />
                </label>

                <label>Event-Typ
                    <select id="tc-modal-type">
                        <option value="training">Gruppentraining</option>
                        <option value="seminar">Seminar</option>
                    </select>
                </label>

                <label>Startdatum & Uhrzeit <span class="required">*</span>
                    <input type="datetime-local" id="tc-modal-start" />
                </label>

                <label>Enddatum & Uhrzeit <small>(nur bei mehrtägigen Events)</small>
                    <input type="datetime-local" id="tc-modal-end" />
                </label>

                <hr class="tc-divider" />

                <label class="tc-toggle-row">
                    <span>Wiederkehrendes Event?</span>
                    <input type="checkbox" id="tc-modal-recurring" />
                </label>

                <div id="tc-recurring-fields" style="display:none;">
                    <label>Wochentag
                        <select id="tc-modal-weekday">
                            <option value="1">Montag</option>
                            <option value="2">Dienstag</option>
                            <option value="3">Mittwoch</option>
                            <option value="4">Donnerstag</option>
                            <option value="5">Freitag</option>
                            <option value="6">Samstag</option>
                            <option value="0">Sonntag</option>
                        </select>
                    </label>
                    <label>Wiederholen bis <span class="required">*</span>
                        <input type="date" id="tc-modal-until" />
                    </label>
                    <p class="tc-modal-hint">
                        💡 Das Event wird jeden ausgewählten Wochentag bis zum angegebenen Datum im Kalender angezeigt. Alle Details werden im Post verwaltet.
                    </p>
                </div>

                <div class="tc-modal-actions">
                    <button id="tc-modal-save" class="button button-primary">Event anlegen &amp; im Editor öffnen</button>
                    <button id="tc-modal-cancel" class="button">Abbrechen</button>
                </div>

                <p id="tc-modal-error" class="tc-error" style="display:none;"></p>
            </div>
        </div>
        <div id="tc-modal-backdrop" style="display:none;"></div>
    </div>
<?php }

// ─────────────────────────────────────────────
// Assets nur auf Kalenderseite laden
// ─────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'time_event_page_time-calendar' ) return;

    wp_enqueue_script(
        'fullcalendar',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
        array(),
        '6.1.15',
        true
    );

    wp_enqueue_script(
        'tc-calendar',
        TC_URL . 'assets/js/admin/calendar.js',
        array( 'fullcalendar' ),
        TC_VERSION,
        true
    );

    wp_localize_script( 'tc-calendar', 'TC', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'tc_nonce' ),
    ) );

    wp_enqueue_style(
        'tc-calendar',
        TC_URL . 'assets/css/admin/calendar.css',
        array(),
        TC_VERSION
    );
} );
