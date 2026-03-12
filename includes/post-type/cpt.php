<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// 1. Custom Post Type registrieren
// ─────────────────────────────────────────────
add_action( 'init', function () {
    register_post_type( 'training_event', array(
        'labels' => array(
            'name'          => 'Kalender',
            'singular_name' => 'Kalender',
            'add_new'       => 'Event hinzufügen',
            'add_new_item'  => 'Neuen Event hinzufügen',
            'edit_item'     => 'Event bearbeiten',
            'all_items'     => 'Alle Events',
            'search_items'  => 'Events suchen',
            'not_found'     => 'Keine Events gefunden.',
        ),
        'public'       => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-calendar-alt',
        'supports'     => array( 'title', 'editor', 'thumbnail' ),
        'has_archive'  => true,
        'rewrite'      => array( 'slug' => 'events' ),
        'show_in_rest' => true,
    ) );
} );

// ─────────────────────────────────────────────
// 2. ACF Feldgruppe
// ─────────────────────────────────────────────
add_action( 'acf/include_fields', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( array(
        'key'    => 'group_training_event',
        'title'  => 'Event Details',
        'fields' => array(

            // ── TAB: Allgemein ──────────────────────
            array(
                'key'       => 'field_tc_tab_general',
                'label'     => 'Allgemein',
                'type'      => 'tab',
                'placement' => 'top',
                'endpoint'  => 0,
            ),
            array(
                'key'           => 'field_tc_event_type',
                'label'         => 'Event-Typ',
                'name'          => 'event_type',
                'type'          => 'select',
                'choices'       => array(),   // wird per acf/load_field dynamisch befüllt
                'default_value' => 'training',
                'return_format' => 'value',
                'required'      => 1,
            ),
            array(
                'key'       => 'field_tc_intro_text',
                'label'     => 'Einleitungstext',
                'name'      => 'intro_text',
                'type'      => 'textarea',
                'rows'      => 4,
                'new_lines' => 'wpautop',
            ),
            array(
                'key'           => 'field_tc_partnerlogo',
                'label'         => 'Partnerlogo',
                'name'          => 'partnerlogo',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
            ),

            // ── TAB: Details ────────────────────────
            array(
                'key'       => 'field_tc_tab_details',
                'label'     => 'Details',
                'type'      => 'tab',
                'placement' => 'top',
                'endpoint'  => 0,
            ),
            array(
                'key'         => 'field_tc_leadership',
                'label'       => 'Seminar-/Trainingsleitung',
                'name'        => 'seminar_leadership',
                'type'        => 'text',
                'placeholder' => 'z.B. Max Mustermann',
            ),
            array(
                'key'         => 'field_tc_participants',
                'label'       => 'Max. Teilnehmer (optional)',
                'name'        => 'participants',
                'type'        => 'number',
                'placeholder' => 'z.B. 12',
                'instructions' => 'Lassen Sie leer, wenn keine Kapazitätsbegrenzung verwendet wird.',
            ),
            array(
                'key'         => 'field_tc_track_participants',
                'label'       => 'Teilnehmer tracken?',
                'name'        => 'track_participants',
                'type'        => 'true_false',
                'default_value' => 0,
                'instructions' => 'Aktivieren Sie dies, um die Anmeldungen zu zählen und den Termin zu sperren, wenn die maximale Teilnehmerzahl erreicht ist.',
            ),
            array(
                'key'          => 'field_tc_difficulty',
                'label'        => 'Für wen geeignet',
                'name'         => 'difficulty',
                'type'         => 'text',
                'instructions' => 'z.B. Anfänger, Fortgeschrittene, Alle',
                'placeholder'  => 'z.B. Alle Levels',
            ),
            array(
                'key'   => 'field_tc_location',
                'label' => 'Ort',
                'name'  => 'location',
                'type'  => 'wysiwyg',
                'tabs'  => 'all',
            ),

            // ── TAB: Datum & Uhrzeit ────────────────
            array(
                'key'       => 'field_tc_tab_datetime',
                'label'     => 'Datum & Uhrzeit',
                'type'      => 'tab',
                'placement' => 'top',
                'endpoint'  => 0,
            ),
            array(
                'key'           => 'field_tc_more_days',
                'label'         => 'Mehrtägige Veranstaltung?',
                'name'          => 'more_days',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
            ),
            array(
                'key'            => 'field_tc_start_date',
                'label'          => 'Startdatum',
                'name'           => 'start_date',
                'type'           => 'date_picker',
                'display_format' => 'd.m.Y',
                'return_format'  => 'Y-m-d',
                'first_day'      => 1,
                'required'       => 1,
            ),
            array(
                'key'            => 'field_tc_start_time',
                'label'          => 'Startzeit',
                'name'           => 'start_time',
                'type'           => 'time_picker',
                'display_format' => 'H:i',
                'return_format'  => 'H:i',
            ),
            array(
                'key'            => 'field_tc_end_time',
                'label'          => 'Endzeit',
                'name'           => 'end_time',
                'type'           => 'time_picker',
                'display_format' => 'H:i',
                'return_format'  => 'H:i',
                'instructions'   => 'Endzeit am selben Tag. Für mehrtägige Events unten das Enddatum setzen.',
            ),
            array(
                'key'            => 'field_tc_end_date',
                'label'          => 'Enddatum',
                'name'           => 'end_date',
                'type'           => 'date_picker',
                'display_format' => 'd.m.Y',
                'return_format'  => 'Y-m-d',
                'first_day'      => 1,
                'instructions'   => 'Nur bei mehrtägigen Events ausfüllen.',
                'conditional_logic' => array( array( array(
                    'field'    => 'field_tc_more_days',
                    'operator' => '==',
                    'value'    => '1',
                ) ) ),
            ),

            // ── Wiederholung ────────────────────────
            array(
                'key'           => 'field_tc_is_recurring',
                'label'         => 'Wiederkehrendes Event?',
                'name'          => 'is_recurring',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'instructions'  => 'Aktivieren, wenn dieses Event regelmäßig stattfindet.',
            ),
            array(
                'key'           => 'field_tc_recurring_weekday',
                'label'         => 'Wochentag der Wiederholung',
                'name'          => 'recurring_weekday',
                'type'          => 'select',
                'instructions'  => 'An welchem Wochentag findet das Event wöchentlich statt?',
                'choices'       => array(
                    '1' => 'Montag',
                    '2' => 'Dienstag',
                    '3' => 'Mittwoch',
                    '4' => 'Donnerstag',
                    '5' => 'Freitag',
                    '6' => 'Samstag',
                    '0' => 'Sonntag',
                ),
                'return_format' => 'value',
                'conditional_logic' => array( array( array(
                    'field'    => 'field_tc_is_recurring',
                    'operator' => '==',
                    'value'    => '1',
                ) ) ),
            ),
            array(
                'key'            => 'field_tc_recurring_until',
                'label'          => 'Wiederholen bis',
                'name'           => 'recurring_until',
                'type'           => 'date_picker',
                'instructions'   => 'Letzter möglicher Termin der Serie.',
                'display_format' => 'd.m.Y',
                'return_format'  => 'Y-m-d',
                'first_day'      => 1,
                'required'       => 0,
                'conditional_logic' => array( array( array(
                    'field'    => 'field_tc_is_recurring',
                    'operator' => '==',
                    'value'    => '1',
                ) ) ),
            ),

            // ── TAB: Termine ────────────────────────
            array(
                'key'       => 'field_tc_tab_dates',
                'label'     => 'Termine',
                'type'      => 'tab',
                'placement' => 'top',
                'endpoint'  => 0,
            ),
            array(
                'key'          => 'field_tc_event_dates',
                'label'        => 'Mehrere Termine',
                'name'         => 'event_dates',
                'type'         => 'repeater',
                'layout'       => 'block',
                'button_label' => 'Termin hinzufügen',
                'instructions' => 'Fügen Sie hier einzelne Termine hinzu. Wenn Termine eingetragen sind, werden die Felder im Tab "Datum & Uhrzeit" ignoriert.',
                'sub_fields'   => array(
                    array(
                        'key'            => 'field_tc_ed_start',
                        'label'          => 'Startdatum',
                        'name'           => 'date_start',
                        'type'           => 'date_picker',
                        'display_format' => 'd.m.Y',
                        'return_format'  => 'Y-m-d',
                        'first_day'      => 1,
                        'required'       => 1,
                        'wrapper'        => array( 'width' => '25' ),
                    ),
                    array(
                        'key'            => 'field_tc_ed_end',
                        'label'          => 'Enddatum (optional, nur mehrtägig)',
                        'name'           => 'date_end',
                        'type'           => 'date_picker',
                        'display_format' => 'd.m.Y',
                        'return_format'  => 'Y-m-d',
                        'first_day'      => 1,
                        'wrapper'        => array( 'width' => '25' ),
                    ),
                    array(
                        'key'            => 'field_tc_ed_time_start',
                        'label'          => 'Startzeit',
                        'name'           => 'time_start',
                        'type'           => 'time_picker',
                        'display_format' => 'H:i',
                        'return_format'  => 'H:i',
                        'wrapper'        => array( 'width' => '15' ),
                    ),
                    array(
                        'key'            => 'field_tc_ed_time_end',
                        'label'          => 'Endzeit',
                        'name'           => 'time_end',
                        'type'           => 'time_picker',
                        'display_format' => 'H:i',
                        'return_format'  => 'H:i',
                        'wrapper'        => array( 'width' => '15' ),
                    ),
                    array(
                        'key'          => 'field_tc_ed_seats',
                        'label'        => 'Max. Plätze (optional)',
                        'name'         => 'seats',
                        'type'         => 'number',
                        'min'          => 0,
                        'placeholder'  => 'unbegrenzt',
                        'instructions' => 'Überschreibt die globale Teilnehmerzahl für diesen Termin.',
                        'wrapper'      => array( 'width' => '20' ),
                    ),
                ),
            ),

            // ── TAB: Preis ──────────────────────────
            array(
                'key'       => 'field_tc_tab_price',
                'label'     => 'Preis',
                'type'      => 'tab',
                'placement' => 'top',
                'endpoint'  => 0,
            ),

            // NEU: Preis auf Anfrage Toggle
            array(
                'key'           => 'field_tc_price_on_request',
                'label'         => 'Probetraining Button anzeigen (kein fixer Preis)',
                'name'          => 'price_on_request',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'instructions'  => 'Aktivieren wenn kein fixer Preis angegeben werden soll. Preisfelder werden dann ausgeblendet.',
            ),

            // Regulärer Preis — nur sichtbar wenn NICHT auf Anfrage
            array(
                'key'      => 'field_tc_normal_price',
                'label'    => 'Regulärer Preis (€)',
                'name'     => 'normal_preis',
                'type'     => 'number',
                'required' => 1,
                'min'      => 0,
                'step'     => 0.01,
                'prepend'  => '€',
                'conditional_logic' => array( array( array(
                    'field'    => 'field_tc_price_on_request',
                    'operator' => '==',
                    'value'    => '0',
                ) ) ),
            ),

            // Early Bird — nur sichtbar wenn NICHT auf Anfrage
            array(
                'key'        => 'field_tc_early_bird',
                'label'      => 'Early Bird',
                'name'       => 'early_bird',
                'type'       => 'group',
                'layout'     => 'row',
                'conditional_logic' => array( array( array(
                    'field'    => 'field_tc_price_on_request',
                    'operator' => '==',
                    'value'    => '0',
                ) ) ),
                'sub_fields' => array(
                    array(
                        'key'     => 'field_tc_eb_price',
                        'label'   => 'Early-Bird-Preis (€)',
                        'name'    => 'early_bird_preis',
                        'type'    => 'number',
                        'min'     => 0,
                        'step'    => 0.01,
                        'prepend' => '€',
                    ),
                    array(
                        'key'            => 'field_tc_eb_deadline',
                        'label'          => 'Anmeldung bis',
                        'name'           => 'anmeldung',
                        'type'           => 'date_picker',
                        'instructions'   => 'Muss vor dem Startdatum liegen.',
                        'display_format' => 'd.m.Y',
                        'return_format'  => 'Y-m-d',
                        'first_day'      => 1,
                    ),
                ),
            ),

        ),
        'location' => array( array( array(
            'param'    => 'post_type',
            'operator' => '==',
            'value'    => 'training_event',
        ) ) ),
        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
    ) );
} );

// ─────────────────────────────────────────────
// 3. ACF: event_type Select dynamisch befüllen
// ─────────────────────────────────────────────
add_filter( 'acf/load_field/key=field_tc_event_type', function ( $field ) {
    $categories = tc_get_all_categories();
    $choices = array();
    foreach ( $categories as $cat ) {
        $choices[ $cat['slug'] ] = $cat['name'];
    }
    $field['choices'] = $choices;
    return $field;
} );

// ─────────────────────────────────────────────
// 4. Single-Post-Template
// ─────────────────────────────────────────────
add_filter( 'single_template', function ( $template ) {
    if ( is_singular( 'training_event' ) ) {
        $plugin_tpl = TC_PATH . 'templates/single-training_event.php';
        if ( file_exists( $plugin_tpl ) ) {
            return $plugin_tpl;
        }
    }
    return $template;
} );
