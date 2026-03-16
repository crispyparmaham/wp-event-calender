/* global FullCalendar, TC_Frontend */
document.addEventListener('DOMContentLoaded', () => {

  const ajaxUrl = (typeof TC_Frontend !== 'undefined' ? TC_Frontend.ajaxUrl : null)
                  || '/wp-admin/admin-ajax.php';
  const nonce   = (typeof TC_Frontend !== 'undefined' ? TC_Frontend.nonce  : null)
                  || '';

  // ── Modul-weite Hilfsfunktionen ────────────────────────────────
  const escHtml = (s) => String(s).replace(/[&<>"']/g, c => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[c]));

  const hexToRgba = (hex, alpha) => {
    let h = (hex || '#4f46e5').replace('#', '');
    if (h.length === 3) h = h.split('').map(c => c + c).join('');
    const r = parseInt(h.slice(0, 2), 16) || 79;
    const g = parseInt(h.slice(2, 4), 16) || 70;
    const b = parseInt(h.slice(4, 6), 16) || 229;
    return `rgba(${r},${g},${b},${alpha})`;
  };

  // ── Event-Übersicht rendern ─────────────────────────────────────
  const tcRenderEventOverview = (container, events, title) => {
    if (!container) return;

    const seen   = new Set();
    const unique = events
      .filter(ev => {
        const key = (ev.title || '').replace('🔁 ', '').trim().toLowerCase();
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
      })
      .sort((a, b) => {
        const ta = (a.title || '').replace('🔁 ', '').trim();
        const tb = (b.title || '').replace('🔁 ', '').trim();
        return ta.localeCompare(tb, 'de');
      });

    if (unique.length === 0) { container.innerHTML = ''; return; }

    let html = `
      <div class="tc-evlist-header">
        <hr class="tc-evlist-divider">
        <h2 class="tc-evlist-title">${escHtml(title || 'Unsere Events')}</h2>
      </div>
      <div class="tc-evlist-grid">
    `;

    unique.forEach(ev => {
      const p          = ev.extendedProps || {};
      const color      = ev.color || '#4f46e5';
      const rawTitle   = (ev.title || '').replace('🔁 ', '').trim();
      const typeLabel  = p.type === 'seminar' ? 'Seminar' : 'Gruppentraining';
      const permalink  = p.permalink ? escHtml(p.permalink) : '#';
      const intro      = p.intro_text  ? escHtml(p.intro_text)  : '';
      const location   = p.location    ? escHtml(p.location)    : '';
      const leadership = p.leadership  ? escHtml(p.leadership)  : '';
      const bgColor    = hexToRgba(color, 0.12);

      html += `
        <a class="tc-evlist-card" href="${permalink}">
          <div class="tc-evlist-stripe" style="background:${color}"></div>
          <div class="tc-evlist-card-body">
            <span class="tc-evlist-badge" style="color:${color};background:${bgColor}">${typeLabel}</span>
            <h3 class="tc-evlist-card-title">${escHtml(rawTitle)}</h3>
            ${intro ? `<p class="tc-evlist-card-desc">${intro}</p>` : ''}
            <div class="tc-evlist-card-meta">
              ${location   ? `<span>📍 ${location}</span>`   : ''}
              ${leadership ? `<span>👤 ${leadership}</span>` : ''}
            </div>
          </div>
        </a>
      `;
    });

    html += '</div>';
    container.innerHTML = html;
  };

  // ── Pro Kalender-Instanz ────────────────────────────────────────
  document.querySelectorAll('.tc-frontend-calendar').forEach(el => {
    const wrap    = el.closest('.tc-frontend-wrap');
    const uid     = el.id;
    const popover = document.getElementById(uid + '-popover');
    const popBody = popover.querySelector('.tc-popover-body');
    const popBack = document.getElementById(uid + '-backdrop');
    const loader  = document.getElementById(uid + '-loader');

    const weekOnly       = el.dataset.weekOnly       === '1';
    const showEventList  = el.dataset.showEventList  === '1';
    const eventListTitle = el.dataset.eventListTitle || 'Unsere Events';
    const overviewEl     = wrap.querySelector('.tc-event-overview');

    let activeType   = el.dataset.type || 'all';
    let cachedEvents = null;

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

    // ── Zeitbereich der Wochenansicht dynamisch anpassen ─────
    const updateVisibleTimeRange = () => {
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
    };

    // ── Wochenplan: State & Hilfsfunktionen ──────────────────
    const weekPlanWrap  = document.getElementById(uid + '-wochenplan');
    const weekPlanBody  = weekPlanWrap ? weekPlanWrap.querySelector('.tc-wochenplan-body')  : null;
    const weekPlanLabel = weekPlanWrap ? weekPlanWrap.querySelector('.tc-wochenplan-label') : null;
    const weekPlanPrev  = weekPlanWrap ? weekPlanWrap.querySelector('.tc-wochenplan-prev')  : null;
    const weekPlanNext  = weekPlanWrap ? weekPlanWrap.querySelector('.tc-wochenplan-next')  : null;
    let weekPlanOffset  = 0;
    let weekPlanRefs    = [];

    const getWeekStart = (offset) => {
      const d   = new Date();
      const day = d.getDay();
      d.setDate(d.getDate() - (day === 0 ? 6 : day - 1) + offset * 7);
      d.setHours(0, 0, 0, 0);
      return d;
    };

    const getISOWeek = (d) => {
      const tmp = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
      tmp.setUTCDate(tmp.getUTCDate() + 4 - (tmp.getUTCDay() || 7));
      const yr  = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));
      return Math.ceil(((tmp - yr) / 86400000 + 1) / 7);
    };

    const openPopoverRaw = (rawEv, jsEvent) => openPopover({
      ...rawEv,
      start: rawEv.start ? new Date(rawEv.start) : null,
      end:   rawEv.end   ? new Date(rawEv.end)   : null,
    }, jsEvent);

    const buildWeekPlan = () => {
      if (!weekPlanBody) return;

      const weekStart = getWeekStart(weekPlanOffset);
      const weekEnd   = new Date(weekStart);
      weekEnd.setDate(weekStart.getDate() + 7);

      const endDisp = new Date(weekEnd);
      endDisp.setDate(weekEnd.getDate() - 1);
      const fmtD = d => `${d.getDate()}.${d.getMonth() + 1}.${d.getFullYear()}`;
      if (weekPlanLabel) {
        weekPlanLabel.textContent = `KW ${getISOWeek(weekStart)}  ·  ${fmtD(weekStart)} – ${fmtD(endDisp)}`;
      }

      weekPlanRefs = [];
      const fmtT = (h, m) => `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;

      const weekEvents = getFiltered().filter(e => {
        const s = new Date(e.start);
        return s >= weekStart && s < weekEnd;
      });

      // Zeitslots: key "HH:MM|HH:MM" → { startMins, startStr, endStr, days[][events] }
      const slotMap = new Map();
      weekEvents.forEach(e => {
        const s        = new Date(e.start);
        const endD     = e.end ? new Date(e.end) : null;
        const startStr = fmtT(s.getHours(), s.getMinutes());
        const endStr   = endD ? fmtT(endD.getHours(), endD.getMinutes()) : '';
        const key      = `${startStr}|${endStr}`;
        const dayIdx   = (s.getDay() + 6) % 7; // Mo=0 … So=6
        if (!slotMap.has(key)) {
          slotMap.set(key, {
            startMins: s.getHours() * 60 + s.getMinutes(),
            startStr,
            endStr,
            days: Array.from({ length: 7 }, () => []),
          });
        }
        slotMap.get(key).days[dayIdx].push(e);
      });

      const slots  = [...slotMap.values()].sort((a, b) => a.startMins - b.startMins);
      const groups = [
        { label: 'Vormittag',  min:     0, max: 12 * 60, slots: [] },
        { label: 'Nachmittag', min: 12 * 60, max: 17 * 60, slots: [] },
        { label: 'Abend',      min: 17 * 60, max: 24 * 60, slots: [] },
      ];
      slots.forEach(slot => {
        const g = groups.find(g => slot.startMins >= g.min && slot.startMins < g.max);
        if (g) g.slots.push(slot);
      });

      const activeGroups = groups.filter(g => g.slots.length > 0);
      const dayNames     = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
      const days         = Array.from({ length: 7 }, (_, i) => {
        const d = new Date(weekStart);
        d.setDate(weekStart.getDate() + i);
        return d;
      });
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      let html = '<div class="tc-wochenplan-table-wrap"><table class="tc-wochenplan-table"><thead><tr>';
      html += '<th class="tc-wp-th-time"></th>';
      days.forEach((d, i) => {
        const isToday = d.getTime() === today.getTime();
        html += `<th${isToday ? ' class="tc-wp-today"' : ''}>${dayNames[i]}`;
        html += `<span class="tc-wp-th-date">${d.getDate()}.${d.getMonth() + 1}.</span></th>`;
      });
      html += '</tr></thead><tbody>';

      if (activeGroups.length === 0) {
        html += '<tr><td colspan="8" class="tc-wp-empty">Keine Events in dieser Woche.</td></tr>';
      } else {
        activeGroups.forEach(group => {
          html += `<tr class="tc-wp-group-row"><td colspan="8" class="tc-wp-group-label">${group.label}</td></tr>`;
          group.slots.forEach(slot => {
            html += '<tr class="tc-wp-slot-row">';
            html += '<td class="tc-wp-time">';
            html += `<span class="tc-wp-time-start">${slot.startStr}</span>`;
            if (slot.endStr) html += `<span class="tc-wp-time-end">${slot.endStr}</span>`;
            html += '</td>';
            slot.days.forEach(dayEvs => {
              if (dayEvs.length === 0) {
                html += '<td class="tc-wp-cell tc-wp-cell--empty"></td>';
              } else {
                html += '<td class="tc-wp-cell">';
                dayEvs.forEach(ev => {
                  const idx   = weekPlanRefs.push(ev) - 1;
                  const color = ev.color || '#4f46e5';
                  const bg    = hexToRgba(color, 0.13);
                  const title = escHtml((ev.title || '').replace('🔁 ', ''));
                  const p     = ev.extendedProps || {};
                  const sub   = p.leadership ? escHtml(p.leadership) : '';
                  html += `<button class="tc-wp-event" data-ev-idx="${idx}" type="button"`;
                  html += ` style="background:${bg};border-left:3px solid ${color}">`;
                  html += `<strong class="tc-wp-event-title">${title}</strong>`;
                  if (sub) html += `<span class="tc-wp-event-sub">${sub}</span>`;
                  html += '</button>';
                });
                html += '</td>';
              }
            });
            html += '</tr>';
          });
        });
      }

      html += '</tbody></table></div>';

      // ── Mobile-Layout: Events nach Tag gruppiert ──────────────
      const dayFullNames = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
      const monthNames   = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
      let mobileHtml     = '<div class="tc-wochenplan-mobile">';
      let mobileHasEvs   = false;

      days.forEach((d, i) => {
        const dayStart = new Date(d);
        const dayEnd   = new Date(d);
        dayEnd.setDate(dayEnd.getDate() + 1);

        const dayEvs = weekEvents
          .filter(e => { const s = new Date(e.start); return s >= dayStart && s < dayEnd; })
          .sort((a, b) => new Date(a.start) - new Date(b.start));

        if (dayEvs.length === 0) return;
        mobileHasEvs = true;

        const isToday = d.getTime() === today.getTime();
        mobileHtml += `<div class="tc-wpm-day${isToday ? ' tc-wpm-day--today' : ''}">`;
        mobileHtml += `<div class="tc-wpm-day-header">${dayFullNames[i]}, ${d.getDate()}. ${monthNames[d.getMonth()]}</div>`;
        mobileHtml += '<div class="tc-wpm-events">';

        dayEvs.forEach(ev => {
          const refIdx  = weekPlanRefs.indexOf(ev);
          const idx     = refIdx !== -1 ? refIdx : weekPlanRefs.push(ev) - 1;
          const color   = ev.color || '#4f46e5';
          const bg      = hexToRgba(color, 0.13);
          const title   = escHtml((ev.title || '').replace('🔁 ', ''));
          const p       = ev.extendedProps || {};
          const sub     = p.leadership ? escHtml(p.leadership) : '';
          const s       = new Date(ev.start);
          const endD    = ev.end ? new Date(ev.end) : null;
          const timeStr = fmtT(s.getHours(), s.getMinutes())
                        + (endD ? ' – ' + fmtT(endD.getHours(), endD.getMinutes()) : '');

          mobileHtml += `<button class="tc-wp-event" data-ev-idx="${idx}" type="button"`;
          mobileHtml += ` style="background:${bg};border-left:3px solid ${color}">`;
          mobileHtml += `<span class="tc-wpm-time">${timeStr}</span>`;
          mobileHtml += `<strong class="tc-wp-event-title">${title}</strong>`;
          if (sub) mobileHtml += `<span class="tc-wp-event-sub">${sub}</span>`;
          mobileHtml += '</button>';
        });

        mobileHtml += '</div></div>';
      });

      if (!mobileHasEvs) {
        mobileHtml += '<p class="tc-wp-empty" style="padding:24px 16px;">Keine Events in dieser Woche.</p>';
      }
      mobileHtml += '</div>';

      weekPlanBody.innerHTML = html + mobileHtml;

      weekPlanBody.querySelectorAll('.tc-wp-event').forEach(btn => {
        btn.addEventListener('click', jsEvent => {
          openPopoverRaw(weekPlanRefs[+btn.dataset.evIdx], jsEvent);
        });
      });
    };

    // ── Responsive View-Logik ─────────────────────────────────
    const getResponsiveView = () => {
      if (weekOnly) return 'timeGridWeek';
      return isMobile() ? 'listMonth' : (el.dataset.view || 'dayGridMonth');
    };

    const getResponsiveToolbar = () => {
      if (weekOnly) return { left: '', center: 'title', right: '' };
      return isMobile()
        ? { left: 'prev,next', center: 'title', right: 'listMonth,dayGridMonth' }
        : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' };
    };

    // ── FullCalendar initialisieren (noch ohne Events) ────────
    const calendar = new FullCalendar.Calendar(el, {
      initialView:   getResponsiveView(),
      initialDate:   weekOnly ? new Date() : undefined,
      locale:        'de',
      height:        'auto',
      firstDay:      1,
      editable:      false,
      navLinks:      !weekOnly,
      headerToolbar: getResponsiveToolbar(),
      buttonText: {
        today: 'Heute',
        month: 'Monat',
        week:  'Woche',
        list:  'Liste',
      },
      noEventsText:  'Keine Events in diesem Zeitraum.',
      slotDuration:  '00:30:00',

      datesSet() { updateVisibleTimeRange(); },
      eventsSet()  { updateVisibleTimeRange(); },

      windowResize() {
        if (weekOnly) return;
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

    // ── Week-Only: Wochenplan-Modus ───────────────────────────
    if (weekOnly) {
      el.style.display = 'none';
      const viewToggle = wrap.querySelector('.tc-view-toggle');
      if (viewToggle) viewToggle.style.display = 'none';

      if (weekPlanWrap) {
        weekPlanWrap.style.display = '';
        if (weekPlanPrev) weekPlanPrev.style.display = 'none';
        if (weekPlanNext) weekPlanNext.style.display = 'none';
      }

      const weekLabel = document.createElement('div');
      weekLabel.className = 'tc-week-label';
      weekLabel.textContent = 'Aktuelle Woche';
      const anchor = weekPlanWrap || el;
      anchor.parentNode.insertBefore(weekLabel, anchor);
    }

    // ── Events einmalig per AJAX laden, dann statisch setzen ──
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

      if (weekOnly) {
        buildWeekPlan();
      } else {
        calendar.addEventSource(getFiltered());
        updateVisibleTimeRange();
      }

      if (showEventList) tcRenderEventOverview(overviewEl, getFiltered(), eventListTitle);
    })();

    // ── Filter-Tabs: nur Cache umsortieren, kein AJAX ─────────
    wrap.querySelectorAll('.tc-filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.dataset.type === activeType) return;

        wrap.querySelectorAll('.tc-filter-btn').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        activeType = btn.dataset.type;

        if (weekOnly) {
          buildWeekPlan();
        } else {
          calendar.getEventSources().forEach(s => s.remove());
          calendar.addEventSource(getFiltered());
          updateVisibleTimeRange();
          if (weekPlanWrap && weekPlanWrap.style.display !== 'none') buildWeekPlan();
        }

        if (showEventList) tcRenderEventOverview(overviewEl, getFiltered(), eventListTitle);
      });
    });

    // ── View-Toggle (Kalender ↔ Wochenplan) ──────────────────
    wrap.querySelectorAll('.tc-view-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.classList.contains('is-active')) return;
        wrap.querySelectorAll('.tc-view-btn').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');

        if (btn.dataset.tcView === 'wochenplan') {
          const calDate = calendar.getDate();
          const calDay  = calDate.getDay();
          const calMon  = new Date(calDate);
          calMon.setDate(calDate.getDate() - (calDay === 0 ? 6 : calDay - 1));
          calMon.setHours(0, 0, 0, 0);
          weekPlanOffset = Math.round((calMon - getWeekStart(0)) / (7 * 86400000));
          el.style.display = 'none';
          if (weekPlanWrap) { weekPlanWrap.style.display = ''; buildWeekPlan(); }
        } else {
          el.style.display = '';
          if (weekPlanWrap) weekPlanWrap.style.display = 'none';
        }
      });
    });

    if (weekPlanPrev) weekPlanPrev.addEventListener('click', () => { weekPlanOffset--; buildWeekPlan(); });
    if (weekPlanNext) weekPlanNext.addEventListener('click', () => { weekPlanOffset++; buildWeekPlan(); });

    // ── Popover schließen ─────────────────────────────────────
    popover.querySelector('.tc-popover-close').addEventListener('click', closePopover);
    popBack.addEventListener('click', closePopover);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closePopover(); });
  });
});
