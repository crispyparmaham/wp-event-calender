/**
 * Time Calendar – Admin Settings
 * Ausgelagerte JS-Logik für die Settings-Seite.
 * Geladen per wp_enqueue_script nur auf time_event_page_time-calendar-settings.
 */
(function () {
    'use strict';

    var data   = window.tcSettingsData || {};
    var tabMap = data.tabMap || {};

    // ── Unsaved Changes ────────────────────────────────────────────
    var form         = document.getElementById('tc-stg-form');
    var unsavedBadge = document.getElementById('tc-stg-unsaved');
    var isDirty      = false;

    if (form) {
        var markDirty = function () {
            if (!isDirty) {
                isDirty = true;
                if (unsavedBadge) unsavedBadge.hidden = false;
            }
        };
        form.addEventListener('change', markDirty);
        form.addEventListener('input',  markDirty);
        form.addEventListener('submit', function () {
            isDirty = false;
            if (unsavedBadge) unsavedBadge.hidden = true;
        });
        window.addEventListener('beforeunload', function (e) {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    // ── Color Pickers ──────────────────────────────────────────────
    function bindColorPicker(inputId, labelId) {
        var inp = document.getElementById(inputId);
        var lbl = document.getElementById(labelId);
        if (inp && lbl) {
            inp.addEventListener('input', function () { lbl.textContent = inp.value; });
        }
    }

    bindColorPicker('tc-primary-color-input', 'tc-primary-color-label');

    [
        'token_bg_light',           'token_bg_dark',
        'token_bg_secondary_light', 'token_bg_secondary_dark',
        'token_surface_light',      'token_surface_dark',
        'token_text_light',         'token_text_dark',
        'token_text_muted_light',   'token_text_muted_dark',
        'token_border_light',       'token_border_dark'
    ].forEach(function (id) {
        bindColorPicker('tc-' + id, 'tc-' + id + '-lbl');
    });

    // ── Dark-Mode Label ────────────────────────────────────────────
    var modeCb    = document.querySelector('input[name="tc_settings[calendar_mode]"]');
    var modeLabel = document.getElementById('tc-mode-label');
    if (modeCb && modeLabel) {
        modeCb.addEventListener('change', function () {
            modeLabel.textContent = modeCb.checked ? 'Dark Mode' : 'Light Mode';
        });
    }

    // ── Two-Level Tab Navigation ───────────────────────────────────
    var MAIN_KEY  = 'tc_settings_main_tab';
    var SUBS_KEY  = 'tc_settings_sub_tabs';
    var mainBtns  = document.querySelectorAll('.tc-stg-tabs--main .tc-stg-tab');
    var mainPanes = document.querySelectorAll('.tc-stg-main-pane');

    function getSavedSubs() {
        try { return JSON.parse(localStorage.getItem(SUBS_KEY) || '{}'); } catch (e) { return {}; }
    }
    function saveSub(mainId, subId) {
        try {
            var s = getSavedSubs();
            s[mainId] = subId;
            localStorage.setItem(SUBS_KEY, JSON.stringify(s));
        } catch (e) {}
    }

    function activateSubTab(mainId, subId) {
        var subs = tabMap[mainId] || [];
        if (subs.indexOf(subId) === -1) subId = subs[0];
        var mainPane = document.querySelector('.tc-stg-main-pane[data-main-tab="' + mainId + '"]');
        if (!mainPane) return;

        var subBtns  = mainPane.querySelectorAll('.tc-stg-tabs--sub .tc-stg-tab');
        var subPanes = mainPane.querySelectorAll('.tc-stg-pane[data-sub-tab]');

        subBtns.forEach(function (btn) {
            var active = btn.dataset.subTab === subId;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
            btn.tabIndex = active ? 0 : -1;
        });
        subPanes.forEach(function (pane) {
            var active = pane.dataset.subTab === subId;
            pane.classList.toggle('is-active', active);
        });
        saveSub(mainId, subId);
    }

    function activateMainTab(mainId, forceSubId) {
        var keys = Object.keys(tabMap);
        if (!tabMap[mainId]) mainId = keys[0] || 'design';

        mainBtns.forEach(function (btn) {
            var active = btn.dataset.mainTab === mainId;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
            btn.tabIndex = active ? 0 : -1;
        });
        mainPanes.forEach(function (pane) {
            pane.classList.toggle('is-active', pane.dataset.mainTab === mainId);
        });

        try { localStorage.setItem(MAIN_KEY, mainId); } catch (e) {}

        var subId = forceSubId;
        if (!subId) {
            var saved = getSavedSubs()[mainId];
            var valid = tabMap[mainId] || [];
            subId = (saved && valid.indexOf(saved) !== -1) ? saved : valid[0];
        }
        activateSubTab(mainId, subId);

        if (history.replaceState) {
            var subSlug = subId ? subId.replace(mainId + '-', '') : '';
            history.replaceState(null, '', location.pathname + location.search + '#' + mainId + (subSlug ? '/' + subSlug : ''));
        }
    }

    // Arrow-key navigation (ARIA Tabs-Pattern)
    function handleTabKeydown(e, btns, isMain) {
        var idx = Array.prototype.indexOf.call(btns, e.target);
        if (idx === -1) return;
        var next = -1;
        if      (e.key === 'ArrowRight' || e.key === 'ArrowDown')  { e.preventDefault(); next = (idx + 1) % btns.length; }
        else if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')    { e.preventDefault(); next = (idx - 1 + btns.length) % btns.length; }
        else if (e.key === 'Home')                                  { e.preventDefault(); next = 0; }
        else if (e.key === 'End')                                   { e.preventDefault(); next = btns.length - 1; }
        if (next === -1) return;
        btns[next].focus();
        if (isMain) {
            activateMainTab(btns[next].dataset.mainTab);
        } else {
            var mainPane = btns[next].closest('.tc-stg-main-pane');
            if (mainPane) activateSubTab(mainPane.dataset.mainTab, btns[next].dataset.subTab);
        }
    }

    mainBtns.forEach(function (btn) {
        btn.addEventListener('click',   function () { activateMainTab(btn.dataset.mainTab); });
        btn.addEventListener('keydown', function (e) { handleTabKeydown(e, mainBtns, true); });
    });

    mainPanes.forEach(function (pane) {
        var mainId  = pane.dataset.mainTab;
        var subBtns = pane.querySelectorAll('.tc-stg-tabs--sub .tc-stg-tab');
        subBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                activateSubTab(mainId, btn.dataset.subTab);
                if (history.replaceState) {
                    var subSlug = btn.dataset.subTab.replace(mainId + '-', '');
                    history.replaceState(null, '', location.pathname + location.search + '#' + mainId + '/' + subSlug);
                }
            });
            btn.addEventListener('keydown', function (e) { handleTabKeydown(e, subBtns, false); });
        });
    });

    // Initial activation: URL hash → localStorage → default
    var hash        = (location.hash || '').replace('#', '');
    var parts       = hash.split('/');
    var hashMain    = parts[0] || '';
    var hashSubSlug = parts[1] || '';
    var savedMain   = '';
    try { savedMain = localStorage.getItem(MAIN_KEY) || ''; } catch (e) {}
    var initMain = tabMap[hashMain] ? hashMain : (tabMap[savedMain] ? savedMain : Object.keys(tabMap)[0]);
    var initSub  = null;
    if (hashMain === initMain && hashSubSlug) {
        var candidate = initMain + '-' + hashSubSlug;
        if ((tabMap[initMain] || []).indexOf(candidate) !== -1) initSub = candidate;
    }
    activateMainTab(initMain, initSub);

    // ── Mobile-View Hints ──────────────────────────────────────────
    var mobileRadios = document.querySelectorAll('input[name="tc_settings[mobile_calendar_view]"]');
    var mobileHints  = document.querySelectorAll('.tc-stg-mobile-hint');
    var hintBoxRow   = document.getElementById('tc-hint-box-row');
    if (mobileHints.length) {
        mobileRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                mobileHints.forEach(function (h) {
                    h.style.display = (h.dataset.for === radio.value && radio.checked) ? '' : 'none';
                });
                if (hintBoxRow) hintBoxRow.style.display = radio.value === 'slider' ? '' : 'none';
            });
        });
    }

    // ── Column-Label / Event-Time Hints ───────────────────────────
    function bindRadioHints(radioName, hintClass) {
        var radios = document.querySelectorAll('input[name="' + radioName + '"]');
        var hints  = document.querySelectorAll('.' + hintClass);
        if (!hints.length) return;
        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                hints.forEach(function (h) {
                    h.style.display = (h.dataset.for === radio.value && radio.checked) ? '' : 'none';
                });
            });
        });
    }
    bindRadioHints('tc_settings[time_column_label]',  'tc-stg-col-hint');
    bindRadioHints('tc_settings[event_time_display]', 'tc-stg-evt-hint');

    // ── Event-List Conditional ────────────────────────────────────
    var eventListCb  = document.getElementById('tc-show-event-list');
    var eventListRow = document.getElementById('tc-event-list-title-row');
    if (eventListCb && eventListRow) {
        eventListCb.addEventListener('change', function () {
            eventListRow.style.display = eventListCb.checked ? '' : 'none';
        });
    }

    // ── Toast ──────────────────────────────────────────────────────
    var toast = document.getElementById('tc-stg-toast');
    if (toast && toast.dataset.show === 'true') {
        toast.classList.add('is-visible');
        setTimeout(function () { toast.classList.remove('is-visible'); }, 4500);
    }

    // ── Mail Expert Mode ───────────────────────────────────────────
    window.tcConvertMailToHtml = function (mailId) {
        var card = document.getElementById('tc-mail-card-' + mailId);
        if (!card) return '';
        function v(name) {
            var el = card.querySelector('[name="tc_settings[mail_' + mailId + '_' + name + ']"]');
            return el ? el.value : '';
        }
        function toHtml(s) {
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
        }
        var anrede    = v('anrede');
        var haupttext = v('haupttext');
        var abschluss = v('abschluss');
        var signatur  = v('signatur');
        var showEvEl  = card.querySelector('[name="tc_settings[mail_' + mailId + '_show_event]"]');
        var html = '';
        if (anrede)    html += '<p>' + toHtml(anrede) + '</p>\n';
        if (haupttext) html += '<p>' + toHtml(haupttext) + '</p>\n';
        if (showEvEl && showEvEl.checked) html += '<!-- EVENT_BLOCK -->\n';
        if (abschluss) html += '<p>' + toHtml(abschluss) + '</p>\n';
        if (signatur)  html += '<hr style="border:none;border-top:1px solid #ddd;margin:20px 0;">\n'
                             + '<p style="font-size:.9em;color:#666;">' + toHtml(signatur) + '</p>\n';
        return html;
    };

    window.tcToggleMailExpert = function (mailId, activate) {
        var card       = document.getElementById('tc-mail-card-' + mailId);
        if (!card) return;
        var modeInput  = document.getElementById('tc-mail-' + mailId + '-expert-mode');
        var structured = card.querySelector('.tc-mail-structured');
        var expertEd   = card.querySelector('.tc-mail-expert-editor');
        var htmlArea   = card.querySelector('[name="tc_settings[mail_' + mailId + '_expert_html]"]');
        if (activate) {
            var current = htmlArea ? htmlArea.value.trim() : '';
            if (!current) {
                if (!confirm('Den strukturierten Inhalt als HTML-Code übernehmen?')) return;
                if (htmlArea) htmlArea.value = window.tcConvertMailToHtml(mailId);
            }
            if (modeInput)  modeInput.value = '1';
            if (structured) structured.style.display = 'none';
            if (expertEd)   expertEd.style.display   = '';
        } else {
            if (modeInput)  modeInput.value = '0';
            if (structured) structured.style.display = '';
            if (expertEd)   expertEd.style.display   = 'none';
        }
    };

})();
