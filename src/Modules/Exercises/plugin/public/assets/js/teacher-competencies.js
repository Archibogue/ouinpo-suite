(function () {
  function normalizeApiRoot() {
    const raw = (window.OUINEXO && OUINEXO.api)
      ? String(OUINEXO.api)
      : '/wp-json/ouinpo/v1';

    const clean = raw.replace(/\/+$/, '');

    if (/\/ouinpo\/v1$/i.test(clean)) return clean;
    if (/\/wp-json$/i.test(clean)) return clean + '/ouinpo/v1';

    return clean + '/ouinpo/v1';
  }

  const root = normalizeApiRoot();

  function H(tag, attrs, text) {
    const el = document.createElement(tag);
    if (attrs) {
      for (const k in attrs) {
        if (Object.prototype.hasOwnProperty.call(attrs, k)) {
          el.setAttribute(k, attrs[k]);
        }
      }
    }
    if (text != null) el.textContent = text;
    return el;
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function fetchJSON(url) {
    return fetch(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    }).then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function buildQuery(params) {
    const u = new URL(root + '/competencies', location.origin);
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '' && v !== 0) {
        u.searchParams.set(k, v);
      }
    });
    return u.toString();
  }

    function buildDsQuery(params) {
      const u = new URL(root + '/competencies/assessments-by-ds', location.origin);
      Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '' && v !== 0) {
          u.searchParams.set(k, v);
        }
      });
      return u.toString();
    }

function buildExercisesQuery(params) {
  const u = new URL(root + '/competencies/exercises-progress', location.origin);
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '' && v !== 0) {
      u.searchParams.set(k, v);
    }
  });
  return u.toString();
}

  function normalizeStatus(raw) {
    if (!raw) return 'not_acquired';

    const s = raw.toString().toLowerCase().trim();

    if (s === 'not_acquired' || s === 'non acquis' || s === 'non acquise') {
      return 'not_acquired';
    }
    if (s === 'acquired' || s === 'acquis' || s === 'acquise') {
      return 'acquired';
    }
    if (s === 'consolidating' || s === 'en consolidation' || s === 'consolidation') {
      return 'consolidating';
    }
    if (
      s === 'in_progress' ||
      s === 'en progression' ||
      s === 'en cours' ||
      s === "en cours d'acquisition"
    ) {
      return 'in_progress';
    }

    if (s.includes('not_acquired') || s.includes('non acquis')) return 'not_acquired';
    if (s.includes('consolid')) return 'consolidating';
    if (s.includes('in_progress') || s.includes('progression') || s.includes('cours')) return 'in_progress';
    if (s.includes('acquired') || s.includes('acquis')) return 'acquired';

    return 'not_acquired';
  }

  function formatStudentStatus(raw) {
    const norm = normalizeStatus(raw);

    if (norm === 'acquired') return { key: norm, ico: '✅', label: 'Acquis' };
    if (norm === 'consolidating') return { key: norm, ico: '📈', label: 'En consolidation' };
    if (norm === 'in_progress') return { key: norm, ico: '⏳', label: 'En progression' };
    return { key: 'not_acquired', ico: '✖️', label: 'Non acquis' };
  }

  function formatTrend(raw) {
    const t = String(raw || '').toLowerCase().trim();

    if (t === 'up') return { ico: '⬆️', label: 'Progression', cls: 'trend-up' };
    if (t === 'down') return { ico: '⬇️', label: 'Recul', cls: 'trend-down' };
    if (t === 'confirmed') return { ico: '✅', label: 'Confirmé', cls: 'trend-confirmed' };
    if (t === 'stable') return { ico: '➖', label: 'Stable', cls: 'trend-stable' };
    return { ico: '🆕', label: 'Premier DS', cls: 'trend-new' };
  }

  function formatDateFr(raw) {
    if (!raw) return '';
    const s = String(raw).slice(0, 10);
    const parts = s.split('-');
    if (parts.length !== 3) return raw;
    return [parts[2], parts[1], parts[0]].join('/');
  }

  function getSelectedFilters(sourceEl) {
    const yearSel   = document.getElementById('t-year');
    const groupSel  = document.getElementById('t-group');
    const domainSel = document.getElementById('t-domain');
    const userSel   = document.getElementById('t-user');
    const viewSel   = document.getElementById('t-view');

    const ds = sourceEl ? sourceEl.dataset : {};

    return {
      year: parseInt((yearSel && yearSel.value) || ds.year || '0', 10),
      group: parseInt((groupSel && groupSel.value) || ds.group || '0', 10),
      domain: (domainSel && domainSel.value) || ds.domain || '',
      user: parseInt((userSel && userSel.value) || ds.user || '0', 10),
      view: (viewSel && viewSel.value) || ds.view || 'detail'
    };
  }

  function getActiveTab() {
    const btn = document.querySelector('.ouinpo-me-tab.is-active');
    return btn ? btn.getAttribute('data-tab') : 'global';
  }

function updateViewFieldVisibility() {
  const viewSel = document.getElementById('t-view');
  if (!viewSel) return;

  const field = viewSel.closest('.field');
  const activeTab = getActiveTab();

  if (activeTab !== 'global') {
    viewSel.disabled = true;
    if (field) field.style.display = 'none';
  } else {
    viewSel.disabled = false;
    if (field) field.style.display = '';
  }
}

  function syncUrlState() {
    const params = new URLSearchParams(window.location.search);

    const yearSel   = document.getElementById('t-year');
    const groupSel  = document.getElementById('t-group');
    const domainSel = document.getElementById('t-domain');
    const userSel   = document.getElementById('t-user');
    const viewSel   = document.getElementById('t-view');

    if (yearSel) params.set('year_id', yearSel.value || 0);
    if (groupSel) params.set('group_id', groupSel.value || 0);
    if (domainSel) {
      if (domainSel.value) params.set('domain', domainSel.value);
      else params.delete('domain');
    }
    if (userSel) params.set('user_id', userSel.value || 0);
    if (viewSel && !viewSel.disabled) params.set('view', viewSel.value || 'detail');

    const activeTab = getActiveTab();
    if (activeTab) params.set('tab', activeTab);

    const next = window.location.pathname + '?' + params.toString();
    window.history.replaceState({}, '', next);
  }

  function reloadForStructureChange() {
    const yearSel   = document.getElementById('t-year');
    const groupSel  = document.getElementById('t-group');
    const viewSel   = document.getElementById('t-view');

    const params = new URLSearchParams(window.location.search);

    if (yearSel) params.set('year_id', yearSel.value || 0);
    if (groupSel) params.set('group_id', groupSel.value || 0);

    params.delete('domain');
    params.set('user_id', '0');

    if (viewSel && !viewSel.disabled) {
      params.set('view', viewSel.value || 'detail');
    }

    const activeTab = getActiveTab();
    if (activeTab) params.set('tab', activeTab);

    window.location.search = params.toString();
  }

    function activateTab(name) {
      const tabs = document.querySelectorAll('.ouinpo-me-tab');
      const panels = document.querySelectorAll('.ouinpo-me-panel');
    
      tabs.forEach(btn => {
        const active = btn.getAttribute('data-tab') === name;
        btn.classList.toggle('is-active', active);
      });
    
      panels.forEach(panel => {
        panel.classList.toggle('is-active', panel.id === 'ouinpo-teacher-tab-' + name);
      });
    
      updateViewFieldVisibility();
      syncUrlState();
    }

  function bindTabs() {
    const tabs = document.querySelectorAll('.ouinpo-me-tab');
    if (!tabs.length) return;

    tabs.forEach(btn => {
      btn.addEventListener('click', () => {
        const name = btn.getAttribute('data-tab') || 'global';
        activateTab(name);
      });
    });

    const params = new URLSearchParams(window.location.search);
    const allowed = ['global', 'ds', 'ex'];
    const raw = params.get('tab') || 'global';
    const initial = allowed.includes(raw) ? raw : 'global';
    activateTab(initial);
  }

  function updateKpis(payload, view) {
    const rows = payload.rows || [];

    const elStudents = document.getElementById('t-kpi-students');
    const elAcq      = document.getElementById('t-kpi-acq');
    const elConso    = document.getElementById('t-kpi-conso');
    const elProg     = document.getElementById('t-kpi-progress');
    const elNa       = document.getElementById('t-kpi-na');
    const userSel    = document.getElementById('t-user');

    if (!elStudents && !elAcq && !elConso && !elProg && !elNa) return;

    const selectedUserId = userSel ? parseInt(userSel.value || '0', 10) : 0;
    const perStudent = new Map();

    rows.forEach(r => {
      const uid = r.user_id || r.userId || r.user;
      if (!uid) return;

      if (!perStudent.has(uid)) {
        perStudent.set(uid, {
          total: 0,
          acquired: 0,
          consolidating: 0,
          inProgress: 0,
          notAcquired: 0
        });
      }

      const agg = perStudent.get(uid);

      if (view === 'domain') {
        agg.total         += Number(r.total) || 0;
        agg.acquired      += Number(r.acquired) || 0;
        agg.consolidating += Number(r.consolidating) || 0;
        agg.inProgress    += Number(r.in_progress) || 0;
        agg.notAcquired   += Number(r.not_acquired) || 0;
      } else {
        agg.total += 1;
        const norm = normalizeStatus(r.status);
        if (norm === 'acquired') agg.acquired += 1;
        else if (norm === 'consolidating') agg.consolidating += 1;
        else if (norm === 'in_progress') agg.inProgress += 1;
        else agg.notAcquired += 1;
      }
    });

    const nbStudents = perStudent.size;
    if (elStudents) elStudents.textContent = String(nbStudents);

    if (nbStudents === 0) {
      if (elAcq) elAcq.textContent = '0';
      if (elConso) elConso.textContent = '0';
      if (elProg) elProg.textContent = '0';
      if (elNa) elNa.textContent = '0';
      return;
    }

    let displayAcq, displayConso, displayProg, displayNa;

    if (selectedUserId > 0 && perStudent.has(selectedUserId)) {
      const s = perStudent.get(selectedUserId);
      displayAcq = s.acquired;
      displayConso = s.consolidating;
      displayProg = s.inProgress;
      displayNa = s.notAcquired;
    } else {
      let sumAcq = 0, sumConso = 0, sumProg = 0, sumNa = 0;

      perStudent.forEach(s => {
        sumAcq += s.acquired;
        sumConso += s.consolidating;
        sumProg += s.inProgress;
        sumNa += s.notAcquired;
      });

      const nb = nbStudents || 1;
      displayAcq = (sumAcq / nb).toFixed(1);
      displayConso = (sumConso / nb).toFixed(1);
      displayProg = (sumProg / nb).toFixed(1);
      displayNa = (sumNa / nb).toFixed(1);
    }

    if (elAcq) elAcq.textContent = String(displayAcq);
    if (elConso) elConso.textContent = String(displayConso);
    if (elProg) elProg.textContent = String(displayProg);
    if (elNa) elNa.textContent = String(displayNa);
  }

function setTeacherExerciseKpis(summary) {
  const elStudents = document.getElementById('t-ex-kpi-students');
  const elTotal    = document.getElementById('t-ex-kpi-total');
  const elWorked   = document.getElementById('t-ex-kpi-worked');
  const elSolid    = document.getElementById('t-ex-kpi-solid');
  const elPriority = document.getElementById('t-ex-kpi-priority');

  if (elStudents) elStudents.textContent = String(Number(summary.students || 0));
  if (elTotal)    elTotal.textContent    = String(Number(summary.total || 0));
  if (elWorked)   elWorked.textContent   = String(Number(summary.worked || 0));
  if (elSolid)    elSolid.textContent    = String(Number(summary.solid || 0));
  if (elPriority) elPriority.textContent = String(Number(summary.priority || 0));
}

function renderTeacherExercises(container, payload, selectedUserId) {
  container.innerHTML = '';

  const rows = (payload && payload.rows) ? payload.rows : [];
  const selected = Number(selectedUserId || 0);

  if (!rows.length) {
    container.innerHTML = '<p>Aucune donnée d’exercices pour ces filtres.</p>';
    return;
  }

  if (selected > 0) {
    const grid = H('div', { class: 'ouinpo-ds-cards' });

    rows.forEach(row => {
      const available = Number(row.available_count || 0);
      const attempted = Number(row.attempted_count || 0);
      const solved = Number(row.solved_count || 0);
      const coverage = Number(row.coverage_pct || 0);
      const success = Number(row.success_pct || 0);

      const card = H('article', { class: 'ouinpo-ds-card' });
      card.innerHTML = `
        <h4>${escapeHtml(row.label || '')}</h4>
        <p class="ouinpo-ds-domain">${escapeHtml(row.domain || '')}</p>
        <p><strong>État actuel :</strong>
          <span class="ouinpo-ex-state is-${escapeHtml(row.current_status || 'not_acquired')}">
            ${escapeHtml(formatStudentStatus(row.current_status).label)}
          </span>
        </p>
        <p><strong>Exercices :</strong> ${available} disponibles · ${attempted} tentés · ${solved} réussis</p>
        <p><strong>Couverture :</strong> ${coverage}%</p>
        <p><strong>Réussite :</strong> ${success}%</p>
        <p><strong>Niveaux réussis :</strong> Débutant ${Number(row.solved_beginner_count || 0)} · Confirmé ${Number(row.solved_confirmed_count || 0)} · Expert ${Number(row.solved_expert_count || 0)}</p>
      `;
      grid.appendChild(card);
    });

    container.appendChild(grid);
    return;
  }

const table = H('table', { class: 'ouinpo-table ouinpo-ex-table' });

const colgroup = H('colgroup');
colgroup.innerHTML = `
  <col style="width:120px">
  <col style="width:100px">
  <col style="width:auto">
  <col style="width:120px">
  <col style="width:95px">
  <col style="width:120px">
  <col style="width:120px">
`;
table.appendChild(colgroup);

const thead = H('thead');
const tbody = H('tbody');

thead.innerHTML = `
  <tr>
    <th>Élève</th>
    <th>Domaine</th>
    <th>Compétence</th>
    <th>État actuel</th>
    <th>D/T/R</th>
    <th>Couverture</th>
    <th>Réussite</th>
  </tr>
`;

function miniBar(value) {
  const n = Number(value || 0);
  let cls = 'is-low';
  if (n >= 70) cls = 'is-good';
  else if (n >= 40) cls = 'is-mid';

  const wrap = H('div');
  wrap.innerHTML = `
    <div class="ouinpo-ex-mini-bar ${cls}">
      <span style="width:${Math.max(0, Math.min(100, n))}%"></span>
    </div>
    <div class="ouinpo-ex-mini-bar-label">${n}%</div>
  `;
  return wrap;
}

rows.forEach(row => {
  const tr = H('tr');

  const available = Number(row.available_count || 0);
  const attempted = Number(row.attempted_count || 0);
  const solved = Number(row.solved_count || 0);
  const success = Number(row.success_pct || 0);

  tr.appendChild(H('td', null, row.display_name || ''));
  tr.appendChild(H('td', null, row.domain || ''));
  tr.appendChild(H('td', null, row.label || ''));
  tr.appendChild(H('td', null, formatStudentStatus(row.current_status).label));
  tr.appendChild(H('td', null, `${available}/${attempted}/${solved}`));

  const tdCoverage = H('td');
  tdCoverage.appendChild(miniBar(row.coverage_pct || 0));
  tr.appendChild(tdCoverage);

  const tdSuccess = H('td');
  tdSuccess.appendChild(miniBar(row.success_pct || 0));
  tr.appendChild(tdSuccess);

  if (available > 0 && (attempted === 0 || success < 50)) {
    tr.classList.add('is-priority');
  } else if (available > 0 && attempted >= 3 && success >= 70) {
    tr.classList.add('is-solid');
  }

  tbody.appendChild(tr);
});

table.appendChild(thead);
table.appendChild(tbody);
container.appendChild(table);
}

function setTeacherDsKpis(payload) {
  const el1 = document.getElementById('t-ds-kpi-evaluated');
  const el2 = document.getElementById('t-ds-kpi-acq');
  const el3 = document.getElementById('t-ds-kpi-conso');
  const el4 = document.getElementById('t-ds-kpi-prog');
  const el5 = document.getElementById('t-ds-kpi-na');

  if (!el1 && !el2 && !el3 && !el4 && !el5) return;

  const s = payload && payload.summary ? payload.summary : {};

  if (el1) el1.textContent = String(Number(s.assessments || 0));
  if (el2) el2.textContent = String(Number(s.students || 0));
  if (el3) el3.textContent = String(Number(s.absences || 0));
  if (el4) el4.textContent = String(Number(s.evaluated || 0));
  if (el5) el5.textContent = String(Number(s.acquired || 0));
}

  function renderTable(container, payload, view) {
    container.innerHTML = '';
    const rows = payload.rows || [];

    const userSel = document.getElementById('t-user');
    const selectedUserId = userSel ? parseInt(userSel.value || '0', 10) : 0;

    const table = H('table', { class: 'ouinpo-table' });
    const thead = H('thead');
    const tbody = H('tbody');

    if (view === 'domain') {
      if (selectedUserId === 0) {
        const grouped = new Map();

        rows.forEach(r => {
          const domainName = r.domain || 'Sans domaine';

          if (!grouped.has(domainName)) {
            grouped.set(domainName, {
              domain: domainName,
              total: 0,
              acquired: 0,
              inProg: 0,
              inConso: 0,
              notAcq: 0,
              students: new Set()
            });
          }

          const g = grouped.get(domainName);
          const uid = r.user_id || r.userId || r.user;
          if (uid) g.students.add(uid);

          g.total   += Number(r.total) || 0;
          g.acquired += Number(r.acquired) || 0;
          g.inProg  += Number(r.in_progress) || 0;
          g.inConso += Number(r.consolidating) || 0;
          g.notAcq  += Number(r.not_acquired) || 0;
        });

        thead.innerHTML = `
          <tr>
            <th>Domaine</th>
            <th>Progression moyenne</th>
          </tr>`;

        grouped.forEach(g => {
          const nbSt = g.students.size || 1;

          const avgAcq   = g.acquired / nbSt;
          const avgProg  = g.inProg / nbSt;
          const avgConso = g.inConso / nbSt;
          const avgNa    = g.notAcq / nbSt;

          const sumAvg = (avgAcq + avgProg + avgConso + avgNa) || 0;

          const pctAcq   = sumAvg > 0 ? Math.round(avgAcq * 100 / sumAvg) : 0;
          const pctConso = sumAvg > 0 ? Math.round(avgConso * 100 / sumAvg) : 0;
          const pctProg  = sumAvg > 0 ? Math.round(avgProg * 100 / sumAvg) : 0;
          const pctNa    = Math.max(0, 100 - pctAcq - pctConso - pctProg);

          const tr = H('tr');
          tr.appendChild(H('td', null, g.domain));

          const tdProg = H('td');
          const bar = H('div', { class: 'ouin-bar' });

          bar.appendChild(H('span', { class: 'seg seg-acq', style: `width:${pctAcq}%;` }, pctAcq ? pctAcq + '%' : ''));
          bar.appendChild(H('span', { class: 'seg seg-conso', style: `width:${pctConso}%;` }, pctConso ? pctConso + '%' : ''));
          bar.appendChild(H('span', { class: 'seg seg-prog', style: `width:${pctProg}%;` }, pctProg ? pctProg + '%' : ''));
          bar.appendChild(H('span', { class: 'seg seg-na', style: `width:${pctNa}%;` }, pctNa ? pctNa + '%' : ''));

          const legend = H(
            'div',
            { class: 'ouin-bar-legend' },
            `${avgAcq.toFixed(1)} acquis · ${avgConso.toFixed(1)} en consolidation · ${avgProg.toFixed(1)} en progression · ${avgNa.toFixed(1)} non acquis (moyenne par élève, ${nbSt} élève(s))`
          );

          tdProg.appendChild(bar);
          tdProg.appendChild(legend);
          tr.appendChild(tdProg);
          tbody.appendChild(tr);
        });

      } else {
        thead.innerHTML = `
          <tr>
            <th>Élève</th>
            <th>Domaine</th>
            <th>Progression</th>
          </tr>`;

        rows.forEach(r => {
          const tr = H('tr');

          const total         = Number(r.total) || 0;
          const acquired      = Number(r.acquired) || 0;
          const inProg        = Number(r.in_progress) || 0;
          const consolidating = Number(r.consolidating) || 0;
          const notAcq        = Number(r.not_acquired) || 0;

          const sum = (acquired + inProg + consolidating + notAcq) || total || 0;

          const pctAcq   = sum > 0 ? Math.round(acquired * 100 / sum) : 0;
          const pctConso = sum > 0 ? Math.round(consolidating * 100 / sum) : 0;
          const pctProg  = sum > 0 ? Math.round(inProg * 100 / sum) : 0;
          const pctNa    = Math.max(0, 100 - pctAcq - pctConso - pctProg);

          tr.appendChild(H('td', null, r.display_name || ''));
          tr.appendChild(H('td', null, r.domain || ''));

          const tdProg = H('td');
          const bar = H('div', { class: 'ouin-bar' });

          bar.appendChild(H('span', { class: 'seg seg-acq', style: `width:${pctAcq}%;` }, pctAcq ? pctAcq + '%' : ''));
          bar.appendChild(H('span', { class: 'seg seg-conso', style: `width:${pctConso}%;` }, pctConso ? pctConso + '%' : ''));
          bar.appendChild(H('span', { class: 'seg seg-prog', style: `width:${pctProg}%;` }, pctProg ? pctProg + '%' : ''));
          bar.appendChild(H('span', { class: 'seg seg-na', style: `width:${pctNa}%;` }, pctNa ? pctNa + '%' : ''));

          const legend = H(
            'div',
            { class: 'ouin-bar-legend' },
            `${acquired} acquis · ${consolidating} en consolidation · ${inProg} en progression · ${notAcq} non acquis${total ? ` (sur ${total})` : ''}`
          );

          tdProg.appendChild(bar);
          tdProg.appendChild(legend);
          tr.appendChild(tdProg);
          tbody.appendChild(tr);
        });
      }

    } else {
      thead.innerHTML = `
        <tr>
          <th>Élève</th>
          <th>Domaine</th>
          <th>Compétence</th>
          <th>Statut</th>
        </tr>`;

      rows.forEach(r => {
        const tr = H('tr');

        tr.appendChild(H('td', null, r.display_name || ''));
        tr.appendChild(H('td', null, r.domain || ''));

        const tdLabel = H('td');
        const wrapper = H('span', { class: 'ouinpo-tooltip-wrapper' });
        const spanLabel = H('span', { class: 'ouin-li-label' }, r.label || '');
        wrapper.appendChild(spanLabel);

        if (r.capacity || r.example) {
          const tooltip = H('div', { class: 'ouinpo-tooltip' });
          tooltip.appendChild(H('div', { class: 'ouinpo-tooltip-title' }, 'Détail de la compétence'));

          if (r.capacity) {
            const line = H('div', { class: 'ouinpo-tooltip-line' });
            line.appendChild(H('span', { class: 'ouinpo-tooltip-label' }, 'Capacité :'));
            line.appendChild(document.createTextNode(' ' + r.capacity));
            tooltip.appendChild(line);
          }

          if (r.example) {
            const line = H('div', { class: 'ouinpo-tooltip-line' });
            line.appendChild(H('span', { class: 'ouinpo-tooltip-label' }, 'Exemple :'));
            line.appendChild(document.createTextNode(' ' + r.example));
            tooltip.appendChild(line);
          }

          wrapper.appendChild(tooltip);
        }

        tdLabel.appendChild(wrapper);
        tr.appendChild(tdLabel);

        const tdStatus = H('td');
        const info = formatStudentStatus(r.status);
        tdStatus.appendChild(H('span', { class: 'ouin-li-ico', title: info.label }, info.ico));
        tr.appendChild(tdStatus);

        tbody.appendChild(tr);
      });
    }

    table.appendChild(thead);
    table.appendChild(tbody);
    container.appendChild(table);
  }

function renderTeacherDs(container, payload, selectedUserId) {
  container.innerHTML = '';

  const assessments = (payload && payload.assessments) ? payload.assessments : [];

  if (!assessments.length) {
    container.innerHTML = '<p>Aucun DS enregistré pour ces filtres.</p>';
    return;
  }

  const selected = Number(selectedUserId || 0);

  const badge = (label, cls = '') =>
    `<span class="ouinpo-ds-badge ${cls}">${escapeHtml(String(label))}</span>`;

  if (selected > 0) {
    const grid = H('div', { class: 'ouinpo-ds-cards' });

    assessments.forEach(ds => {
      const student = Array.isArray(ds.students) && ds.students.length ? ds.students[0] : null;
      if (!student) return;

      const article = H('article', {
        class: 'ouinpo-ds-card' + (student.is_absent ? ' is-absent' : '')
      });

      let html = `
        <div class="ouinpo-ds-card-head">
          <div>
            <h4>${escapeHtml(ds.title || '')}</h4>
            <p class="ouinpo-ds-card-meta">
              ${escapeHtml(ds.group_label || '')}
              ${ds.due_on ? ' — ' + escapeHtml(formatDateFr(ds.due_on)) : ''}
            </p>
          </div>
          <div>
            ${student.is_absent ? badge('Absent', 'is-absent') : badge('Présent', 'is-present')}
          </div>
        </div>
      `;

      if (student.is_absent) {
        html += `
          <div class="ouinpo-ds-card-body">
            ${student.absence_note ? `<p><strong>Note :</strong> ${escapeHtml(student.absence_note)}</p>` : ''}
          </div>
        `;
      } else {
        const c = student.counts || {};
        html += `
          <div class="ouinpo-ds-card-body">
            <div class="ouinpo-ds-badges-line">
              ${badge(`${Number(c.acquired || 0)} acquis`, 'is-acquired')}
              ${badge(`${Number(c.consolidating || 0)} conso`, 'is-conso')}
              ${badge(`${Number(c.in_progress || 0)} prog`, 'is-prog')}
              ${badge(`${Number(c.not_acquired || 0)} NA`, 'is-na')}
            </div>
          </div>
        `;
      }

      if (!student.is_absent && Array.isArray(student.competencies) && student.competencies.length) {
        const details = H('details', { class: 'ouinpo-ds-details' });
        details.appendChild(H('summary', null, 'Voir le détail'));

        const ul = H('ul', { class: 'ouinpo-ds-detail-list' });
        student.competencies.forEach(item => {
          const li = H('li');
          const info = formatStudentStatus(item.status);
          li.innerHTML = `
            <strong>${escapeHtml(item.domain || '')}</strong> — ${escapeHtml(item.label || '')}
            ${badge(info.label, 'is-' + String(item.status || ''))}
            ${item.note ? `<em> — ${escapeHtml(item.note)}</em>` : ''}
          `;
          ul.appendChild(li);
        });

        details.appendChild(ul);
        article.appendChild(details);
      }

      article.innerHTML = html + article.innerHTML;
      grid.appendChild(article);
    });

    if (!grid.children.length) {
      container.innerHTML = '<p>Aucun DS enregistré pour cet élève avec ces filtres.</p>';
      return;
    }

    container.appendChild(grid);
    return;
  }

  const table = H('table', { class: 'ouinpo-table ouinpo-ds-table' });
    const colgroup = H('colgroup');
    colgroup.innerHTML = `
      <col style="width:110px">
      <col style="width:auto">
      <col style="width:90px">
      <col style="width:80px">
      <col style="width:80px">
      <col style="width:80px">
      <col style="width:80px">
      <col style="width:80px">
      <col style="width:80px">
      <col style="width:110px">
    `;
    table.appendChild(colgroup);  
  const thead = H('thead');
  const tbody = H('tbody');

  thead.innerHTML = `
    <tr>
      <th>Date</th>
      <th>DS</th>
      <th>Classe</th>
      <th>Évalués</th>
      <th>Absents</th>
      <th>Acquis</th>
      <th>Conso</th>
      <th>Prog</th>
      <th>NA</th>
      <th></th>
    </tr>
  `;

  assessments.forEach(ds => {
    const totals = ds.totals || {};
    const students = Array.isArray(ds.students) ? ds.students : [];

    const hasData =
      Number(totals.evaluated_students || 0) > 0 ||
      Number(totals.absent_students || 0) > 0;

    const row = H('tr', { class: hasData ? '' : 'is-empty-ds' });

    row.appendChild(H('td', null, ds.due_on ? formatDateFr(ds.due_on) : '—'));
    row.appendChild(H('td', null, ds.title || ''));
    row.appendChild(H('td', null, ds.group_label || ''));
    row.appendChild(H('td', null, String(Number(totals.evaluated_students || 0))));
    row.appendChild(H('td', null, String(Number(totals.absent_students || 0))));
    row.appendChild(H('td', null, String(Number(totals.acquired || 0))));
    row.appendChild(H('td', null, String(Number(totals.consolidating || 0))));
    row.appendChild(H('td', null, String(Number(totals.in_progress || 0))));
    row.appendChild(H('td', null, String(Number(totals.not_acquired || 0))));

    const tdToggle = H('td');
    if (students.length) {
      const btn = H('button', {
        type: 'button',
        class: 'button button-small ouinpo-ds-toggle',
        'aria-expanded': 'false'
      }, 'Voir');
      tdToggle.appendChild(btn);
    } else {
      tdToggle.textContent = '—';
    }
    row.appendChild(tdToggle);
    tbody.appendChild(row);

    if (students.length) {
      const detailRow = H('tr', {
        class: 'ouinpo-ds-detail-row',
        hidden: 'hidden'
      });

      const detailCell = H('td', { colspan: '10' });
      const wrap = H('div', { class: 'ouinpo-ds-detail-wrap' });

      const list = H('div', { class: 'ouinpo-ds-students-grid' });

      students.forEach(student => {
        const item = H('div', {
          class: 'ouinpo-ds-student-card' + (student.is_absent ? ' is-absent' : '')
        });

        if (student.is_absent) {
          item.innerHTML = `
            <div class="ouinpo-ds-student-head">
              <strong>${escapeHtml(student.display_name || '')}</strong>
              ${badge('Absent', 'is-absent')}
            </div>
            ${student.absence_note ? `<div class="ouinpo-ds-student-note">${escapeHtml(student.absence_note)}</div>` : ''}
          `;
        } else {
          const c = student.counts || {};
          item.innerHTML = `
            <div class="ouinpo-ds-student-head">
              <strong>${escapeHtml(student.display_name || '')}</strong>
            </div>
            <div class="ouinpo-ds-badges-line">
              ${badge(`${Number(c.acquired || 0)} acquis`, 'is-acquired')}
              ${badge(`${Number(c.consolidating || 0)} conso`, 'is-conso')}
              ${badge(`${Number(c.in_progress || 0)} prog`, 'is-prog')}
              ${badge(`${Number(c.not_acquired || 0)} NA`, 'is-na')}
            </div>
          `;
        }

        list.appendChild(item);
      });

      wrap.appendChild(list);
      detailCell.appendChild(wrap);
      detailRow.appendChild(detailCell);
      tbody.appendChild(detailRow);

      const btn = tdToggle.querySelector('.ouinpo-ds-toggle');
      btn.addEventListener('click', function () {
        const isOpen = !detailRow.hasAttribute('hidden');
        if (isOpen) {
          detailRow.setAttribute('hidden', 'hidden');
          btn.setAttribute('aria-expanded', 'false');
          btn.textContent = 'Voir';
        } else {
          detailRow.removeAttribute('hidden');
          btn.setAttribute('aria-expanded', 'true');
          btn.textContent = 'Repli';
        }
      });
    }
  });

  table.appendChild(thead);
  table.appendChild(tbody);
  container.appendChild(table);
}

async function refreshExercises() {
  const wrap = document.getElementById('t-ex-results');
  if (!wrap) return;

  const filters = getSelectedFilters(wrap);

  wrap.innerHTML = '<div class="loading">Chargement…</div>';

  const params = {
    year_id: filters.year || '',
    group_id: filters.group || '',
    domain: filters.domain || '',
    user_id: filters.user || ''
  };

  try {
    const data = await fetchJSON(buildExercisesQuery(params));
    setTeacherExerciseKpis(data.summary || {});
    renderTeacherExercises(wrap, data, filters.user || 0);
  } catch (e) {
    console.error(e);
    wrap.innerHTML = '<div class="error">Impossible de charger le suivi des exercices.</div>';
  }
}

  async function refresh() {
    const wrap = document.getElementById('t-results');
    if (!wrap) return;

    const filters = getSelectedFilters(wrap);

    wrap.innerHTML = '<div class="loading">Chargement…</div>';

    const params = {
      year_id: filters.year || '',
      group_id: filters.group || '',
      domain: filters.domain || '',
      user_id: filters.user || '',
      view: filters.view
    };

    try {
      const data = await fetchJSON(buildQuery(params));
      updateKpis(data, filters.view);
      renderTable(wrap, data, filters.view);
    } catch (e) {
      console.error(e);
      wrap.innerHTML = '<div class="error">Impossible de charger les données.</div>';
    }
  }

async function refreshDs() {
  const wrap = document.getElementById('t-ds-results');
  if (!wrap) return;

  const filters = getSelectedFilters(wrap);

  wrap.innerHTML = '<div class="loading">Chargement…</div>';

  const params = {
    year_id: filters.year || '',
    group_id: filters.group || '',
    domain: filters.domain || '',
    user_id: filters.user || ''
  };

  try {
    const data = await fetchJSON(buildDsQuery(params));
    setTeacherDsKpis(data);
    renderTeacherDs(wrap, data, filters.user || 0);
  } catch (e) {
    console.error(e);
    wrap.innerHTML = '<div class="error">Impossible de charger les données des DS.</div>';
  }
}

function refreshAll() {
  syncUrlState();
  return Promise.allSettled([refresh(), refreshDs(), refreshExercises()]);
}

  document.addEventListener('DOMContentLoaded', () => {
    const yearSel   = document.getElementById('t-year');
    const groupSel  = document.getElementById('t-group');
    const domainSel = document.getElementById('t-domain');
    const userSel   = document.getElementById('t-user');
    const viewSel   = document.getElementById('t-view');
    const btn       = document.getElementById('t-refresh');

    bindTabs();

    if (btn) {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        refreshAll();
      });
    }

    if (yearSel) yearSel.addEventListener('change', reloadForStructureChange);
    if (groupSel) groupSel.addEventListener('change', reloadForStructureChange);

    if (domainSel) domainSel.addEventListener('change', refreshAll);
    if (userSel) userSel.addEventListener('change', refreshAll);
    if (viewSel) viewSel.addEventListener('change', refreshAll);

    refreshAll();
  });

  document.addEventListener('click', e => {
    const wrap = e.target.closest('.ouinpo-tooltip-wrapper');
    if (!wrap) {
      document.querySelectorAll('.ouinpo-tooltip-wrapper.active')
        .forEach(w => w.classList.remove('active'));
      return;
    }
    wrap.classList.toggle('active');
  });
})();