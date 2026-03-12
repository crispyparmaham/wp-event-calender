/* global FullCalendar, TC */
document.addEventListener('DOMContentLoaded', () => {

  const el          = document.getElementById('tc-calendar');
  const modal       = document.getElementById('tc-modal');
  const backdrop    = document.getElementById('tc-modal-backdrop');
  const errBox      = document.getElementById('tc-modal-error');
  const recurFields = document.getElementById('tc-recurring-fields');
  const recurCheck  = document.getElementById('tc-modal-recurring');

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

    editable:  true,
    droppable: true,

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

    // ── Drag & Drop (nur editierbare Events) ──────────────────
    eventDrop: async ({ event, revert }) => {
      const dateIndex = event.extendedProps.dateIndex;
      const payload   = {
        id:    event.id,
        start: event.startStr,
        end:   event.endStr || '',
      };
      if (dateIndex !== null && dateIndex !== undefined) payload.date_index = dateIndex;
      const res = await post('tc_update_event', payload);
      if (!res.success) {
        revert();
        alert('Fehler beim Speichern: ' + (res.data?.message || ''));
      }
    },

    // ── Resize ────────────────────────────────────────────────
    eventResize: async ({ event, revert }) => {
      const dateIndex = event.extendedProps.dateIndex;
      const payload   = {
        id:    event.id,
        start: event.startStr,
        end:   event.endStr || '',
      };
      if (dateIndex !== null && dateIndex !== undefined) payload.date_index = dateIndex;
      const res = await post('tc_update_event', payload);
      if (!res.success) {
        revert();
        alert('Fehler beim Speichern: ' + (res.data?.message || ''));
      }
    },
  });

  calendar.render();

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
