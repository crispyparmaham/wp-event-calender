/**
 * Time Calendar – Setup Wizard
 * Schritt-Navigation, Fortschrittsanzeige, Kategorie-AJAX, Einstellungs-AJAX.
 */
( function () {
    'use strict';

    const TOTAL_STEPS = 5;

    let currentStep = 1;

    // DOM-Referenzen
    const backdrop   = document.getElementById( 'tc-wizard-backdrop' );
    const fillBar    = document.getElementById( 'tc-wiz-progress-fill' );
    const stepLabel  = document.getElementById( 'tc-wiz-step-label' );
    const dots       = Array.from( document.querySelectorAll( '.tc-wiz-dot' ) );
    const stepEls    = Array.from( document.querySelectorAll( '.tc-wiz-step' ) );
    const btnBack    = document.getElementById( 'tc-wiz-btn-back' );
    const btnNext    = document.getElementById( 'tc-wiz-btn-next' );
    const btnSkip    = document.getElementById( 'tc-wiz-btn-skip' );

    // Nonces aus wp_localize_script
    const wizNonce = ( window.tcWizard || {} ).wizNonce || '';
    const catNonce = ( window.tcWizard || {} ).catNonce || '';
    const ajaxUrl  = ( window.tcWizard || {} ).ajaxUrl  || window.ajaxurl || '';

    // ── Hilfsfunktionen ────────────────────────────────────────────────

    function updateUI() {
        // Steps ein-/ausblenden
        stepEls.forEach( ( el, i ) => {
            el.classList.toggle( 'is-active', i + 1 === currentStep );
        } );

        // Fortschrittsbalken
        const pct = ( currentStep / TOTAL_STEPS ) * 100;
        fillBar.style.width = pct + '%';

        // Punkte
        dots.forEach( ( dot, i ) => {
            dot.classList.remove( 'is-active', 'is-done' );
            if ( i + 1 === currentStep ) {
                dot.classList.add( 'is-active' );
            } else if ( i + 1 < currentStep ) {
                dot.classList.add( 'is-done' );
            }
        } );

        // Schrittanzeige
        stepLabel.textContent = 'Schritt ' + currentStep + ' / ' + TOTAL_STEPS;

        // Zurück-Button
        if ( btnBack ) {
            btnBack.style.visibility = currentStep > 1 ? 'visible' : 'hidden';
        }

        // Weiter/Fertig-Text
        if ( btnNext ) {
            btnNext.textContent = currentStep === TOTAL_STEPS ? 'Fertig' : 'Weiter';
        }

        // Überspringen nur auf Schritt 3 (Kategorien)
        if ( btnSkip ) {
            btnSkip.style.display = currentStep === 3 ? 'inline-block' : 'none';
        }
    }

    function goNext() {
        if ( currentStep === TOTAL_STEPS ) {
            completeWizard( function () {
                backdrop.remove();
            } );
            return;
        }

        if ( currentStep === 1 ) {
            saveStep1();
        } else if ( currentStep === 2 ) {
            saveStep2();
        } else if ( currentStep === 4 ) {
            saveStep4();
        }

        currentStep++;
        updateUI();
    }

    function goBack() {
        if ( currentStep > 1 ) {
            currentStep--;
            updateUI();
        }
    }

    // ── Schritt 1: Farbe & Modus ────────────────────────────────────

    function saveStep1() {
        const colorInput = document.getElementById( 'tc-wiz-color' );
        const modeInput  = document.querySelector( 'input[name="tc_wiz_mode"]:checked' );

        const settings = {};
        if ( colorInput && colorInput.value ) {
            settings.primary_color = colorInput.value;
        }
        if ( modeInput ) {
            settings.calendar_mode = modeInput.value;
        }

        if ( Object.keys( settings ).length === 0 ) return;

        saveSettings( settings );
    }

    // ── Schritt 2: Anmeldung ────────────────────────────────────────

    function saveStep2() {
        const anredeInput = document.querySelector( 'input[name="tc_wiz_anrede"]:checked' );
        const emailInput  = document.getElementById( 'tc-wiz-reg-email' );

        const settings = {};
        if ( anredeInput ) {
            settings.anrede_mode = anredeInput.value;
        }
        if ( emailInput && emailInput.value ) {
            settings.registration_email = emailInput.value;
        }

        if ( Object.keys( settings ).length === 0 ) return;

        saveSettings( settings );
    }

    // ── Schritt 4: E-Mail-Absender ──────────────────────────────────

    function saveStep4() {
        const nameInput  = document.getElementById( 'tc-wiz-mail-name' );
        const emailInput = document.getElementById( 'tc-wiz-mail-email' );

        const settings = {};
        if ( nameInput && nameInput.value ) {
            settings.mail_from_name = nameInput.value;
        }
        if ( emailInput && emailInput.value ) {
            settings.mail_from_email = emailInput.value;
        }

        if ( Object.keys( settings ).length === 0 ) return;

        saveSettings( settings );
    }

    // ── AJAX: Einstellungen speichern ───────────────────────────────

    function saveSettings( settings ) {
        const data = new FormData();
        data.append( 'action', 'tc_save_wizard_settings' );
        data.append( 'nonce', wizNonce );
        Object.entries( settings ).forEach( ( [ k, v ] ) => data.append( k, v ) );

        fetch( ajaxUrl, { method: 'POST', body: data } ).catch( function () {
            // Fehler still schlucken – Wizard läuft trotzdem weiter.
        } );
    }

    // ── AJAX: Wizard als abgeschlossen markieren ────────────────────

    function completeWizard( callback ) {
        const data = new FormData();
        data.append( 'action', 'tc_complete_wizard' );
        data.append( 'nonce', wizNonce );

        fetch( ajaxUrl, { method: 'POST', body: data } )
            .then( function () {
                if ( typeof callback === 'function' ) callback();
            } )
            .catch( function () {
                if ( typeof callback === 'function' ) callback();
            } );
    }

    // ── Schritt 3: Kategorien ───────────────────────────────────────

    const catList     = document.getElementById( 'tc-wiz-categories-list' );
    const catNameInp  = document.getElementById( 'tc-wiz-cat-name' );
    const catColorInp = document.getElementById( 'tc-wiz-cat-color' );
    const btnAddCat   = document.getElementById( 'tc-wiz-btn-add-cat' );

    function addCategoryRow( name, color ) {
        if ( ! catList ) return;
        const row = document.createElement( 'div' );
        row.className = 'tc-wiz-cat-row';
        row.innerHTML =
            '<span class="tc-wiz-cat-dot" style="background:' + color + '"></span>' +
            '<span class="tc-wiz-cat-name">' + escHtml( name ) + '</span>';
        catList.appendChild( row );
    }

    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

    function handleAddCategory() {
        const name  = ( catNameInp  ? catNameInp.value.trim()  : '' );
        const color = ( catColorInp ? catColorInp.value        : '#4f46e5' );

        if ( ! name ) {
            if ( catNameInp ) catNameInp.focus();
            return;
        }

        if ( btnAddCat ) btnAddCat.disabled = true;

        const data = new FormData();
        data.append( 'action', 'tc_create_category' );
        data.append( 'nonce',  catNonce );
        data.append( 'name',   name );
        data.append( 'color',  color );

        fetch( ajaxUrl, { method: 'POST', body: data } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( res ) {
                if ( res.success ) {
                    addCategoryRow( res.data.name, res.data.color );
                    if ( catNameInp  ) catNameInp.value  = '';
                    if ( catColorInp ) catColorInp.value = '#4f46e5';
                } else {
                    alert( ( res.data && res.data.message ) || 'Fehler beim Anlegen.' );
                }
            } )
            .catch( function () {
                alert( 'Verbindungsfehler. Bitte erneut versuchen.' );
            } )
            .finally( function () {
                if ( btnAddCat ) btnAddCat.disabled = false;
            } );
    }

    if ( btnAddCat ) {
        btnAddCat.addEventListener( 'click', handleAddCategory );
    }

    if ( catNameInp ) {
        catNameInp.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Enter' ) {
                e.preventDefault();
                handleAddCategory();
            }
        } );
    }

    // ── Farb-Picker ↔ Hex-Input-Sync ───────────────────────────────

    const colorPicker = document.getElementById( 'tc-wiz-color-picker' );
    const colorHex    = document.getElementById( 'tc-wiz-color' );
    const colorPrev   = document.getElementById( 'tc-wiz-color-preview' );

    function updateColorPreview( hex ) {
        if ( colorPrev ) colorPrev.style.background = hex;
    }

    if ( colorPicker && colorHex ) {
        colorPicker.addEventListener( 'input', function () {
            colorHex.value = colorPicker.value;
            updateColorPreview( colorPicker.value );
        } );
        colorHex.addEventListener( 'input', function () {
            const v = colorHex.value.trim();
            if ( /^#[0-9a-fA-F]{6}$/.test( v ) ) {
                colorPicker.value = v;
                updateColorPreview( v );
            }
        } );
    }

    // ── Event-Listener ──────────────────────────────────────────────

    if ( btnNext ) btnNext.addEventListener( 'click', goNext );
    if ( btnBack ) btnBack.addEventListener( 'click', goBack );

    if ( btnSkip ) {
        btnSkip.addEventListener( 'click', function () {
            currentStep++;
            updateUI();
        } );
    }

    // Schritt 5: Buttons „Zum Kalender" / „Zu den Einstellungen"
    const btnGoCalendar  = document.getElementById( 'tc-wiz-go-calendar' );
    const btnGoSettings  = document.getElementById( 'tc-wiz-go-settings' );

    if ( btnGoCalendar ) {
        btnGoCalendar.addEventListener( 'click', function () {
            completeWizard( function () {
                window.location.href = btnGoCalendar.dataset.href || '';
            } );
        } );
    }

    if ( btnGoSettings ) {
        btnGoSettings.addEventListener( 'click', function () {
            completeWizard( function () {
                window.location.href = btnGoSettings.dataset.href || '';
            } );
        } );
    }

    // ── Init ────────────────────────────────────────────────────────

    updateUI();

} )();
