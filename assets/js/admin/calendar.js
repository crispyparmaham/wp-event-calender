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
  const recurFields = document.getElementById('tc-recurring-fields');
  const recurCheck  = document.getElementById('tc-modal-recurring');
  const saveBar     = document.getElementById('tc-save-bar');
  const saveCount   = document.getElementById('tc-save-count');
  const saveBtnEl   = document.getElementById('tc-save-bar-save');
  const resetBtnEl  = document.getElementById('tc-save-bar-reset');

  // ── Ausstehende Änderungen (Drag & Drop / Resize) ─────────────
  // key: "<eventId>:<dateIndex|''>"
  // value: { event, origStart, origEnd }
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
      // Originalposition nur beim ersten Verschieben merken
      pendingChanges.set(key, {
        event,
        origStart: oldEvent.startStr,
        origEnd:   oldEvent.endStr || '',
      });
    } else {
      // Weiteres Verschieben desselben Events: nur Referenz aktualisieren
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

  const showError = (msg) => {
    errBox.textContent   = msg;
    errBox.style.display = 'block';
  };

  const closeModal = () => {
    modal.style.display       = 'none';
    backdrop.style.display    = 'none';
    errBox.style.display      = 'none';
    recurFields.style.display = 'none';
    recurCheck.checked        = false;
    document.getElementById('tc-modal-title').value   = '';
    document.getElementById('tc-modal-start').value   = '';
    document.getElementById('tc-modal-end').value     = '';
    document.getElementById('tc-modal-weekday').value = '1';
    document.getElementById('tc-modal-until').value   = '';
  };

  const toDatetimeLocal = (iso) => iso ? iso.slice(0, 16) : '';

  // Wochentag aus einem Datums-String lesen (0=So … 6=Sa)
  const getWeekday = (isoStr) => new Date(isoStr).getDay().toString();

  // ── Wiederkehrend-Toggle im Modal ──────────────────────────────
  recurCheck.addEventListener('change', () => {
    recurFields.style.display = recurCheck.checked ? 'block' : 'none';
  });

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
      const p     = event.extendedProps;
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
      const start = dateStr.length === 10
        ? dateStr + 'T08:00'
        : dateStr.slice(0, 16);

      document.getElementById('tc-modal-start').value   = toDatetimeLocal(start);
      document.getElementById('tc-modal-weekday').value = getWeekday(start);
      modal.style.display    = 'flex';
      backdrop.style.display = 'block';
      document.getElementById('tc-modal-title').focus();
    },

    // ── Klick auf Event → WP-Editor ───────────────────────────
    eventClick({ event }) {
      const url = event.extendedProps.editUrl || event.url;
      if (url) window.open(url, '_blank');
    },

    // ── Drag & Drop → Änderung vormerken, nicht sofort speichern ─
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
      const dateIndex = ev.extendedProps.dateIndex;
      const payload   = { id: ev.id, start: ev.startStr, end: ev.endStr || '' };
      if (dateIndex !== null && dateIndex !== undefined) payload.date_index = dateIndex;

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

    // Admin-Kalender neu laden damit die Server-Daten bestätigt sind
    calendar.refetchEvents();

    // Kurz "Gespeichert"-Zustand zeigen, dann Bar ausblenden
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

  // ── Modal: Speichern ──────────────────────────────────────────
  document.getElementById('tc-modal-save').addEventListener('click', async () => {
    const title       = document.getElementById('tc-modal-title').value.trim();
    const start       = document.getElementById('tc-modal-start').value;
    const end         = document.getElementById('tc-modal-end').value;
    const type        = document.getElementById('tc-modal-type').value;
    const isRecurring = recurCheck.checked ? 1 : 0;
    const weekday     = document.getElementById('tc-modal-weekday').value;
    const until       = document.getElementById('tc-modal-until').value;

    if (!title)                          return showError('Bitte einen Titel eingeben.');
    if (!start)                          return showError('Bitte ein Startdatum wählen.');
    if (isRecurring && !until)           return showError('Bitte ein Enddatum für die Wiederholung wählen.');

    const btn = document.getElementById('tc-modal-save');
    btn.disabled    = true;
    btn.textContent = 'Wird angelegt…';

    const res = await post('tc_create_event', {
      title,
      start,
      end,
      type,
      is_recurring:      isRecurring,
      recurring_weekday: weekday,
      recurring_until:   until,
    });

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
