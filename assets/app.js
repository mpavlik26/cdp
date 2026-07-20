(function () {
  'use strict';

  const DATA = window.SHIFT_DATA;
  const CONFIG = window.APP_CONFIG;

  const ACCENT = {
    d: { bg: '#FBE1E5', accent: '#E0224B' },
    k: { bg: '#ECEFF2', accent: '#8A96A2' },
    n: { bg: '#ECEFF2', accent: '#8A96A2' },
  };
  const SEAM_ON = '3px solid #1B2530';
  const SEAM_OFF = '1px solid #E9EDF1';
  const TODAY_RING = '2px solid #2563EB';
  const DEFAULT_BORDER = '1px solid #E9EDF1';
  const DOW_ABBREV = ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'];
  const WEEKDAYS = [
    { label: 'Po', bg: '#F5F7F9', color: '#5B6672' },
    { label: 'Út', bg: '#F5F7F9', color: '#5B6672' },
    { label: 'St', bg: '#F5F7F9', color: '#5B6672' },
    { label: 'Čt', bg: '#F5F7F9', color: '#5B6672' },
    { label: 'Pá', bg: '#F5F7F9', color: '#5B6672' },
    { label: 'So', bg: '#FFF3E6', color: '#9A5B00' },
    { label: 'Ne', bg: '#FFF3E6', color: '#9A5B00' },
  ];

  const personIdByName = {};
  Object.keys(CONFIG.personIds || {}).forEach((id) => {
    personIdByName[CONFIG.personIds[id]] = id;
  });

  const todayIndex = DATA.dates.findIndex((d) => d.today);

  const state = {
    view: CONFIG.initialView === 'calendar' ? 'calendar' : 'matrix',
    person: CONFIG.initialPerson || DATA.people[0] || null,
    selId: null,
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => (
      { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
  }

  function noIcon(sizeClass) {
    return '<span class="icon-no' + (sizeClass ? ' ' + sizeClass : '') + '"></span>';
  }

  function noIconLarge() {
    return '<span style="position:relative; display:inline-block; width:18px; height:18px; border:2px solid #C0223F; border-radius:50%; flex-shrink:0;">'
      + '<span style="position:absolute; left:-1px; right:-1px; top:50%; height:2px; background:#C0223F; transform:translateY(-50%) rotate(-45deg);"></span></span>';
  }

  function roleOn(person, day) {
    if (day.d && day.d.person === person) return { key: 'd', a: day.d };
    if (day.k && day.k.person === person) return { key: 'k', a: day.k };
    if (day.n && day.n.person === person) return { key: 'n', a: day.n };
    return null;
  }

  // ---------- Calendar (per-person) view model ----------

  function buildPersonCells(person) {
    const days = DATA.dates;
    return days.map((d, gi) => {
      const cur = roleOn(person, d);
      const prevDay = gi > 0 ? days[gi - 1] : null;
      const prev = prevDay ? roleOn(person, prevDay) : null;
      const hasEndPrev = !!(prev && prev.key === 'n');
      const isNight = !!(cur && cur.key === 'n');
      const isDay = !!(cur && cur.key !== 'n');

      let startTime = '', startColor = '#1B2530', endTime = '', endColor = '#1B2530', accentBar = '', baseBg;

      if (isDay) {
        startTime = cur.a.startStr; startColor = cur.a.startColor;
        endTime = cur.a.endStr; endColor = cur.a.endColor;
        accentBar = ACCENT[cur.key].accent;
        baseBg = ACCENT[cur.key].bg;
      } else {
        baseBg = d.weekend ? '#FFF7EC' : '#FFFFFF';
        if (isNight) { startTime = cur.a.startStr + ' →'; startColor = cur.a.startColor; }
      }

      const empty = !cur && !hasEndPrev;
      const clickable = !!cur || hasEndPrev;
      const selKey = cur ? cur.key : (hasEndPrev ? 'n' : null);
      const refGi = cur ? gi : (hasEndPrev ? gi - 1 : gi);

      return {
        blank: false, gi, num: d.num, monthShort: d.monthShort, firstOfMonth: d.firstOfMonth, showNum: true,
        clickable, selKey, labelTop: isNight, id: clickable ? (refGi + selKey) : null,
        dowIdx: (d.dow + 6) % 7,
        today: d.today, weekend: d.weekend,
        empty, isDay, isNight, hasEndPrev,
        endPrevTime: hasEndPrev ? '→ ' + prev.a.endStr : '',
        endPrevColor: hasEndPrev ? prev.a.endColor : '#1B2530',
        startTime, startColor, endTime, endColor,
        pre514: isDay && cur.a.preKind === '514',
        preNo: isDay && cur.a.preKind === 'no',
        post514: isDay && cur.a.postKind === '514',
        postNo: isDay && cur.a.postKind === 'no',
        nightPreNo: isNight && cur.a.preKind === 'no',
        endPrevNo: hasEndPrev && prev.a.postKind === 'no',
        nBg: ACCENT.n.bg, nAccent: ACCENT.n.accent, baseBg, accentBar,
        outline: d.today ? TODAY_RING : 'none',
      };
    });
  }

  const BLANK_CELL = {
    blank: true, clickable: false, labelTop: false, showNum: false,
    baseBg: '#EAEEF2', outline: 'none', firstOfMonth: false, today: false,
    isDay: false, isNight: false, hasEndPrev: false, empty: false,
    num: '', monthShort: '',
    pre514: false, preNo: false, post514: false, postNo: false, nightPreNo: false, endPrevNo: false,
  };

  function buildCalendar(person) {
    const cells = buildPersonCells(person);
    const weeks = [];
    let cur = null;

    cells.forEach((c) => {
      if (!cur) { cur = { days: new Array(c.dowIdx).fill(BLANK_CELL) }; }
      else if (c.dowIdx === 0) { weeks.push(cur); cur = { days: [] }; }
      cur.days.push(c);
    });
    if (cur) weeks.push(cur);

    weeks.forEach((w) => {
      while (w.days.length < 7) w.days.push(BLANK_CELL);
      const f = w.days.find((x) => !x.blank);
      let l = null;
      for (let i = w.days.length - 1; i >= 0; i--) { if (!w.days[i].blank) { l = w.days[i]; break; } }
      w.label = f ? (f.num + '. – ' + l.num + '.') : '';
      w.sub = f ? f.monthShort : '';
    });

    return { weeks };
  }

  // ---------- Detail panel ----------

  function buildWorkerShifts(person) {
    if (todayIndex === -1 || !person) return [];
    const shifts = [];
    DATA.dates.forEach((day, gi) => {
      if (gi < todayIndex) return;
      ['d', 'k', 'n'].forEach((key) => {
        const a = day[key];
        if (a && a.person === person) {
          const rm = DATA.roleMeta[key];
          const night = key === 'n';
          shifts.push({
            id: gi + key, gi, key,
            weekday: day.weekday, dow: day.dow, dateStr: day.date, night,
            typeLabel: rm.label, code: rm.code, accent: rm.color, tint: rm.bg,
            startStr: a.startStr, endStr: a.endStr, startColor: a.startColor, endColor: a.endColor,
            timesStr: night ? (a.startStr + ' → ' + a.endStr) : (a.startStr + ' – ' + a.endStr),
          });
        }
      });
    });
    return shifts;
  }

  function buildDetail(sel) {
    const days = DATA.dates;
    const d = days[sel.gi];
    const roleMeta = DATA.roleMeta;

    const P = (a, key) => ({
      name: a.person, role: roleMeta[key].label, color: roleMeta[key].color, bg: roleMeta[key].bg,
      startStr: a.startStr, endStr: a.endStr, startColor: a.startColor, endColor: a.endColor,
      sep: key === 'n' ? '→' : '–',
    });

    const WARN = { noteBg: '#FDF0D5', noteFg: '#8A5A00', noteBorder: '#E7B85C', noteIcon: '', noteWarn: true, noteMove: false };
    const MOVE = { noteBg: '#FBE1E5', noteFg: '#C0223F', noteBorder: '#E6A3AE', noteIcon: '514', noteWarn: false, noteMove: true };
    const noNote = { hasNote: false, note: '', noteBg: '', noteFg: '', noteBorder: '', noteIcon: '', noteWarn: false, noteMove: false };
    const withNote = (obj, kind, text) => Object.assign(obj, { hasNote: true, note: text }, kind === 'warn' ? WARN : MOVE);

    const me = d[sel.key], rm = roleMeta[sel.key];
    let takeover, alongside, handoff;

    if (sel.key === 'n') {
      takeover = Object.assign({ time: me.startStr, when: 'Večer', people: [P(d.d, 'd'), P(d.k, 'k')] }, noNote);
      if (me.preKind === 'no') {
        takeover.people = [];
        withNote(takeover, 'warn', 'Nastupuješ bez převzetí — denní služba na 524 už skončila. Mezičas do tvého příchodu kryl kolega na 514.');
      }
      alongside = { show: false, people: [] };
      const nd = days[sel.gi + 1];
      handoff = Object.assign({ time: me.endStr, when: 'Ráno · další den', people: nd ? [P(nd.d, 'd'), P(nd.k, 'k')] : [] }, noNote);
      if (me.postKind === 'no') {
        handoff.people = [];
        withNote(handoff, 'warn', 'Odcházíš bez předávky — ranní denní služba na 524 nastupuje až po tvém odchodu. Mezičas kryje kolega na 514.');
      }
    } else {
      const prev = sel.gi > 0 ? days[sel.gi - 1] : null;
      takeover = Object.assign({ time: me.startStr, when: 'Ráno', people: (prev && prev.n) ? [P(prev.n, 'n')] : [] }, noNote);
      if (me.preKind === '514') {
        withNote(takeover, 'move', 'Začni na sousedním postu 514. Jakmile v ' + d.d.startStr + ' dorazí ' + d.d.person + ' (514), přesedni na svůj post 524.');
      } else if (me.preKind === 'no') {
        takeover.people = [];
        withNote(takeover, 'warn', 'Nastupuješ bez převzetí — předchozí služba na 524 (noční) už skončila. Mezičas do tvého příchodu kryje kolega na 514.');
      }
      const ok = sel.key === 'd' ? 'k' : 'd';
      alongside = { show: true, people: d[ok] ? [P(d[ok], ok)] : [] };
      handoff = Object.assign({ time: me.endStr, when: 'Večer', people: d.n ? [P(d.n, 'n')] : [] }, noNote);
      if (me.postKind === '514') {
        withNote(handoff, 'move', 'Až ve ' + d.n.startStr + ' dorazí ' + d.n.person + ' na noční, přesedni na sousední post 514 a dokonči tam směnu do ' + me.endStr + '.');
      } else if (me.postKind === 'no') {
        handoff.people = [];
        withNote(handoff, 'warn', 'Odcházíš bez předávky — noční služba na 524 začíná až po tvém odchodu. Mezičas kryje kolega na 514.');
      }
    }

    return {
      dateStr: d.date, weekday: d.weekday,
      me: { label: rm.label, color: rm.color, bg: rm.bg, timesStr: sel.timesStr },
      takeover, alongside, handoff,
    };
  }

  // ---------- Matrix ----------

  function buildMatrixPP() {
    const pp = {};
    DATA.people.forEach((n) => { pp[n] = {}; });
    DATA.dates.forEach((day, gi) => {
      ['d', 'k', 'n'].forEach((key) => {
        const a = day[key];
        if (a) pp[a.person][gi] = Object.assign({ key }, a);
      });
    });
    return pp;
  }

  function buildMatrix() {
    const days = DATA.dates;
    const pp = buildMatrixPP();

    const rows = DATA.people.map((name) => {
      const cells = days.map((day, gi) => {
        const todayA = pp[name][gi];
        const prevA = pp[name][gi - 1];
        const hasEndPrev = !!(prevA && prevA.key === 'n');
        const isNight = !!(todayA && todayA.key === 'n');
        const isDay = !!(todayA && todayA.key !== 'n');
        let startTime = '', startColor = '#1B2530', endTime = '', endColor = '#1B2530', accentBar = '', baseBg;

        if (isDay) {
          startTime = todayA.startStr; startColor = todayA.startColor;
          endTime = todayA.endStr; endColor = todayA.endColor;
          accentBar = ACCENT[todayA.key].accent;
          baseBg = ACCENT[todayA.key].bg;
        } else {
          baseBg = day.weekend ? '#FFF3E6' : '#FFFFFF';
          if (isNight) { startTime = todayA.startStr + ' →'; startColor = todayA.startColor; }
        }

        const empty = !todayA && !hasEndPrev;

        return {
          empty, isDay, isNight, hasEndPrev,
          endPrevTime: hasEndPrev ? '→ ' + prevA.endStr : '',
          endPrevColor: hasEndPrev ? prevA.endColor : '#1B2530',
          startTime, startColor, endTime, endColor,
          pre514: isDay && todayA.preKind === '514',
          preNo: isDay && todayA.preKind === 'no',
          post514: isDay && todayA.postKind === '514',
          postNo: isDay && todayA.postKind === 'no',
          nightPreNo: isNight && todayA.preKind === 'no',
          endPrevNo: hasEndPrev && prevA.postKind === 'no',
          nBg: ACCENT.n.bg, nAccent: ACCENT.n.accent, baseBg, accentBar,
          outerBorderLeft: day.firstOfMonth ? SEAM_ON : SEAM_OFF,
          outline: day.today ? TODAY_RING : 'none',
        };
      });
      return { name, cells };
    });

    const header = days.map((d) => ({
      n: d.num, dow: DOW_ABBREV[d.dow],
      bg: d.today ? '#EAF2FF' : (d.weekend ? '#FFF3E6' : '#F5F7F9'),
      ring: d.today ? TODAY_RING : DEFAULT_BORDER,
      borderLeft: d.firstOfMonth ? SEAM_ON : (d.today ? TODAY_RING : DEFAULT_BORDER),
    }));

    return { header, rows, months: DATA.months };
  }

  // ---------- Rendering ----------

  function renderHeader() {
    return (
      '<header class="app-header"><div class="app-header__inner">'
      + '<div class="logo-box">S</div>'
      + '<div class="header-titles">'
      + '<div class="header-title">Rozpis služeb</div>'
      + '<div class="header-subtitle">' + esc(CONFIG.rangeLabel) + '</div>'
      + '</div>'
      + '<nav class="tabs">'
      + '<button type="button" class="tab-btn' + (state.view === 'calendar' ? ' tab-btn--active' : '') + '" data-action="tab" data-view="calendar">Můj směnář</button>'
      + '<button type="button" class="tab-btn' + (state.view === 'matrix' ? ' tab-btn--active' : '') + '" data-action="tab" data-view="matrix">Kompletní směnář</button>'
      + '</nav>'
      + '</div></header>'
    );
  }

  function renderLegend(scale) {
    const noIconFn = () => noIcon(scale === 'sm' ? 'icon-no--sm' : '');
    const items = scale === 'sm'
      ? [
        '<span class="legend-item"><span class="legend-swatch" style="background:#FBE1E5; border-left-color:#E0224B;"></span>dlouhá 514</span>',
        '<span class="legend-item"><span class="legend-swatch" style="background:#ECEFF2; border-left-color:#8A96A2;"></span>krátká 524</span>',
        '<span class="legend-item"><span class="legend-swatch" style="background:#ECEFF2; border-left-color:#8A96A2;"></span>noční</span>',
        '<span class="legend-item"><span class="tag-514">514</span> začátek/konec na 514</span>',
        '<span class="legend-item">' + noIconFn() + ' bez předávky / převzetí</span>',
        '<span><b>19:00 →</b> nástup večer (pokračuje do dalšího dne) &nbsp;·&nbsp; <b>→ 7:30</b> ranní odchod (z předchozí noční)</span>',
        '<span class="legend-item"><span style="color:#C43331; font-weight:700;">červená</span> delší &nbsp; <span style="color:#1B7F3B; font-weight:700;">zelená</span> kratší</span>',
      ]
      : [
        '<span class="legend-item"><span class="legend-swatch" style="background:#FBE1E5; border-left-color:#E0224B;"></span>dlouhá 514</span>',
        '<span class="legend-item"><span class="legend-swatch" style="background:#ECEFF2; border-left-color:#8A96A2;"></span>krátká 524</span>',
        '<span class="legend-item"><span class="legend-swatch" style="background:#ECEFF2; border-left-color:#8A96A2;"></span>noční (dole nástup <b>→</b>, nahoře ranní odchod <b>→</b>)</span>',
        '<span class="legend-item"><span class="tag-514">514</span> začátek/konec na sousedním postu 514</span>',
        '<span class="legend-item">' + noIconFn() + ' bez předávky / bez převzetí (mezičas kryje 514)</span>',
      ];
    return '<div class="legend"><span style="font-weight:700; color:#1B2530;">Legenda:</span>' + items.join('') + '</div>';
  }

  function renderCalDayCell(cell, selId) {
    if (cell.blank) {
      return '<div class="cal-day cal-day--blank"></div>';
    }

    const selected = cell.clickable && cell.id === selId;
    const border = selected ? '2px solid #1B2530' : '1px solid #E9EDF1';
    const style = 'background:' + cell.baseBg + '; border:' + border + '; outline:' + cell.outline + '; outline-offset:2px;';
    const attrs = cell.clickable ? ' data-action="cell-toggle" data-id="' + cell.id + '"' : '';
    const cls = 'cal-day' + (cell.clickable ? ' cal-day--clickable' : '');

    let html = '<div class="' + cls + '" style="' + style + '"' + attrs + '>';

    if (selected) {
      html += '<span class="badge-detail" style="' + (cell.labelTop ? 'top:5px;' : 'bottom:5px;') + '">DETAIL ↓</span>';
    }
    if (cell.showNum) html += '<span class="day-num">' + cell.num + '</span>';
    if (cell.firstOfMonth) html += '<span class="badge-first">' + esc(cell.monthShort) + '</span>';

    if (cell.isDay) {
      html += '<div class="shift-block"><div class="shift-block__bar" style="background:' + cell.accentBar + ';"></div>'
        + '<div class="shift-block__col">'
        + '<div class="shift-row">' + (cell.pre514 ? '<span class="tag-514">514</span>' : '') + (cell.preNo ? noIcon() : '')
        + '<span style="color:' + cell.startColor + ';">' + esc(cell.startTime) + '</span></div>'
        + '<div class="shift-row"><span style="color:' + cell.endColor + ';">' + esc(cell.endTime) + '</span>'
        + (cell.post514 ? '<span class="tag-514">514</span>' : '') + (cell.postNo ? noIcon() : '') + '</div>'
        + '</div></div>';
    }
    if (cell.hasEndPrev) {
      html += '<div class="night-block night-block--top" style="background:' + cell.nBg + '; border-left-color:' + cell.nAccent + ';">'
        + '<span style="color:' + cell.endPrevColor + ';">' + esc(cell.endPrevTime) + '</span>' + (cell.endPrevNo ? noIcon() : '') + '</div>';
    }
    if (cell.isNight) {
      html += '<div class="night-block night-block--bottom" style="background:' + cell.nBg + '; border-left-color:' + cell.nAccent + ';">'
        + (cell.nightPreNo ? noIcon() : '') + '<span style="color:' + cell.startColor + ';">' + esc(cell.startTime) + '</span></div>';
    }
    if (cell.empty) html += '<div class="cal-empty-label">volno</div>';

    html += '</div>';
    return html;
  }

  function renderPPRow(pp) {
    return (
      '<div class="pp-row" style="border-left-color:' + pp.color + '; background:' + pp.bg + ';">'
      + '<div class="pp-row__top"><span class="pp-name">' + esc(pp.name) + '</span>'
      + '<span class="pp-role" style="color:' + pp.color + ';">' + esc(pp.role) + '</span></div>'
      + '<div class="pp-times"><span style="color:' + pp.startColor + ';">' + esc(pp.startStr) + '</span>'
      + '<span class="pp-sep">' + pp.sep + '</span>'
      + '<span style="color:' + pp.endColor + ';">' + esc(pp.endStr) + '</span></div>'
      + '</div>'
    );
  }

  function renderNote(section) {
    if (!section.hasNote) return '';
    const icon = section.noteWarn
      ? noIconLarge()
      : '<span style="font-size:12px; font-weight:700; color:' + section.noteFg + '; background:#fff; border:1px solid ' + section.noteBorder + '; padding:1px 5px; border-radius:4px;">' + esc(section.noteIcon) + '</span>';
    return (
      '<div class="detail-note" style="background:' + section.noteBg + '; border-color:' + section.noteBorder + ';">'
      + '<span class="detail-note__icon">' + icon + '</span>'
      + '<span style="color:' + section.noteFg + ';">' + esc(section.note) + '</span>'
      + '</div>'
    );
  }

  function renderDetailCard(badgeLabel, badgeColor, badgeBg, section) {
    return (
      '<div class="detail-card">'
      + '<div style="display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap;">'
      + '<span class="detail-badge" style="color:' + badgeColor + '; background:' + badgeBg + ';">' + badgeLabel + '</span>'
      + '<span class="detail-when">' + esc(section.when) + ' · ' + esc(section.time) + '</span>'
      + '</div>'
      + renderNote(section)
      + section.people.map(renderPPRow).join('')
      + '</div>'
    );
  }

  function renderDetailPanel(detail) {
    let inner;
    if (detail) {
      inner = (
        '<div class="detail-grid">'
        + '<div class="detail-me" style="border-left-color:' + detail.me.color + ';">'
        + '<div class="detail-me__top" style="background:' + detail.me.bg + ';">'
        + '<div class="detail-me__date">' + esc(detail.weekday) + ' · ' + esc(detail.dateStr) + '</div>'
        + '<div style="display:flex; align-items:baseline; gap:10px; margin-top:3px; flex-wrap:wrap;">'
        + '<span class="detail-me__role" style="color:' + detail.me.color + ';">' + esc(detail.me.label) + '</span>'
        + '<span class="detail-me__times">' + esc(detail.me.timesStr) + '</span>'
        + '</div></div>'
        + '<div class="detail-me__bottom">Tvoje směna. Níže vidíš, koho střídáš, s kým jsi ve službě a komu ji předáváš.</div>'
        + '</div>'
        + '<div class="detail-side">'
        + renderDetailCard('↓ PŘEBÍRÁŠ', '#0E7C66', '#DCF2EB', detail.takeover)
        + (detail.alongside.show
          ? '<div class="detail-card"><div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">'
            + '<span class="detail-badge" style="color:#1B5FBF; background:#E1ECFC;">↔ SPOLU VE SLUŽBĚ</span></div>'
            + detail.alongside.people.map(renderPPRow).join('') + '</div>'
          : '')
        + renderDetailCard('↑ PŘEDÁVÁŠ', '#9A5B00', '#FBEAD0', detail.handoff)
        + '</div></div>'
      );
    } else {
      inner = '<div class="detail-placeholder">Klikněte na směnu v kalendáři výše a zobrazí se detail střídání.</div>';
    }
    return '<div class="detail-panel"><div class="detail-panel__title">DETAIL STŘÍDÁNÍ</div>' + inner + '</div>';
  }

  function renderCalendarView() {
    const person = state.person;
    const shifts = buildWorkerShifts(person);
    const sel = shifts.find((s) => s.id === state.selId) || null;
    const selId = sel ? sel.id : null;
    const detail = sel ? buildDetail(sel) : null;
    const calendar = buildCalendar(person);

    const printId = personIdByName[person];
    const printHref = 'index.php?print=worker&person=' + encodeURIComponent(printId != null ? printId : '');

    const peopleOptions = DATA.people.map((n) => (
      '<option value="' + esc(n) + '"' + (n === person ? ' selected' : '') + '>' + esc(n) + '</option>'
    )).join('');

    const weekdayCells = WEEKDAYS.map((w) => (
      '<div class="cal-weekday-cell" style="background:' + w.bg + '; color:' + w.color + ';">' + w.label + '</div>'
    )).join('');

    const weekCells = calendar.weeks.map((week) => {
      const label = '<div class="cal-week-label"><span class="cal-week-label__range">' + esc(week.label) + '</span>'
        + '<span class="cal-week-label__month">' + esc(week.sub) + '</span></div>';
      const days = week.days.map((cell) => renderCalDayCell(cell, selId)).join('');
      return label + days;
    }).join('');

    return (
      '<div class="card">'
      + '<div class="card-header">'
      + '<label style="display:flex; flex-direction:column; gap:5px;">'
      + '<span style="font-size:13px; font-weight:700; opacity:.8; letter-spacing:.04em;">Vyberte pracovníka</span>'
      + '<select class="person-select" data-role="person-select">' + peopleOptions + '</select>'
      + '</label>'
      + '<div class="card-header__titles">'
      + '<div class="view-eyebrow">MŮJ SMĚNÁŘ · KALENDÁŘ</div>'
      + '<div class="view-title">' + esc(person) + ' · ' + esc(CONFIG.rangeLabel) + '</div>'
      + '</div>'
      + '<a class="print-link" href="' + printHref + '" title="Úsporné zobrazení vhodné pro tisk">🖨 Tiskové zobrazení</a>'
      + '</div>'
      + '<div class="card-body">'
      + '<div class="cal-grid"><div class="cal-corner"></div>' + weekdayCells + weekCells + '</div>'
      + renderLegend('lg')
      + '</div>'
      + renderDetailPanel(detail)
      + '</div>'
    );
  }

  function renderMatrixCell(cell) {
    const style = 'background:' + cell.baseBg + '; border-left:' + cell.outerBorderLeft + '; outline:' + cell.outline + '; outline-offset:-2px;';
    let html = '<div class="matrix-cell" style="' + style + '">';

    if (cell.isDay) {
      html += '<div class="matrix-shift-block"><div class="matrix-shift-block__bar" style="background:' + cell.accentBar + ';"></div>'
        + '<div class="matrix-shift-block__col">'
        + '<div class="matrix-shift-row">' + (cell.pre514 ? '<span class="tag-514">514</span>' : '') + (cell.preNo ? noIcon('icon-no--sm') : '')
        + '<span style="color:' + cell.startColor + ';">' + esc(cell.startTime) + '</span></div>'
        + '<div class="matrix-shift-row"><span style="color:' + cell.endColor + ';">' + esc(cell.endTime) + '</span>'
        + (cell.post514 ? '<span class="tag-514">514</span>' : '') + (cell.postNo ? noIcon('icon-no--sm') : '') + '</div>'
        + '</div></div>';
    }
    if (cell.hasEndPrev) {
      html += '<div class="matrix-night-block matrix-night-block--top" style="background:' + cell.nBg + '; border-left-color:' + cell.nAccent + ';">'
        + '<span style="color:' + cell.endPrevColor + ';">' + esc(cell.endPrevTime) + '</span>' + (cell.endPrevNo ? noIcon('icon-no--sm') : '') + '</div>';
    }
    if (cell.isNight) {
      html += '<div class="matrix-night-block matrix-night-block--bottom" style="background:' + cell.nBg + '; border-left-color:' + cell.nAccent + ';">'
        + (cell.nightPreNo ? noIcon('icon-no--sm') : '') + '<span style="color:' + cell.startColor + ';">' + esc(cell.startTime) + '</span></div>';
    }
    if (cell.empty) html += '<div class="matrix-cell__empty">·</div>';

    html += '</div>';
    return html;
  }

  function renderMatrixView() {
    const matrix = buildMatrix();
    const n = DATA.dates.length;

    const monthHeaders = matrix.months.map((mo) => (
      '<div class="matrix-month" style="grid-column: span ' + mo.span + ';">' + esc(mo.label) + '</div>'
    )).join('');

    const dayHeaders = matrix.header.map((h) => (
      '<div class="matrix-day-header" style="background:' + h.bg + '; border:' + h.ring + '; border-left:' + h.borderLeft + ';">'
      + '<div class="matrix-day-header__dow">' + h.dow + '</div>'
      + '<div class="matrix-day-header__num">' + h.n + '</div></div>'
    )).join('');

    const nameCells = matrix.rows.map((row) => (
      '<div class="matrix-name-cell"><button type="button" class="matrix-name-link" data-action="name-nav" data-person="'
      + esc(row.name) + '">' + esc(row.name) + '</button></div>'
    )).join('');

    const dayRows = matrix.rows.map((row) => row.cells.map(renderMatrixCell).join('')).join('');

    return (
      '<div class="card">'
      + '<div class="card-header card-header--matrix">'
      + '<div class="card-header__titles">'
      + '<div class="view-eyebrow">KOMPLETNÍ SMĚNÁŘ</div>'
      + '<div class="view-title view-title--matrix">Kdo, kdy a jak dlouho — všichni pracovníci</div>'
      + '</div>'
      + '<a class="print-link" href="index.php?print=complete" title="Úsporné zobrazení vhodné pro tisk">🖨 Tiskové zobrazení</a>'
      + '</div>'
      + '<div class="matrix-scroll"><div class="matrix-panes">'
      + '<div class="matrix-names-col"><div class="matrix-corner"></div><div class="matrix-worker-label">PRACOVNÍK</div>' + nameCells + '</div>'
      + '<div class="matrix-days-scroll"><div class="matrix-grid" style="grid-template-columns: repeat(' + n + ', 66px);">'
      + monthHeaders + dayHeaders + dayRows
      + '</div></div>'
      + '</div></div>'
      + renderLegend('sm')
      + '</div>'
    );
  }

  function renderApp() {
    const app = document.getElementById('app');
    app.innerHTML = renderHeader() + '<main class="app-main">'
      + (state.view === 'calendar' ? renderCalendarView() : renderMatrixView())
      + '</main>';
  }

  const app = document.getElementById('app');

  app.addEventListener('click', (e) => {
    const el = e.target.closest('[data-action]');
    if (!el) return;
    const action = el.getAttribute('data-action');

    if (action === 'tab') {
      state.view = el.getAttribute('data-view');
      renderApp();
    } else if (action === 'cell-toggle') {
      const id = el.getAttribute('data-id');
      state.selId = state.selId === id ? null : id;
      renderApp();
    } else if (action === 'name-nav') {
      state.view = 'calendar';
      state.person = el.getAttribute('data-person');
      state.selId = null;
      renderApp();
    }
  });

  app.addEventListener('change', (e) => {
    if (e.target.matches('[data-role="person-select"]')) {
      state.person = e.target.value;
      state.selId = null;
      renderApp();
    }
  });

  renderApp();
})();
