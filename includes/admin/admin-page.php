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

        <div id="tc-save-bar" class="tc-save-bar" style="display:none;" aria-live="polite">
            <span id="tc-save-count" class="tc-save-bar-count"></span>
            <div class="tc-save-bar-actions">
                <button id="tc-save-bar-reset" class="button">Zurücksetzen</button>
                <button id="tc-save-bar-save"  class="button button-primary">Speichern</button>
            </div>
        </div>

        <div id="tc-calendar"></div>

        <!-- ── Modal: Neues Event anlegen ─────────────── -->
        <div id="tc-modal" class="tc-modal" style="display:none;">
            <div class="tc-modal-inner">
                <h2>Neues Event anlegen</h2>

                <!-- Titel -->
                <label>Titel <span class="required">*</span>
                    <input type="text" id="tc-modal-title" placeholder="z.B. Kettlebell Kurs" />
                </label>

                <!-- Kategorie mit AJAX-Dropdown + Neue Kategorie -->
                <label>Kategorie
                    <div class="tc-modal-cat-row">
                        <select id="tc-modal-type">
                            <option value="">Wird geladen…</option>
                        </select>
                        <button type="button" id="tc-modal-new-cat-btn" class="button button-small">+ Neue Kategorie</button>
                    </div>
                </label>

                <!-- Inline: Neue Kategorie anlegen -->
                <div id="tc-modal-new-cat" class="tc-modal-new-cat" style="display:none;">
                    <label>Name <span class="required">*</span>
                        <input type="text" id="tc-modal-cat-name" placeholder="z.B. Workshop" />
                    </label>
                    <label>Farbe
                        <div class="tc-modal-color-row">
                            <input type="color" id="tc-modal-cat-color" value="#4f46e5" />
                            <span id="tc-modal-cat-color-val">#4f46e5</span>
                        </div>
                    </label>
                    <div class="tc-modal-new-cat-actions">
                        <button type="button" id="tc-modal-cat-save" class="button button-primary button-small">Anlegen</button>
                        <button type="button" id="tc-modal-cat-cancel" class="button button-small">Abbrechen</button>
                    </div>
                    <p id="tc-modal-cat-error" class="tc-error" style="display:none;"></p>
                </div>

                <hr class="tc-divider" />

                <!-- Termintyp Radio -->
                <div class="tc-modal-date-type">
                    <label class="tc-radio-label">
                        <input type="radio" name="tc-modal-date-type" value="single" checked />
                        Einzeltermin
                    </label>
                    <label class="tc-radio-label">
                        <input type="radio" name="tc-modal-date-type" value="recurring" />
                        Wiederkehrend
                    </label>
                </div>

                <!-- ── Einzeltermin-Felder ── -->
                <div id="tc-fields-single">
                    <label>Datum <span class="required">*</span>
                        <input type="date" id="tc-modal-date" />
                    </label>
                    <div class="tc-modal-time-row">
                        <label>Von
                            <input type="time" id="tc-modal-time-start" />
                        </label>
                        <label>Bis
                            <input type="time" id="tc-modal-time-end" />
                        </label>
                    </div>

                    <label class="tc-toggle-row">
                        <span>Mehrtägig</span>
                        <input type="checkbox" id="tc-modal-multiday" />
                    </label>
                    <div id="tc-modal-enddate-wrap" style="display:none;">
                        <label>Bis Datum
                            <input type="date" id="tc-modal-end-date" />
                        </label>
                    </div>

                    <!-- + Weitere Termine (aufklappbar) -->
                    <div class="tc-modal-extra-dates">
                        <button type="button" id="tc-modal-add-date-toggle" class="tc-link-btn">+ Weitere Termine hinzufügen</button>
                        <div id="tc-modal-extra-dates-list" style="display:none;"></div>
                        <button type="button" id="tc-modal-add-date-btn" class="button button-small" style="display:none;">+ Weiterer Termin</button>
                    </div>
                </div>

                <!-- ── Wiederkehrend-Felder ── -->
                <div id="tc-fields-recurring" style="display:none;">
                    <label>Erster Termin <span class="required">*</span>
                        <input type="date" id="tc-modal-rec-date" />
                    </label>
                    <div class="tc-modal-time-row">
                        <label>Von
                            <input type="time" id="tc-modal-rec-time-start" />
                        </label>
                        <label>Bis
                            <input type="time" id="tc-modal-rec-time-end" />
                        </label>
                    </div>
                    <label>Wochentag <span class="required">*</span>
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
                        💡 Das Event wird jeden ausgewählten Wochentag bis zum angegebenen Datum im Kalender angezeigt.
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
