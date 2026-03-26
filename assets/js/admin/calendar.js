/* global FullCalendar, TC */
document.addEventListener('DOMContentLoaded', () => {

  // ── Zeitbereich der Wochenansicht dynamisch anpassen ──────────────
  function updateVisibleTimeRange(calendar) {
    if (calendar.view.type !== 'timeGridWeek') return;

    const viewStart = calendar.view.activeStart;
    const viewEnd   = calendar.view.activeEnd;
    const toMin     = (d) => d.getHours() * 60 + d.getMinutes();

    let earliest = Infinity;
    let latest   = -Infinity;

    calendar.getEvents().forEach(e => {
      if (e.allDay) return;
      const s   = e.start;
      const end = e.end || s;
      if (s >= viewEnd || end <= viewStart) return;

      const sm = toMin(s);
      const em = (end.getHours() === 0 && end.getMinutes() === 0) ? 1440 : toMin(end);
      if (sm < earliest) earliest = sm;
      if (em > latest)   latest   = em;
    });

    const pad = n => String(n).padStart(2, '0');
    let minT, maxT;

    if (!isFinite(earliest)) {
      minT = '08:00:00';
      maxT = '20:00:00';
    } else {
      const minMin = Math.max(0,    earliest - 60);
      const maxMin = Math.min(1440, latest   + 60);
      minT = `${pad(Math.floor(minMin / 60))}:${pad(minMin % 60)}:00`;
      maxT = `${pad(Math.floor(maxMin / 60))}:${pad(maxMin % 60)}:00`;
    }

    calendar.setOption('slotMinTime', minT);
    calendar.setOption('slotMaxTime', maxT);
    calendar.setOption('scrollTime',  minT);
  }

  const el          = document.getElementById('tc-calendar');
  const modal       = document.getElementById('tc-modal');
  const backdrop    = document.getElementById('tc-modal-backdrop');
  const errBox      = document.getElementById('tc-modal-error');
  const saveBar     = document.getElementById('tc-save-bar');
  const saveCount   = document.getElementById('tc-save-count');
  const saveBtnEl   = document.getElementById('tc-save-bar-save');
  const resetBtnEl  = document.getElementById('tc-save-bar-reset');

  // ── Modal-Elemente ───────────────────────────────────────────────
  const typeSelect       = document.getElementById('tc-modal-type');
  const newCatBtn        = document.getElementById('tc-modal-new-cat-btn');
  const newCatWrap       = document.getElementById('tc-modal-new-cat');
  const catNameInput     = document.getElementById('tc-modal-cat-name');
  const catColorInput    = document.getElementById('tc-modal-cat-color');
  const catColorVal      = document.getElementById('tc-modal-cat-color-val');
  const catSaveBtn       = document.getElementById('tc-modal-cat-save');
  const catCancelBtn     = document.getElementById('tc-modal-cat-cancel');
  const catErrBox        = document.getElementById('tc-modal-cat-error');
  const dateTypeRadios   = document.querySelectorAll('input[name="tc-modal-date-type"]');
  const fieldsSingle     = document.getElementById('tc-fields-single');
  const fieldsRecurring  = document.getElementById('tc-fields-recurring');
  const multidayCheck    = document.getElementById('tc-modal-multiday');
  const endDateWrap      = document.getElementById('tc-modal-enddate-wrap');
  const addDateToggle    = document.getElementById('tc-modal-add-date-toggle');
  const extraDatesList   = document.getElementById('tc-modal-extra-dates-list');
  const addDateBtn       = document.getElementById('tc-modal-add-date-btn');

  // ── Ausstehende Änderungen (Drag & Drop / Resize) ─────────────
  const pendingChanges = new Map();

  const updateSaveBar = () => {
    const n = pendingChanges.size;
    if (n === 0) {
      saveBar.style.display = 'none';
      saveBar.classList.remove('is-saved');
      return;
    }
    saveBar.style.display = 'flex';
    saveCount.textContent = n === 1
      ? '1 ungespeicherte Änderung'
      : `${n} ungespeicherte Änderungen`;
  };

  const trackChange = (event, oldEvent) => {
    const dateIndex = event.extendedProps.dateIndex;
    const key       = `${event.id}:${dateIndex ?? ''}`;
    if (!pendingChanges.has(key)) {
      pendingChanges.set(key, {
        event,
        origStart: oldEvent.startStr,
        origEnd:   oldEvent.endStr || '',
      });
    } else {
      pendingChanges.get(key).event = event;
    }
    updateSaveBar();
  };

  // ── Hilfsfunktionen ────────────────────────────────────────────
  const post = async (action, data) => {
    const body = new URLSearchParams({ action, nonce: TC.nonce, ...data });
    const res  = await fetch(TC.ajaxUrl, { method: 'POST', body });
    return res.json();
  };

  const postFormData = async (action, formData) => {
    formData.append('action', action);
    formData.append('nonce', TC.nonce);
    const res = await fetch(TC.ajaxUrl, { method: 'POST', body: formData });
    return res.json();
  };

  const showError = (msg) => {
    errBox.textContent   = msg;
    errBox.style.display = 'block';
  };

  const getDateType = () => {
    const checked = document.querySelector('input[name="tc-modal-date-type"]:checked');
    return checked ? checked.value : 'single';
  };

  // Wochentag aus einem Datums-String lesen (0=So … 6=Sa)
  const getWeekday = (dateStr) => new Date(dateStr + 'T00:00:00').getDay().toString();

  // ── Kategorien per AJAX laden ──────────────────────────────────
  const loadCategories = async (selectSlug) => {
    const res = await post('tc_get_categories', {});
    if (!res.success) return;
    typeSelect.innerHTML = '';
    (res.data || []).forEach(cat => {
      const opt = document.createElement('option');
      opt.value       = cat.slug;
      opt.textContent = cat.name;
      typeSelect.appendChild(opt);
    });
    if (selectSlug) typeSelect.value = selectSlug;
  };

  // ── Neue Kategorie: inline Bereich ─────────────────────────────
  newCatBtn.addEventListener('click', () => {
    newCatWrap.style.display = newCatWrap.style.display === 'none' ? '' : 'none';
    catErrBox.style.display  = 'none';
    catNameInput.value       = '';
    catColorInput.value      = '#4f46e5';
    catColorVal.textContent  = '#4f46e5';
    if (newCatWrap.style.display !== 'none') catNameInput.focus();
  });

  catColorInput.addEventListener('input', () => {
    catColorVal.textContent = catColorInput.value;
  });

  catCancelBtn.addEventListener('click', () => {
    newCatWrap.style.display = 'none';
  });

  catSaveBtn.addEventListener('click', async () => {
    const name  = catNameInput.value.trim();
    const color = catColorInput.value;
    if (!name) {
      catErrBox.textContent   = 'Bitte einen Namen eingeben.';
      catErrBox.style.display = 'block';
      return;
    }
    catSaveBtn.disabled    = true;
    catSaveBtn.textContent = 'Wird angelegt…';
    catErrBox.style.display = 'none';

    const res = await post('tc_create_category', { name, color });

    catSaveBtn.disabled    = false;
    catSaveBtn.textContent = 'Anlegen';

    if (!res.success) {
      catErrBox.textContent   = res.data?.message || 'Fehler beim Anlegen.';
      catErrBox.style.display = 'block';
      return;
    }

    // Dropdown neu laden, neue Kategorie auswählen
    await loadCategories(res.data.slug);
    newCatWrap.style.display = 'none';
  });

  // ── Termintyp-Toggle ───────────────────────────────────────────
  dateTypeRadios.forEach(radio => {
    radio.addEventListener('change', () => {
      const dt = getDateType();
      fieldsSingle.style.display    = dt === 'single'    ? '' : 'none';
      fieldsRecurring.style.display = dt === 'recurring' ? '' : 'none';
    });
  });

  // ── Mehrtägig-Toggle ───────────────────────────────────────────
  multidayCheck.addEventListener('change', () => {
    endDateWrap.style.display = multidayCheck.checked ? '' : 'none';
  });

  // ── Weitere Termine (Repeater im Modal) ────────────────────────
  let extraDateCounter = 0;

  const addExtraDateRow = () => {
    extraDateCounter++;
    const row = document.createElement('div');
    row.className = 'tc-modal-extra-date-row';
    row.innerHTML = `
      <div class="tc-modal-extra-date-header">
        <strong>Termin ${extraDateCounter + 1}</strong>
        <button type="button" class="tc-modal-extra-date-remove" title="Entfernen">&times;</button>
      </div>
      <label>Datum <span class="required">*</span>
        <input type="date" class="tc-extra-date" />
      </label>
      <div class="tc-modal-time-row">
        <label>Von <input type="time" class="tc-extra-time-start" /></label>
        <label>Bis <input type="time" class="tc-extra-time-end" /></label>
      </div>
    `;
    extraDatesList.appendChild(row);

    row.querySelector('.tc-modal-extra-date-remove').addEventListener('click', () => {
      row.remove();
      renumberExtraDates();
      if (extraDatesList.children.length === 0) {
        extraDatesList.style.display = 'none';
        addDateBtn.style.display     = 'none';
        addDateToggle.style.display  = '';
      }
    });
  };

  const renumberExtraDates = () => {
    extraDatesList.querySelectorAll('.tc-modal-extra-date-row').forEach((row, i) => {
      row.querySelector('strong').textContent = `Termin ${i + 2}`;
    });
    extraDateCounter = extraDatesList.children.length;
  };

  addDateToggle.addEventListener('click', () => {
    addDateToggle.style.display  = 'none';
    extraDatesList.style.display = '';
    addDateBtn.style.display     = '';
    addExtraDateRow();
  });

  addDateBtn.addEventListener('click', () => {
    addExtraDateRow();
  });

  // ── Modal öffnen / schließen ───────────────────────────────────
  const closeModal = () => {
    modal.style.display    = 'none';
    backdrop.style.display = 'none';
    errBox.style.display   = 'none';
    newCatWrap.style.display = 'none';

    // Single fields reset
    document.getElementById('tc-modal-title').value = '';
    document.getElementById('tc-modal-date').value  = '';
    document.getElementById('tc-modal-time-start').value = '';
    document.getElementById('tc-modal-time-end').value   = '';
    multidayCheck.checked    = false;
    endDateWrap.style.display = 'none';
    document.getElementById('tc-modal-end-date').value = '';

    // Recurring fields reset
    document.getElementById('tc-modal-rec-date').value       = '';
    document.getElementById('tc-modal-rec-time-start').value = '';
    document.getElementById('tc-modal-rec-time-end').value   = '';
    document.getElementById('tc-modal-weekday').value        = '1';
    document.getElementById('tc-modal-until').value          = '';

    // Extra dates reset
    extraDatesList.innerHTML     = '';
    extraDatesList.style.display = 'none';
    addDateBtn.style.display     = 'none';
    addDateToggle.style.display  = '';
    extraDateCounter = 0;

    // Date type reset
    dateTypeRadios.forEach(r => r.checked = r.value === 'single');
    fieldsSingle.style.display    = '';
    fieldsRecurring.style.display = 'none';
  };

  const openModal = async (dateStr) => {
    // Kategorien frisch laden
    await loadCategories();

    if (dateStr) {
      const datePart = dateStr.length > 10 ? dateStr.slice(0, 10) : dateStr;
      const timePart = dateStr.length > 10 ? dateStr.slice(11, 16) : '08:00';
      document.getElementById('tc-modal-date').value       = datePart;
      document.getElementById('tc-modal-time-start').value = timePart;
      document.getElementById('tc-modal-rec-date').value   = datePart;
      document.getElementById('tc-modal-rec-time-start').value = timePart;
      document.getElementById('tc-modal-weekday').value    = getWeekday(datePart);
    }

    modal.style.display    = 'flex';
    backdrop.style.display = 'block';
    document.getElementById('tc-modal-title').focus();
  };

  // ── Kalender initialisieren ────────────────────────────────────
  const calendar = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth',
    locale:      'de',
    height:      'auto',
    firstDay:    1,

    headerToolbar: {
      left:   'prev,next today',
      center: 'title',
      right:  'dayGridMonth,timeGridWeek,listMonth',
    },

    buttonText: {
      today: 'Heute',
      month: 'Monat',
      week:  'Woche',
      list:  'Liste',
    },

    editable:      true,
    droppable:     true,
    slotDuration:  '00:30:00',

    datesSet() {
      updateVisibleTimeRange(calendar);
    },

    eventsSet() {
      updateVisibleTimeRange(calendar);
    },

    // ── Events laden ──────────────────────────────────────────
    events: async (info, success, failure) => {
      try {
        const res = await post('tc_get_events', {});
        if (res.success) success(res.data);
        else failure(res.data?.message || 'Fehler beim Laden.');
      } catch (e) {
        failure(e.message);
      }
    },

    // ── Tooltip ───────────────────────────────────────────────
    eventDidMount({ event, el: evEl }) {
      const p = event.extendedProps;

      // Vergangene Occurrences ausgegraut darstellen
      if (p.isPast && p.dateIndex === -2) {
        evEl.style.opacity = '0.4';
        evEl.style.filter  = 'grayscale(30%)';
      }
      const lines = [
        p.isRecurring              ? '🔁 Wiederkehrendes Event' : null,
        p.leadership               ? `👤 ${p.leadership}`       : null,
        p.location                 ? `📍 ${p.location}`         : null,
        p.participants             ? `👥 ${p.participants}`      : null,
        p.price                    ? `💶 ${p.price} €`          : null,
      ].filter(Boolean).join('\n');

      if (lines) evEl.title = lines;

      // Occurrences (editable=false) leicht transparent darstellen
      if (!event.startEditable) evEl.style.opacity = '0.75';
    },

    // ── Klick auf leeren Tag → Modal ──────────────────────────
    dateClick({ dateStr }) {
      openModal(dateStr);
    },

    // ── Klick auf Event → WP-Editor ───────────────────────────
    eventClick({ event }) {
      const url = event.extendedProps.editUrl || event.url;
      if (url) window.open(url, '_blank');
    },

    // ── Drag & Drop → Änderung vormerken ──────────────────────
    eventDrop: ({ event, oldEvent }) => {
      trackChange(event, oldEvent);
    },

    // ── Resize → Änderung vormerken ───────────────────────────
    eventResize: ({ event, oldEvent }) => {
      trackChange(event, oldEvent);
    },
  });

  calendar.render();

  // ── Save-Bar: Speichern ───────────────────────────────────────
  saveBtnEl.addEventListener('click', async () => {
    saveBtnEl.disabled    = true;
    saveBtnEl.textContent = 'Wird gespeichert…';

    let errors = 0;

    for (const [key, entry] of pendingChanges) {
      const ev        = entry.event;
      const dateIndex = ev.extendedProps.dateIndex ?? -1;
      const dateType  = ev.extendedProps.dateType  ?? 'single';
      const payload   = {
        id:         ev.id,
        start:      ev.startStr,
        end:        ev.endStr || '',
        date_index: dateIndex,
        date_type:  dateType,
      };

      const res = await post('tc_update_event', payload);
      if (res.success) {
        pendingChanges.delete(key);
      } else {
        errors++;
      }
    }

    saveBtnEl.disabled    = false;
    saveBtnEl.textContent = 'Speichern';

    if (errors > 0) {
      saveCount.textContent = `${errors} Änderung(en) konnten nicht gespeichert werden.`;
      return;
    }

    calendar.refetchEvents();
    saveBar.classList.add('is-saved');
    saveCount.textContent = 'Gespeichert ✓';
    setTimeout(() => {
      updateSaveBar();
    }, 2000);
  });

  // ── Save-Bar: Zurücksetzen ────────────────────────────────────
  resetBtnEl.addEventListener('click', () => {
    pendingChanges.forEach(({ event, origStart, origEnd }) => {
      event.setStart(origStart);
      if (origEnd) event.setEnd(origEnd);
    });
    pendingChanges.clear();
    updateSaveBar();
  });

  // ── Modal: Event anlegen ──────────────────────────────────────
  document.getElementById('tc-modal-save').addEventListener('click', async () => {
    const title    = document.getElementById('tc-modal-title').value.trim();
    const type     = typeSelect.value;
    const dateType = getDateType();

    if (!title) return showError('Bitte einen Titel eingeben.');

    let payload;

    if (dateType === 'single') {
      const date      = document.getElementById('tc-modal-date').value;
      const timeStart = document.getElementById('tc-modal-time-start').value;
      const timeEnd   = document.getElementById('tc-modal-time-end').value;
      const multiDay  = multidayCheck.checked ? 1 : 0;
      const endDate   = document.getElementById('tc-modal-end-date').value;

      if (!date) return showError('Bitte ein Datum wählen.');

      const startISO = timeStart ? `${date}T${timeStart}` : date;
      let endISO = '';
      if (multiDay && endDate) {
        endISO = timeEnd ? `${endDate}T${timeEnd}` : endDate;
      } else if (timeEnd) {
        endISO = `${date}T${timeEnd}`;
      }

      payload = { title, type, date_type: 'single', start: startISO, end: endISO, multi_day: multiDay };

      // Zusätzliche Termine sammeln
      const extraRows = extraDatesList.querySelectorAll('.tc-modal-extra-date-row');
      if (extraRows.length > 0) {
        const formData = new FormData();
        formData.append('title', title);
        formData.append('type', type);
        formData.append('date_type', 'single');
        formData.append('start', startISO);
        formData.append('end', endISO);
        formData.append('multi_day', multiDay);

        extraRows.forEach((row, i) => {
          const d  = row.querySelector('.tc-extra-date').value;
          const ts = row.querySelector('.tc-extra-time-start').value;
          const te = row.querySelector('.tc-extra-time-end').value;
          if (d) {
            formData.append(`additional_dates[${i}][date]`, d);
            formData.append(`additional_dates[${i}][time_start]`, ts);
            formData.append(`additional_dates[${i}][time_end]`, te);
          }
        });

        const btn = document.getElementById('tc-modal-save');
        btn.disabled    = true;
        btn.textContent = 'Wird angelegt…';

        const res = await postFormData('tc_create_event', formData);

        btn.disabled    = false;
        btn.textContent = 'Event anlegen & im Editor öffnen';

        if (!res.success) return showError(res.data?.message || 'Fehler beim Anlegen.');

        calendar.refetchEvents();
        closeModal();
        window.open(res.data.editUrl, '_blank');
        return;
      }

    } else {
      // recurring
      const date      = document.getElementById('tc-modal-rec-date').value;
      const timeStart = document.getElementById('tc-modal-rec-time-start').value;
      const timeEnd   = document.getElementById('tc-modal-rec-time-end').value;
      const weekday   = document.getElementById('tc-modal-weekday').value;
      const until     = document.getElementById('tc-modal-until').value;

      if (!date)  return showError('Bitte ein Datum wählen.');
      if (!until) return showError('Bitte ein Enddatum für die Wiederholung wählen.');

      const startISO = timeStart ? `${date}T${timeStart}` : date;
      const endISO   = timeEnd ? `${date}T${timeEnd}` : '';

      payload = {
        title, type,
        date_type:         'recurring',
        start:             startISO,
        end:               endISO,
        recurring_weekday: weekday,
        recurring_until:   until,
      };
    }

    const btn = document.getElementById('tc-modal-save');
    btn.disabled    = true;
    btn.textContent = 'Wird angelegt…';

    const res = await post('tc_create_event', payload);

    btn.disabled    = false;
    btn.textContent = 'Event anlegen & im Editor öffnen';

    if (!res.success) return showError(res.data?.message || 'Fehler beim Anlegen.');

    calendar.refetchEvents();
    closeModal();
    window.open(res.data.editUrl, '_blank');
  });

  document.getElementById('tc-modal-cancel').addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
});
