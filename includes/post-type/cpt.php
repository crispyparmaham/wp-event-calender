<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// 1. Custom Post Type registrieren
// ─────────────────────────────────────────────
add_action( 'init', function () {
    register_post_type( 'time_event', array(
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
        'key'    => 'group_time_event',
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

            // ── TAB: Termine (konsolidiert) ────────────────
            array(
                'key'       => 'field_tc_tab_dates',
                'label'     => 'Termine',
                'type'      => 'tab',
                'placement' => 'top',
                'endpoint'  => 0,
            ),

            // Termintyp-Auswahl (2 Modi)
            array(
                'key'           => 'field_tc_event_date_type',
                'label'         => 'Termintyp',
                'name'          => 'event_date_type',
                'type'          => 'radio',
                'choices'       => array(
                    'single'    => 'Einzeltermin',
                    'recurring' => 'Wiederkehrend',
                ),
                'default_value' => 'single',
                'layout'        => 'horizontal',
                'return_format' => 'value',
                'required'      => 1,
            ),

            // ── Einzeltermin + Wiederkehrend: gemeinsame Felder ──
            array(
                'key'            => 'field_tc_start_date',
                'label'          => 'Startdatum',
                'name'           => 'start_date',
                'type'           => 'date_picker',
                'display_format' => 'd.m.Y',
                'return_format'  => 'Y-m-d',
                'first_day'      => 1,
                'required'       => 1,
                'conditional_logic' => array(
                    array( array( 'field' => 'field_tc_event_date_type', 'operator' => '==', 'value' => 'single' ) ),
                    array( array( 'field' => 'field_tc_event_date_type', 'operator' => '==', 'value' => 'recurring' ) ),
                ),
            ),
            array(
                'key'            => 'field_tc_start_time',
                'label'          => 'Uhrzeit von',
                'name'           => 'start_time',
                'type'           => 'time_picker',
                'display_format' => 'H:i',
                'return_format'  => 'H:i',
                'conditional_logic' => array(
                    array( array( 'field' => 'field_tc_event_date_type', 'operator' => '==', 'value' => 'single' ) ),
                    array( array( 'field' => 'field_tc_event_date_type', 'operator' => '==', 'value' => 'recurring' ) ),
                ),
            ),
            array(
                'key'            => 'field_tc_end_time',
                'label'          => 'Uhrzeit bis',
                'name'           => 'end_time',
                'type'           => 'time_picker',
                'display_format' => 'H:i',
                'return_format'  => 'H:i',
                'conditional_logic' => array(
                    array( array( 'field' => 'field_tc_event_date_type', 'operator' => '==', 'value' => 'single' ) ),
                    array( array( 'field' => 'field_tc_event_date_type', 'operator' => '==', 'value' => 'recurring' ) ),
                ),
            ),

            // ── Nur Einzeltermin: Mehrtägig-Checkbox + Enddatum ──
            array(
                'key'           => 'field_tc_multi_day',
                'label'         => 'Mehrtägig',
                'name'          => 'multi_day',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'conditional_logic' => array( array( array(
                    'field'    => 'field_tc_event_date_type',
                    'operator' => '==',
                    'value'    => 'single',
                ) ) ),
            ),
            array(
                'key'            => 'field_tc_end_date',
                'label'          => 'Bis Datum',
                'name'           => 'end_date',
                'type'           => 'date_picker',
                'display_format' => 'd.m.Y',
                'return_format'  => 'Y-m-d',
                'first_day'      => 1,
                'conditional_logic' => array( array(
                    array( 'field' => 'field_tc_event_date_type', 'operator' => '==', 'value' => 'single' ),
                    array( 'field' => 'field_tc_multi_day',       'operator' => '==', 'value' => '1' ),
                ) ),
            ),

            // ── Nur Wiederkehrend: Wochentag + Bis-Datum ──
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
                'required'      => 1,
                'conditional_logic' => array( array( array(
                    'field'    => 'field_tc_event_date_type',
                    'operator' => '==',
                    'value'    => 'recurring',
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
                'required'       => 1,
                'conditional_logic' => array( array( array(
                    'field'    => 'field_tc_event_date_type',
                    'operator' => '==',
                    'value'    => 'recurring',
                ) ) ),
            ),

            // ── Einzeltermin: optionaler Repeater für weitere Termine ──
            array(
                'key'          => 'field_tc_event_dates',
                'label'        => '+ Weitere Termine hinzufügen',
                'name'         => 'event_dates',
                'type'         => 'repeater',
                'layout'       => 'block',
                'button_label' => 'Weiteren Termin hinzufügen',
                'instructions' => 'Optional: Falls dasselbe Event an mehreren Daten stattfindet.',
                'conditional_logic' => array( array( array(
                    'field'    => 'field_tc_event_date_type',
                    'operator' => '==',
                    'value'    => 'single',
                ) ) ),
                'sub_fields'   => array(
                    array(
                        'key'            => 'field_tc_ed_start',
                        'label'          => 'Datum',
                        'name'           => 'date_start',
                        'type'           => 'date_picker',
                        'display_format' => 'd.m.Y',
                        'return_format'  => 'Y-m-d',
                        'first_day'      => 1,
                        'required'       => 1,
                        'wrapper'        => array( 'width' => '20' ),
                    ),
                    array(
                        'key'            => 'field_tc_ed_time_start',
                        'label'          => 'Von',
                        'name'           => 'time_start',
                        'type'           => 'time_picker',
                        'display_format' => 'H:i',
                        'return_format'  => 'H:i',
                        'wrapper'        => array( 'width' => '15' ),
                    ),
                    array(
                        'key'            => 'field_tc_ed_time_end',
                        'label'          => 'Bis',
                        'name'           => 'time_end',
                        'type'           => 'time_picker',
                        'display_format' => 'H:i',
                        'return_format'  => 'H:i',
                        'wrapper'        => array( 'width' => '15' ),
                    ),
                    array(
                        'key'            => 'field_tc_ed_end',
                        'label'          => 'Enddatum (optional)',
                        'name'           => 'date_end',
                        'type'           => 'date_picker',
                        'display_format' => 'd.m.Y',
                        'return_format'  => 'Y-m-d',
                        'first_day'      => 1,
                        'wrapper'        => array( 'width' => '20' ),
                    ),
                    array(
                        'key'          => 'field_tc_ed_seats',
                        'label'        => 'Max. Plätze (optional)',
                        'name'         => 'seats',
                        'type'         => 'number',
                        'min'          => 0,
                        'placeholder'  => 'unbegrenzt',
                        'instructions' => 'Überschreibt die globale Teilnehmerzahl für diesen Termin.',
                        'wrapper'      => array( 'width' => '15' ),
                    ),
                    array(
                        'key'         => 'field_tc_ed_notes',
                        'label'       => 'Hinweis',
                        'name'        => 'notes',
                        'type'        => 'text',
                        'placeholder' => 'z.B. Nur online',
                        'wrapper'     => array( 'width' => '15' ),
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
            'value'    => 'time_event',
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
// 3. Admin-Notice: Legacy-Felder noch aktiv
// ─────────────────────────────────────────────
add_action( 'admin_notices', function () {
    $screen = get_current_screen();
    if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== 'time_event' ) return;

    $post_id = get_the_ID();
    if ( ! $post_id ) {
        global $post;
        $post_id = $post ? $post->ID : 0;
    }
    if ( ! $post_id ) return;

    $date_type = get_field( 'event_date_type', $post_id );
    if ( empty( $date_type ) ) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>Time Calendar:</strong> ';
        echo 'Dieses Event wurde noch nicht auf den neuen Termintyp migriert. ';
        echo 'Bitte wählen Sie im Tab &bdquo;Termine&ldquo; den passenden Termintyp und speichern Sie.</p>';
        echo '</div>';
    }
} );

// ─────────────────────────────────────────────
// 5. ACF: event_type Select dynamisch befüllen
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
    if ( is_singular( 'time_event' ) ) {
        $plugin_tpl  = TC_PATH . 'templates/single-time_event.php';
        $legacy_tpl  = TC_PATH . 'templates/single-training_event.php';
        if ( ! file_exists( $plugin_tpl ) && file_exists( $legacy_tpl ) ) {
            $plugin_tpl = $legacy_tpl;
        }
        if ( file_exists( $plugin_tpl ) ) {
            return $plugin_tpl;
        }
    }
    return $template;
} );
