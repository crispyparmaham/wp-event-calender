<?php
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Wiederverwendbarer Bestätigungs-Dialog
//
// Ersetzt window.confirm() in den Admin-Listen.
// Einmal pro Seite via tc_render_admin_confirm_modal()
// ausgeben, danach steht window.tcConfirm() bereit:
//
//   tcConfirm({
//       title:     'Anmeldung bestätigen',
//       message:   'Max Mustermann wird für …',
//       tone:      'confirm' | 'registered' | 'danger',
//       checkbox:  { label: 'Mail senden', checked: true },  // optional
//       confirmText: 'Bestätigen',
//       onConfirm: function( checked ) { … }
//   });
// ─────────────────────────────────────────────
function tc_render_admin_confirm_modal() {
    static $rendered = false;
    if ( $rendered ) return;
    $rendered = true;
    ?>
    <div class="tc-modal-backdrop" id="tc-modal" aria-hidden="true">
        <div class="tc-modal" role="dialog" aria-modal="true" aria-labelledby="tc-modal-title">
            <h2 class="tc-modal-title" id="tc-modal-title"></h2>
            <p class="tc-modal-message"></p>
            <label class="tc-modal-check">
                <input type="checkbox" class="tc-modal-check-input">
                <span class="tc-modal-check-label"></span>
            </label>
            <div class="tc-modal-actions">
                <button type="button" class="button tc-modal-cancel">Abbrechen</button>
                <button type="button" class="button button-primary tc-modal-ok"></button>
            </div>
        </div>
    </div>

    <style>
        .tc-modal-backdrop {
            position: fixed; inset: 0; z-index: 100100;
            background: rgba(17, 24, 39, .55);
            display: none; align-items: center; justify-content: center;
            padding: 20px;
        }
        .tc-modal-backdrop.is-open { display: flex; }
        .tc-modal {
            background: #fff; border-radius: 10px; width: 100%; max-width: 440px;
            padding: 24px 26px 20px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, .25);
            animation: tc-modal-in .14s ease-out;
        }
        @keyframes tc-modal-in {
            from { opacity: 0; transform: translateY(-8px) scale(.98); }
            to   { opacity: 1; transform: none; }
        }
        .tc-modal-title {
            margin: 0 0 10px; font-size: 17px; line-height: 1.3;
            font-weight: 600; color: #111827;
        }
        .tc-modal-message {
            margin: 0; font-size: 13.5px; line-height: 1.6; color: #4b5563;
        }
        .tc-modal-check {
            display: none; align-items: flex-start; gap: 8px;
            margin: 16px 0 0; padding: 11px 13px;
            background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;
            font-size: 13px; color: #374151; cursor: pointer;
        }
        .tc-modal-check.is-visible { display: flex; }
        .tc-modal-check input { margin: 1px 0 0; flex: 0 0 auto; }
        .tc-modal-actions {
            display: flex; justify-content: flex-end; gap: 8px; margin-top: 22px;
        }
        .tc-modal-ok.button-primary {
            background: #4f46e5; border-color: #4338ca;
        }
        .tc-modal-ok.button-primary:hover { background: #4338ca; border-color: #3730a3; }
        .tc-modal--confirm    .tc-modal-ok.button-primary { background: #059669; border-color: #047857; }
        .tc-modal--confirm    .tc-modal-ok.button-primary:hover { background: #047857; border-color: #065f46; }
        .tc-modal--registered .tc-modal-ok.button-primary { background: #2563eb; border-color: #1d4ed8; }
        .tc-modal--registered .tc-modal-ok.button-primary:hover { background: #1d4ed8; border-color: #1e40af; }
        .tc-modal--danger     .tc-modal-ok.button-primary { background: #dc2626; border-color: #b91c1c; }
        .tc-modal--danger     .tc-modal-ok.button-primary:hover { background: #b91c1c; border-color: #991b1b; }
    </style>

    <script>
    jQuery(function($) {
        var $backdrop = $('#tc-modal');
        var $modal    = $backdrop.find('.tc-modal');
        var $check    = $backdrop.find('.tc-modal-check');
        var $input    = $backdrop.find('.tc-modal-check-input');
        var $ok       = $backdrop.find('.tc-modal-ok');
        var handler   = null;

        function close() {
            $backdrop.removeClass('is-open').attr('aria-hidden', 'true');
            handler = null;
        }

        window.tcConfirm = function(opts) {
            handler = typeof opts.onConfirm === 'function' ? opts.onConfirm : null;

            $modal.attr('class', 'tc-modal' + (opts.tone ? ' tc-modal--' + opts.tone : ''));
            $backdrop.find('.tc-modal-title').text(opts.title || 'Bitte bestätigen');
            $backdrop.find('.tc-modal-message').text(opts.message || '');
            $ok.text(opts.confirmText || 'Bestätigen');

            if (opts.checkbox) {
                $check.addClass('is-visible').find('.tc-modal-check-label').text(opts.checkbox.label);
                $input.prop('checked', opts.checkbox.checked !== false);
            } else {
                $check.removeClass('is-visible');
                $input.prop('checked', false);
            }

            $backdrop.addClass('is-open').attr('aria-hidden', 'false');
            $ok.trigger('focus');
        };

        $ok.on('click', function() {
            var cb = handler;
            var checked = $input.is(':checked');
            close();
            if (cb) cb(checked);
        });

        $backdrop.find('.tc-modal-cancel').on('click', close);

        // Klick auf den Hintergrund schließt, Klick im Dialog nicht
        $backdrop.on('click', function(e) {
            if (e.target === this) close();
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $backdrop.hasClass('is-open')) close();
        });
    });
    </script>
    <?php
}
