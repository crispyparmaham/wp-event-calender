/**
 * Time Calendar – Gegenseitige Ausschluss-Logik
 * für more_days (Mehrtägig) ↔ is_recurring (Wiederkehrend)
 *
 * ACF Conditional Logic blendet das jeweils andere Feld bereits aus,
 * dieses Skript ist eine zusätzliche Absicherung für den Fall dass
 * ACF die Conditional Logic nicht sofort greift (z.B. bei schnellen
 * Klicks oder bestehenden Konflikten in den gespeicherten Daten).
 */
(function ($) {
    'use strict';

    $(document).ready(function () {

        var KEY_MORE_DAYS    = 'field_tc_more_days';
        var KEY_IS_RECURRING = 'field_tc_is_recurring';

        // ── Hilfsfunktionen ─────────────────────────────────────

        function getContainer(key) {
            return $('.acf-field[data-key="' + key + '"]');
        }

        // ACF true_false mit ui:1 rendert einen Checkbox-Input;
        // wir lesen dessen checked-Status.
        function isChecked($container) {
            return $container.find('input[type="checkbox"]').is(':checked');
        }

        // Checkbox programmatisch deaktivieren und ACF-Change auslösen.
        function uncheck($container) {
            var $cb = $container.find('input[type="checkbox"]');
            if ($cb.is(':checked')) {
                $cb.prop('checked', false).trigger('change');
            }
        }

        // ── Hinweistext ─────────────────────────────────────────

        var $hintMoreDays    = $('<p class="tc-conflict-hint"></p>').css({
            display:       'none',
            color:         '#92400e',
            background:    '#fef3c7',
            border:        '1px solid #fcd34d',
            borderRadius:  '4px',
            padding:       '7px 12px',
            margin:        '6px 0 0',
            fontSize:      '13px',
            lineHeight:    '1.5',
        });
        var $hintRecurring = $hintMoreDays.clone();

        getContainer(KEY_MORE_DAYS).find('.acf-input').append($hintMoreDays);
        getContainer(KEY_IS_RECURRING).find('.acf-input').append($hintRecurring);

        var hintTimer = null;

        function showHint($hint, msg) {
            clearTimeout(hintTimer);
            $hintMoreDays.hide();
            $hintRecurring.hide();
            $hint.text(msg).show();
            hintTimer = setTimeout(function () { $hint.fadeOut(300); }, 4000);
        }

        // ── Gegenseitiger Ausschluss ─────────────────────────────

        // Guard verhindert Endlosschleife beim programmatischen Setzen
        var updating = false;

        getContainer(KEY_MORE_DAYS).find('input[type="checkbox"]').on('change', function () {
            if (updating) return;
            if (isChecked(getContainer(KEY_MORE_DAYS))) {
                updating = true;
                uncheck(getContainer(KEY_IS_RECURRING));
                updating = false;
                showHint(
                    $hintMoreDays,
                    'Mehrtägige Veranstaltungen können nicht gleichzeitig als wiederkehrend markiert werden.'
                );
            } else {
                $hintMoreDays.hide();
            }
        });

        getContainer(KEY_IS_RECURRING).find('input[type="checkbox"]').on('change', function () {
            if (updating) return;
            if (isChecked(getContainer(KEY_IS_RECURRING))) {
                updating = true;
                uncheck(getContainer(KEY_MORE_DAYS));
                updating = false;
                showHint(
                    $hintRecurring,
                    'Wiederkehrende Events können nicht gleichzeitig als mehrtägig markiert werden.'
                );
            } else {
                $hintRecurring.hide();
            }
        });

    });

}(jQuery));
