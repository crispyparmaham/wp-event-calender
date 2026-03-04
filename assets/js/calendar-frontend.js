/* global FullCalendar, TC_Frontend */
document.addEventListener('DOMContentLoaded', () => {

  const ajaxUrl = (typeof TC_Frontend !== 'undefined' ? TC_Frontend.ajaxUrl : null)
                  || '/wp-admin/admin-ajax.php';
  const nonce   = (typeof TC_Frontend !== 'undefined' ? TC_Frontend.nonce  : null)
                  || '';

  document.querySelectorAll('.tc-frontend-calendar').forEach(el => {
    const wrap    = el.closest('.tc-frontend-wrap');
    const uid     = el.id;
    const popover = document.getElementById(uid + '-popover');
    const popBody = popover.querySelector('.tc-popover-body');
    const popBack = document.getElementById(uid + '-backdrop');
    const loader  = document.getElementById(uid + '-loader');

    let activeType   = el.dataset.type || 'all';
    let cachedEvents = null; // alle Events aus AJAX, einmalig geladen

    // ── Hilfsfunktionen ──────────────────────────────────────
    const isMobile = () => window.innerWidth < 768;

    const showLoader = () => { if (loader) loader.style.display = 'flex'; };
    const hideLoader = () => { if (loader) loader.style.display = 'none'; };

    const getFiltered = () => {
      if (!cachedEvents) return [];
      return activeType === 'all'
        ? cachedEvents
        : cachedEvents.filter(e => e.type === activeType);
    };

    const closePopover = () => {
      popover.style.display = 'none';
      popBack.style.display = 'none';
    };

    const formatDate = (d) => d.toLocaleDateString('de-DE', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });

    const escHtml = (s) => String(s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));

    const openPopover = (event, jsEvent) => {
      const p     = event.extendedProps;
      const start = event.start ? formatDate(event.start) : '–';
      const end   = event.end   ? formatDate(event.end)   : null;
      const label = p.type === 'seminar' ? 'Seminar' : 'Gruppentraining';

      popBody.innerHTML = `
        <div class="tc-popover-type tc-popover-type--${p.type || 'training'}">
          ${label}${p.isRecurring ? ' &nbsp;🔁' : ''}
        </div>
        <h3 class="tc-popover-title">${escHtml(event.title.replace('🔁 ', ''))}</h3>
        <ul class="tc-popover-meta">
          <li><span>📅</span>${start}${end ? ' – ' + end : ''}</li>
          ${p.leadership   ? `<li><span>👤</span>${escHtml(p.leadership)}</li>`      : ''}
          ${p.location     ? `<li><span>📍</span>${escHtml(p.location)}</li>`        : ''}
          ${p.participants ? `<li><span>👥</span>${escHtml(p.participants)}</li>`     : ''}
          ${p.price        ? `<li><span>💶</span>${escHtml(String(p.price))} €</li>` : ''}
        </ul>
        ${p.permalink
          ? `<a class="tc-popover-link" href="${escHtml(p.permalink)}">
               Mehr erfahren &rarr;
             </a>`
          : ''}
      `;

      popover.style.display = 'block';
      popBack.style.display = 'block';

      const rect = popover.getBoundingClientRect();
      const vpW  = window.innerWidth;
      const vpH  = window.innerHeight;
      let left   = jsEvent.clientX + 14;
      let top    = jsEvent.clientY + 14;

      if (left + rect.width  > vpW - 16) left = jsEvent.clientX - rect.width  - 14;
      if (top  + rect.height > vpH - 16) top  = jsEvent.clientY - rect.height - 14;

      if (isMobile()) {
        popover.style.left      = '';
        popover.style.top       = '';
        popover.style.transform = '';
      } else {
        popover.style.left      = left + 'px';
        popover.style.top       = (top + window.scrollY) + 'px';
        popover.style.transform = '';
      }
    };

    // ── Responsive View-Logik ─────────────────────────────────
    const getResponsiveView = () => isMobile() ? 'listMonth' : (el.dataset.view || 'dayGridMonth');

    const getResponsiveToolbar = () => isMobile()
      ? { left: 'prev,next', center: 'title', right: 'listMonth,dayGridMonth' }
      : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' };

    // ── FullCalendar initialisieren (noch ohne Events) ────────
    const calendar = new FullCalendar.Calendar(el, {
      initialView:  getResponsiveView(),
      locale:       'de',
      height:       'auto',
      firstDay:     1,
      editable:     false,
      headerToolbar: getResponsiveToolbar(),
      buttonText: {
        today: 'Heute',
        month: 'Monat',
        week:  'Woche',
        list:  'Liste',
      },
      noEventsText: 'Keine Events in diesem Zeitraum.',

      windowResize() {
        calendar.setOption('headerToolbar', getResponsiveToolbar());
        const targetView = getResponsiveView();
        if (calendar.view.type !== targetView) calendar.changeView(targetView);
      },

      eventDidMount({ event, el: evEl }) {
        if (!event.startEditable) evEl.style.opacity = '0.8';
      },

      eventClick({ event, jsEvent }) {
        jsEvent.preventDefault();
        openPopover(event, jsEvent);
      },
    });

    calendar.render();

    // ── Events einmalig per AJAX laden, dann statisch setzen ──
    // Kein events-Callback → FullCalendar fragt nie selbst nach.
    // Filter & Ansichtswechsel arbeiten nur gegen den lokalen Cache.
    (async () => {
      showLoader();
      try {
        const body = new URLSearchParams({ action: 'tc_get_events', nonce });
        const res  = await fetch(ajaxUrl, { method: 'POST', body });
        const json = await res.json();
        cachedEvents = json.success ? json.data : [];
      } catch (e) {
        cachedEvents = [];
      }
      hideLoader();

      // Einmalig die gefilterten Events als einzige Source setzen
      calendar.addEventSource(getFiltered());
    })();

    // ── Filter-Tabs: nur Cache umsortieren, kein AJAX ─────────
    wrap.querySelectorAll('.tc-filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.dataset.type === activeType) return;

        wrap.querySelectorAll('.tc-filter-btn').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        activeType = btn.dataset.type;

        // Alle vorhandenen Sources entfernen und neu aus Cache setzen
        calendar.getEventSources().forEach(s => s.remove());
        calendar.addEventSource(getFiltered());
      });
    });

    // ── Popover schließen ─────────────────────────────────────
    popover.querySelector('.tc-popover-close').addEventListener('click', closePopover);
    popBack.addEventListener('click', closePopover);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closePopover(); });
  });
});
