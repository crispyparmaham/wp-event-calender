/* global FullCalendar, TC_Frontend */
;(function () {
'use strict';

document.addEventListener('DOMContentLoaded', () => {

  /* ═══════════════════════════════════════════════════════════════
   * MODULE 1: Constants & Settings
   * ═══════════════════════════════════════════════════════════════ */

  // Time slot boundaries for the week plan (minutes from midnight)
  const SLOT_VORMITTAG_START  =     0; //  0:00
  const SLOT_NACHMITTAG_START = 12 * 60; // 12:00
  const SLOT_ABEND_START      = 17 * 60; // 17:00
  const SLOT_MAX              = 24 * 60; // 24:00

  const DEFAULT_SLOT_MIN = '08:00:00';
  const DEFAULT_SLOT_MAX = '20:00:00';

  const TF                     = typeof TC_Frontend !== 'undefined' ? TC_Frontend : {};
  const ajaxUrl                = TF.ajaxUrl           || '/wp-admin/admin-ajax.php';
  const nonce                  = TF.nonce              || '';
  const globalMobileView       = TF.mobileView         || 'optimized';
  const globalDefaultView      = TF.defaultView         || 'timeGridWeek';
  const globalWeekStart        = TF.weekStartsOn        || 'monday';
  const globalTimeColumnLabel  = TF.timeColumnLabel      || 'hours';
  const globalEventTimeDisplay = TF.eventTimeDisplay     || 'none';
  const globalShowWeekNumber   = TF.showWeekNumber      || false;

  /* ═══════════════════════════════════════════════════════════════
   * MODULE 2: Utilities & Helpers
   *
   * Shared functions used by Event Overview, Week Plan, and
   * Calendar Instance modules. No DOM references here.
   * ═══════════════════════════════════════════════════════════════ */

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

  // ── Hilfsfunktionen für Terminanzeige ──────────────────────────
  const WEEKDAY_LABELS = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
  const DAY_SHORT      = ['So','Mo','Di','Mi','Do','Fr','Sa'];
  const MONTH_LONG     = ['Januar','Februar','März','April','Mai','Juni',
                          'Juli','August','September','Oktober','November','Dezember'];

  const getWeekdayLabel = (dayInt) => WEEKDAY_LABELS[+dayInt] || '';

  const formatDateLine = (dateStr, timeStart, timeEnd) => {
    if (!dateStr) return '';
    const d     = new Date(dateStr + 'T00:00:00');
    const dow   = DAY_SHORT[d.getDay()];
    const day   = d.getDate();
    const month = MONTH_LONG[d.getMonth()];
    let str = `${dow}, ${day}. ${month}`;
    if (timeStart) {
      str += ` · ${timeStart}`;
      str += timeEnd ? ` – ${timeEnd} Uhr` : ' Uhr';
    }
    return str;
  };

  const buildDatesHtml = (p, cardIdx) => {
    if (p.isRecurring && p.recurringWeekday !== null && p.recurringWeekday !== undefined) {
      const dayName = getWeekdayLabel(p.recurringWeekday);
      let timeStr = '';
      if (p.startTime) {
        timeStr = ` · ${escHtml(p.startTime)}`;
        timeStr += p.endTime ? ` – ${escHtml(p.endTime)} Uhr` : ' Uhr';
      }
      return `<div class="tc-evlist-dates tc-evlist-dates--recurring">
        <span class="tc-evlist-recurring-badge">🔁 Jeden ${escHtml(dayName)}${timeStr}</span>
      </div>`;
    }

    const dates = (p.eventDates || []).slice();
    if (dates.length === 0) return '';

    const MAX_VISIBLE = 3;
    const extraCount  = Math.max(0, dates.length - MAX_VISIBLE);

    let itemsHtml = '';
    dates.forEach((d, i) => {
      const line    = formatDateLine(d.date_start, d.time_start, d.time_end);
      const isExtra = i >= MAX_VISIBLE;
      const extraCls = isExtra ? ' tc-date-extra' : '';
      itemsHtml += `<span class="tc-evlist-date-row${extraCls}">${escHtml(line)}</span>`;
      if (d.title) {
        itemsHtml += `<span class="tc-evlist-date-title${extraCls}">${escHtml(d.title)}</span>`;
      }
    });

    const moreBtn = extraCount > 0
      ? `<button type="button" class="tc-dates-more" data-more="${extraCount}">+ ${extraCount} weitere</button>`
      : '';

    return `<div class="tc-evlist-dates" data-card-idx="${cardIdx}">
      <div class="tc-evlist-date-inner">
        <span class="tc-dates-icon">📅</span>
        <div class="tc-dates-items">${itemsHtml}</div>
      </div>
      ${moreBtn}
    </div>`;
  };

  /* ═══════════════════════════════════════════════════════════════
   * MODULE 3: Event Overview (Kartenliste)
   * ═══════════════════════════════════════════════════════════════ */
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

    unique.forEach((ev, idx) => {
      const p          = ev.extendedProps || {};
      const color      = ev.color || '#4f46e5';
      const rawTitle   = (ev.title || '').replace('🔁 ', '').trim();
      const typeLabel  = p.categoryName || (p.type ? p.type.charAt(0).toUpperCase() + p.type.slice(1) : '');
      const permalink  = p.permalink ? escHtml(p.permalink) : '#';
      const location   = p.location    ? escHtml(p.location)    : '';
      const leadership = p.leadership  ? escHtml(p.leadership)  : '';
      const bgColor    = hexToRgba(color, 0.12);
      const datesHtml  = buildDatesHtml(p, idx);

      html += `
        <a class="tc-evlist-card" href="${permalink}">
          <div class="tc-evlist-stripe" style="background:${color}"></div>
          <div class="tc-evlist-card-body">
            <span class="tc-evlist-badge" style="color:${color};background:${bgColor}">${typeLabel}</span>
            <h3 class="tc-evlist-card-title">${escHtml(rawTitle)}</h3>
            ${datesHtml}
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

    container.querySelectorAll('.tc-dates-more').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        const datesEl  = btn.closest('.tc-evlist-dates');
        const expanded = datesEl.classList.toggle('is-expanded');
        btn.textContent = expanded
          ? 'Weniger anzeigen'
          : `+ ${btn.dataset.more} weitere`;
      });
    });
  };

  /* ═══════════════════════════════════════════════════════════════
   * MODULE 4: Calendar Instance (pro Element)
   * ═══════════════════════════════════════════════════════════════ */
  document.querySelectorAll('.tc-frontend-calendar').forEach(el => {
    const wrap    = el.closest('.tc-frontend-wrap');
    const uid     = el.id;
    const popover = document.getElementById(uid + '-popover');
    const popBody = popover.querySelector('.tc-popover-body');
    const popBack = document.getElementById(uid + '-backdrop');
    const loader     = document.getElementById(uid + '-loader');

    const weekOnly       = el.dataset.weekOnly       === '1';
    const showEventList  = el.dataset.showEventList  === '1';
    const eventListTitle = el.dataset.eventListTitle || 'Unsere Events';
    const lockedType     = el.dataset.lockedType     || '';
    const mobileView     = el.dataset.mobileView || globalMobileView;
    const forceDesktop   = mobileView === 'desktop';
    const mobileScaled   = mobileView === 'scaled' || wrap.dataset.mobileScaled === '1';
    const mobileSlider   = el.dataset.mobileSlider === '1' || mobileView === 'slider';
    const overviewEl     = wrap.querySelector('.tc-event-overview');
    const colLabel       = wrap.dataset.colLabel  || globalTimeColumnLabel;
    const eventTime      = wrap.dataset.eventTime || globalEventTimeDisplay;
    const showEventTime  = eventTime !== 'none';

    let activeType   = el.dataset.type || 'all';
    let cachedEvents = null;

    // ── Hilfsfunktionen ──────────────────────────────────────
    const isMobile = () => window.innerWidth <= 768;

    const showLoader = () => { if (loader) loader.style.display = 'flex'; };
    const hideLoader = () => { if (loader) loader.style.display = 'none'; };

    const getFiltered = () => {
      if (!cachedEvents) return [];
      const filterBy = lockedType || (activeType !== 'all' ? activeType : '');
      return filterBy
        ? cachedEvents.filter(e => e.type === filterBy)
        : cachedEvents;
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
      const label = p.categoryName || (p.type ? p.type.charAt(0).toUpperCase() + p.type.slice(1) : 'Event');

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
      const vt = calendar.view.type;
      if (vt !== 'timeGridWeek') return;

      // Bei 'groups' ist slotMinTime/slotMaxTime fest — nicht überschreiben
      if (globalTimeColumnLabel === 'groups') return;

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
        minT = DEFAULT_SLOT_MIN;
        maxT = DEFAULT_SLOT_MAX;
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

    /* ─── MODULE 4a: Week Plan ──────────────────────────────── */
    const weekPlanWrap  = document.getElementById(uid + '-week-plan');
    const weekPlanBody  = weekPlanWrap ? weekPlanWrap.querySelector('.tc-week-plan-body')  : null;
    const weekPlanLabel = weekPlanWrap ? weekPlanWrap.querySelector('.tc-week-plan-label') : null;
    const weekPlanPrev  = weekPlanWrap ? weekPlanWrap.querySelector('.tc-week-plan-prev')  : null;
    const weekPlanNext  = weekPlanWrap ? weekPlanWrap.querySelector('.tc-week-plan-next')  : null;
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
        if (globalShowWeekNumber) {
          weekPlanLabel.textContent = `KW ${getISOWeek(weekStart)}  ·  ${fmtD(weekStart)} – ${fmtD(endDisp)}`;
          weekPlanLabel.style.display = '';
        } else {
          weekPlanLabel.textContent = '';
          weekPlanLabel.style.display = 'none';
        }
      }

      weekPlanRefs = [];
      const fmtT = (h, m) => `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;

      const weekEvents = getFiltered().filter(e => {
        const s = new Date(e.start);
        return s >= weekStart && s < weekEnd;
      });

      const slotMap = new Map();
      weekEvents.forEach(e => {
        const s        = new Date(e.start);
        const endD     = e.end ? new Date(e.end) : null;
        const startStr = fmtT(s.getHours(), s.getMinutes());
        const endStr   = endD ? fmtT(endD.getHours(), endD.getMinutes()) : '';
        const key      = `${startStr}|${endStr}`;
        const dayIdx   = (s.getDay() + 6) % 7;
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
        { label: 'Vormittag',  min: SLOT_VORMITTAG_START,  max: SLOT_NACHMITTAG_START, slots: [] },
        { label: 'Nachmittag', min: SLOT_NACHMITTAG_START, max: SLOT_ABEND_START,      slots: [] },
        { label: 'Abend',      min: SLOT_ABEND_START,      max: SLOT_MAX,              slots: [] },
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

      let html = `<div class="tc-week-plan-table-wrap"><table class="tc-week-plan-table"><thead><tr>`;
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
          // 'both': full-width group header row above each group's slots
          if (colLabel === 'both') {
            html += '<tr class="tc-wp-group-header-row">';
            html += `<td class="tc-wp-group-header" colspan="8">${group.label}</td>`;
            html += '</tr>';
          }

          group.slots.forEach((slot, slotIdx) => {
            html += '<tr class="tc-wp-slot-row">';

            if (colLabel === 'groups') {
              // Left column: group name with rowspan (first slot only)
              if (slotIdx === 0) {
                html += `<td class="tc-wp-time tc-wp-time--group" rowspan="${group.slots.length}">`;
                html += `<span class="tc-wp-group-label-cell">${group.label}</span>`;
                html += '</td>';
              }
            } else {
              // 'hours' and 'both': left column shows only the time range
              html += '<td class="tc-wp-time">';
              html += `<span class="tc-wp-time-start">${slot.startStr}</span>`;
              if (slot.endStr) html += `<span class="tc-wp-time-end">${slot.endStr}</span>`;
              html += '</td>';
            }

            slot.days.forEach(dayEvs => {
              if (dayEvs.length === 0) {
                html += '<td class="tc-wp-cell tc-wp-cell--empty"></td>';
              } else {
                html += '<td class="tc-wp-cell">';
                dayEvs.forEach(ev => {
                  const idx    = weekPlanRefs.push(ev) - 1;
                  const color  = ev.color || '#4f46e5';
                  const bg     = hexToRgba(color, 0.13);
                  const p      = ev.extendedProps || {};
                  const title  = escHtml((p.dateTitle || ev.title || '').replace('🔁 ', ''));
                  const sub    = p.leadership ? escHtml(p.leadership) : '';
                  const isPast = p.isPast === true;
                  html += `<button class="tc-wp-event${isPast ? ' tc-wp-event--past' : ''}" data-ev-idx="${idx}" type="button"`;
                  html += ` style="background:${bg};border-left:3px solid ${color}">`;
                  if (showEventTime) {
                    const tStr = slot.startStr + (slot.endStr ? ` – ${slot.endStr}` : '');
                    html += `<span class="tc-wp-event-time-above">${escHtml(tStr)}</span>`;
                  }
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
      let mobileHtml     = '<div class="tc-week-plan-mobile">';
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
        mobileHtml += `<div class="tc-wp-mobile-day${isToday ? ' tc-wp-mobile-day--today' : ''}">`;
        mobileHtml += `<div class="tc-wp-mobile-day-header">${dayFullNames[i]}, ${d.getDate()}. ${monthNames[d.getMonth()]}</div>`;
        mobileHtml += '<div class="tc-wp-mobile-events">';

        dayEvs.forEach(ev => {
          const refIdx  = weekPlanRefs.indexOf(ev);
          const idx     = refIdx !== -1 ? refIdx : weekPlanRefs.push(ev) - 1;
          const color   = ev.color || '#4f46e5';
          const bg      = hexToRgba(color, 0.13);
          const p       = ev.extendedProps || {};
          const title   = escHtml((p.dateTitle || ev.title || '').replace('🔁 ', ''));
          const sub     = p.leadership ? escHtml(p.leadership) : '';
          const isPast  = p.isPast === true;
          const s       = new Date(ev.start);
          const endD    = ev.end ? new Date(ev.end) : null;
          const timeStr = fmtT(s.getHours(), s.getMinutes())
                        + (endD ? ' – ' + fmtT(endD.getHours(), endD.getMinutes()) : '');

          mobileHtml += `<button class="tc-wp-event${isPast ? ' tc-wp-event--past' : ''}" data-ev-idx="${idx}" type="button"`;
          mobileHtml += ` style="background:${bg};border-left:3px solid ${color}">`;
          mobileHtml += `<span class="tc-wp-mobile-time">${timeStr}</span>`;
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

      // Nach DOM-Insert: scroll-Container auf Montag (= ganz links) zurücksetzen.
      // Nötig weil Safari/Chrome auf Mobile automatisch zu .tc-wp-today scrollen.
      const tableWrap = weekPlanBody.querySelector('.tc-week-plan-table-wrap');
      if (tableWrap) {
        tableWrap.scrollLeft = 0;
        requestAnimationFrame(() => { tableWrap.scrollLeft = 0; });
      }

      weekPlanBody.querySelectorAll('.tc-wp-event').forEach(btn => {
        btn.addEventListener('click', jsEvent => {
          openPopoverRaw(weekPlanRefs[+btn.dataset.evIdx], jsEvent);
        });
      });
    };

    /* ─── MODULE 4b: Mobile Logic ───────────────────────────── */
    const useMobileSlider = () => mobileSlider && !forceDesktop && isMobile();

    const updateSliderClass = () => {
      wrap.classList.toggle('tc-mobile-slider', useMobileSlider());
    };

    // Scroll zum Montag der aktuellen Woche (Mobile Slider) — mit Retry
    const scrollToMonday = () => {
      if (!useMobileSlider()) return;
      let attempts = 0;
      const tryScroll = () => {
        const harness = el.querySelector('.fc-view-harness');
        if (!harness) {
          if (attempts++ < 12) setTimeout(tryScroll, 100);
          return;
        }
        const headers = el.querySelectorAll('.fc-col-header-cell[data-date]');
        const monday = Array.from(headers).find(h => {
          const d = new Date(h.dataset.date + 'T00:00:00');
          return d.getDay() === 1;
        });
        if (monday) {
          harness.scrollLeft = monday.offsetLeft;
          // Sync header scroller
          const headerScroller = el.querySelector('.fc-scrollgrid-section-header .fc-scroller');
          if (headerScroller) headerScroller.scrollLeft = monday.offsetLeft;
        } else if (attempts++ < 12) {
          setTimeout(tryScroll, 100);
        }
      };
      setTimeout(tryScroll, 150);
    };

    const initSliderScrollSync = () => {
      if (!useMobileSlider()) return;
      const bodyScroller  = el.querySelector('.fc-scroller-liquid-absolute');
      const headerScroller = el.querySelector('.fc-scrollgrid-section-header .fc-scroller');
      if (bodyScroller && headerScroller && !bodyScroller._tcSyncH) {
        bodyScroller._tcSyncH = true;
        bodyScroller.addEventListener('scroll', () => {
          headerScroller.scrollLeft = bodyScroller.scrollLeft;
        }, { passive: true });
      }
    };

    // Week-Plan-Tabelle: zum heutigen Tag scrollen (Mobile Slider)
    const scrollWeekPlanToToday = () => {
      if (!mobileSlider || !isMobile() || !weekPlanWrap) return;
      requestAnimationFrame(() => {
        const todayTh = weekPlanWrap.querySelector('th.tc-wp-today');
        if (todayTh) {
          todayTh.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
        }
      });
    };

    // ── Responsive View-Logik ─────────────────────────────────
    const getResponsiveView = () => {
      if (weekOnly || forceDesktop) return 'timeGridWeek';
      if (useMobileSlider()) return 'timeGridWeek';
      return isMobile() ? 'listMonth' : (el.dataset.view || globalDefaultView);
    };

    const getResponsiveToolbar = () => {
      if (useMobileSlider()) return { left: 'prev', center: 'title', right: 'next' };
      if (weekOnly) return { left: '', center: 'title', right: '' };
      if (forceDesktop) return { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' };
      return isMobile()
        ? { left: 'prev,next', center: 'title', right: 'listMonth,dayGridMonth' }
        : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' };
    };

    /* ─── MODULE 4c: FullCalendar Settings Helpers ─────────── */
    const applyTimeColumnLabel = (opts, mode) => {
      if (mode === 'hours') {
        opts.slotLabelInterval = '01:00';
        opts.slotLabelFormat   = { hour: '2-digit', minute: '2-digit', hour12: false };
        // slotLabelContent explizit NICHT setzen
      } else if (mode === 'groups') {
        opts.slotLabelInterval = '06:00';
        opts.slotMinTime       = '06:00:00';
        opts.slotMaxTime       = '23:00:00';
        opts.slotLabelContent  = function(arg) {
          const h = arg.date.getHours();
          if (h >= 6  && h < 12) return 'Vormittag';
          if (h >= 12 && h < 17) return 'Nachmittag';
          if (h >= 17)           return 'Abend';
          return '';
        };
      } else if (mode === 'both') {
        opts.slotLabelInterval = '01:00';
        opts.slotLabelContent  = function(arg) {
          const h = arg.date.getHours();
          if (arg.date.getMinutes() !== 0) return '';
          const group = h < 12 ? 'Vorm.' : h < 17 ? 'Nachm.' : 'Abend';
          const time  = String(h).padStart(2, '0') + ':00';
          return {
            html: '<span style="display:block;font-size:9px;color:var(--tc-text-subtle,#9ca3af);'
                + 'line-height:1.1;text-transform:uppercase;letter-spacing:.03em">'
                + group + '</span>'
                + '<span style="display:block;font-size:10px;color:var(--tc-text-muted,#6b7280);'
                + 'line-height:1.2">' + time + '</span>'
          };
        };
      }
    };

    // ── Einstellung 2: Zeitstempel im Event-Block ───────────
    const applyEventTimeDisplay = (opts, mode) => {
      if (mode === 'none') {
        opts.displayEventTime = false;
      } else {
        opts.displayEventTime = true;
        opts.eventTimeFormat  = { hour: '2-digit', minute: '2-digit', hour12: false };
      }
    };

    /* ─── MODULE 4d: FullCalendar Init ─────────────────────── */
    const calendar = new FullCalendar.Calendar(el, {
      initialView:   getResponsiveView(),
      initialDate:   (weekOnly || useMobileSlider()) ? new Date() : undefined,
      locale:        'de',
      height:        'auto',
      firstDay:      globalWeekStart === 'sunday' ? 0 : 1,
      editable:      false,
      navLinks:      !weekOnly && !useMobileSlider(),
      headerToolbar: getResponsiveToolbar(),
      buttonText: {
        today: 'Heute',
        month: 'Monat',
        week:  'Woche',
        list:  'Liste',
      },
      noEventsText:  'Keine Events in diesem Zeitraum.',
      allDaySlot:    false,
      slotDuration:  '00:30:00',
      slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },

      datesSet() {
        updateVisibleTimeRange();
        initSliderScrollSync();
        scrollToMonday();
      },
      eventsSet()  { updateVisibleTimeRange(); },

      windowResize() {
        if (forceDesktop) return;
        updateSliderClass();
        if (weekOnly) return;

        const targetView    = getResponsiveView();
        const targetToolbar = getResponsiveToolbar();
        calendar.setOption('headerToolbar', targetToolbar);
        calendar.setOption('navLinks', !useMobileSlider());
        if (calendar.view.type !== targetView) {
          calendar.changeView(targetView);
        }
        calendar.updateSize();
        scrollToMonday();
      },

      eventDidMount({ event, el: evEl }) {
        if (!event.startEditable) evEl.style.opacity = '0.8';

        // Vergangene Occurrences zusätzlich ausgegraut und nicht klickbar
        if (event.extendedProps.isPast) {
          evEl.style.opacity       = '0.45';
          evEl.style.filter        = 'grayscale(30%)';
          evEl.style.pointerEvents = 'none';
        }
      },

      eventClick({ event, jsEvent }) {
        jsEvent.preventDefault();
        openPopover(event, jsEvent);
      },
    });

    calendar.render();
    updateSliderClass();

    // ── Desktop-Forced: zoom to fit, no horizontal scroll ────
    if (forceDesktop) {
      const NATURAL_W = 600;

      const applyZoom = () => {
        const available = wrap.offsetWidth;
        if (!available) return;
        const zoom = available < NATURAL_W
          ? (available / NATURAL_W).toFixed(4)
          : '';
        el.style.zoom = zoom;
        if (weekPlanWrap) weekPlanWrap.style.zoom = zoom;
      };

      requestAnimationFrame(applyZoom);
      window.addEventListener('resize', applyZoom);
    }

    // ── Mobile Scaled: transform statt zoom (cross-browser) ──
    if (mobileScaled && !forceDesktop) {
      const SCALED_W = 800;

      const applyScale = () => {
        if (!isMobile()) {
          wrap.classList.remove('tc-mobile-scaled');
          el.style.transform = '';
          el.style.width     = '';
          wrap.style.height  = '';
          return;
        }
        wrap.classList.add('tc-mobile-scaled');
        const containerW = wrap.offsetWidth;
        const scale      = Math.min(1, containerW / SCALED_W);
        el.style.transformOrigin = 'top left';
        el.style.transform       = `scale(${scale})`;
        el.style.width           = `${SCALED_W}px`;
        // Wrapper-Höhe anpassen damit kein Leerraum entsteht
        requestAnimationFrame(() => {
          wrap.style.height = `${el.offsetHeight * scale}px`;
        });
      };

      requestAnimationFrame(applyScale);
      window.addEventListener('resize', applyScale);
    }

    // ── Week-Only Mode ────────────────────────────────────────
    if (weekOnly) {
      el.style.display = 'none';
      const viewToggle = wrap.querySelector('.tc-view-toggle');
      if (viewToggle) viewToggle.style.display = 'none';

      if (weekPlanWrap) {
        weekPlanWrap.style.display = '';
        if (weekPlanPrev) weekPlanPrev.style.display = 'none';
        if (weekPlanNext) weekPlanNext.style.display = 'none';
      }
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
        scrollWeekPlanToToday();
      } else {
        calendar.addEventSource(getFiltered());
        updateVisibleTimeRange();
        scrollToMonday();
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
          scrollWeekPlanToToday();
        } else {
          calendar.getEventSources().forEach(s => s.remove());
          calendar.addEventSource(getFiltered());
          updateVisibleTimeRange();
          if (weekPlanWrap && weekPlanWrap.style.display !== 'none') buildWeekPlan();
        }

        if (showEventList) tcRenderEventOverview(overviewEl, getFiltered(), eventListTitle);
      });
    });

    // ── View-Toggle (Kalender ↔ Week Plan) ───────────────────
    wrap.querySelectorAll('.tc-view-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.classList.contains('is-active')) return;
        wrap.querySelectorAll('.tc-view-btn').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');

        if (btn.dataset.tcView === 'week-plan') {
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

}); // DOMContentLoaded
}()); // IIFE
