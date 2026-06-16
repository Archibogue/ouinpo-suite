(function () {

function getApiRoot() {
  const raw = (window.OUINEXO && OUINEXO.api)
    ? String(OUINEXO.api)
    : '/wp-json/ouinpo/v1';

  return raw.replace(/\/+$/, '');
}

const root = getApiRoot();

function apiUrl(path) {
  const cleanPath = '/' + String(path || '').replace(/^\/+/, '');

  /*
   * Cas permaliens simples WordPress :
   * /index.php?rest_route=/ouinpo/v1
   */
  if (root.includes('rest_route=')) {
    const u = new URL(root, window.location.origin);
    const baseRoute = (u.searchParams.get('rest_route') || '/ouinpo/v1').replace(/\/+$/, '');

    u.searchParams.set('rest_route', baseRoute + cleanPath);

    return u.toString();
  }

  /*
   * Cas REST classique :
   * /wp-json/ouinpo/v1
   */
  return new URL(root.replace(/\/+$/, '') + cleanPath, window.location.origin).toString();
}

  const app = document.getElementById('ouinpo-competencies-app');

  if (!app) return;



  const params = {

    year: parseInt(app.dataset.year || '0', 10),

    group: parseInt(app.dataset.group || '0', 10),

    domain: app.dataset.domain || '',

    user: parseInt(app.dataset.user || '0', 10),

    view: app.dataset.view || 'detail',

    mode: app.dataset.mode || 'student',

    nonce: app.dataset.nonce || ''

  };



  if (!params.year) {

    app.innerHTML = '<p>Sélectionne une année puis “Appliquer”.</p>';

    return;

  }



  function H(tag, attrs = {}, html = '') {

    const el = document.createElement(tag);

    Object.entries(attrs).forEach(([k, v]) => el.setAttribute(k, v));

    if (html) el.innerHTML = html;

    return el;

  }



  function escapeHTML(str) {

    const div = document.createElement('div');

    div.textContent = String(str ?? '');

    return div.innerHTML;

  }



  function statusSelect(cur) {

    const s = H('select', { class: 'ouinpo-status' });



    ['not_acquired', 'in_progress', 'consolidating', 'acquired'].forEach((v) => {

      const labels = {

        not_acquired: 'Non acquis',

        in_progress: 'En progression',

        consolidating: 'En consolidation',

        acquired: 'Acquis'

      };



      const opt = H('option', { value: v }, labels[v]);

      if (v === cur) opt.selected = true;

      s.appendChild(opt);

    });



    return s;

  }



  function teachingStateSelect(cur) {

    const s = H('select', { class: 'ouinpo-teaching-state' });



    [

      ['not_started', 'Pas encore vue'],

      ['seen', 'Vue']

    ].forEach(([value, label]) => {

      const opt = H('option', { value }, label);

      if (value === cur) opt.selected = true;

      s.appendChild(opt);

    });



    return s;

  }



  async function getJSON(url) {

    const res = await fetch(url, {

      headers: {

        Accept: 'application/json',

        'X-WP-Nonce': params.nonce

      },

      credentials: 'include'

    });



    if (!res.ok) {
      let message = 'HTTP ' + res.status;
      try {
        const err = await res.json();
        if (err && err.message) message = err.message;
      } catch (_) {}
      throw new Error(message);
    }

    return res.json();

  }



  async function postJSON(path, payload) {

    const res = await fetch(apiUrl(path), {

      method: 'POST',

      headers: {

        'Content-Type': 'application/json',

        'X-WP-Nonce': params.nonce

      },

      credentials: 'include',

      body: JSON.stringify(payload)

    });



    if (!res.ok) {
      let message = 'HTTP ' + res.status;
      try {
        const err = await res.json();
        if (err && err.message) message = err.message;
      } catch (_) {}
      throw new Error(message);
    }

    return res.json();

  }



  function rowDetail(r) {

    const tr = H('tr');



    tr.appendChild(H('td', {}, String(r.user_id)));

    tr.appendChild(H('td', {}, escapeHTML(r.display_name || '')));

    tr.appendChild(H('td', {}, escapeHTML(r.domain || '')));

    tr.appendChild(H('td', {}, escapeHTML(r.label || '')));



    const td = H('td');

    const sel = statusSelect(r.status || 'not_acquired');



    sel.addEventListener('change', () => {

      queueUpdate({

        user_id: r.user_id,

        competency_id: r.competency_id,

        year_id: params.year,

        group_id: r.group_id,

        status: sel.value

      });

    });



    td.appendChild(sel);

    tr.appendChild(td);



    tr.appendChild(H('td', {}, r.updated_at ? escapeHTML(r.updated_at) : '—'));

    return tr;

  }



  function rowDomain(r) {

    const tr = H('tr');



    tr.appendChild(H('td', {}, String(r.user_id)));

    tr.appendChild(H('td', {}, escapeHTML(r.display_name || '')));

    tr.appendChild(H('td', {}, escapeHTML(r.domain || '')));

    tr.appendChild(H('td', {}, String(r.total || 0)));

    tr.appendChild(H('td', {}, String(r.acquired || 0)));

    tr.appendChild(H('td', {}, String(r.consolidating || 0)));

    tr.appendChild(H('td', {}, String(r.in_progress || 0)));

    tr.appendChild(H('td', {}, String(r.not_acquired || 0)));



    return tr;

  }



  let queue = [];

  let flushTimer = null;



  function queueUpdate(item) {

    queue.push(item);

    if (flushTimer) return;

    flushTimer = setTimeout(flush, 700);

  }



  async function flush() {

    const payload = { items: queue };

    queue = [];

    flushTimer = null;



    try {

      await postJSON('/competencies', payload);

    } catch (e) {

      console.error(e);

      alert('Erreur enregistrement. Essaie encore.');

    }

  }



function buildStudentUrl() {
  return buildCompetenciesUrl(params.view);
}



function buildCompetenciesUrl(view) {
  const u = new URL(apiUrl('/competencies'), window.location.origin);

  u.searchParams.set('year_id', params.year);

  if (params.group) u.searchParams.set('group_id', params.group);
  if (params.domain) u.searchParams.set('domain', params.domain);
  if (params.user) u.searchParams.set('user_id', params.user);

  u.searchParams.set('view', view || params.view);

  return u.toString();
}



function statusLabel(status) {
  const labels = {
    not_acquired: 'Non acquis',
    in_progress: 'En progression',
    consolidating: 'En consolidation',
    acquired: 'Acquis'
  };

  return labels[status] || labels.not_acquired;
}



function selectedText(id, fallback) {
  const el = document.getElementById(id);
  if (!el || !el.options || el.selectedIndex < 0) return fallback || '';
  return el.options[el.selectedIndex].textContent.trim();
}



function groupDetailRows(rows) {
  const map = new Map();

  (rows || []).forEach((row) => {
    const uid = Number(row.user_id || 0);
    if (!uid) return;

    if (!map.has(uid)) {
      map.set(uid, {
        user_id: uid,
        display_name: row.display_name || ('Eleve ' + uid),
        rows: [],
        counts: {
          acquired: 0,
          consolidating: 0,
          in_progress: 0,
          not_acquired: 0
        }
      });
    }

    const student = map.get(uid);
    const status = ['acquired', 'consolidating', 'in_progress', 'not_acquired'].includes(row.status)
      ? row.status
      : 'not_acquired';

    student.rows.push(Object.assign({}, row, { status }));
    student.counts[status] += 1;
  });

  return Array.from(map.values()).sort((a, b) =>
    String(a.display_name).localeCompare(String(b.display_name))
  );
}



function studentStatsLine(student) {
  const c = student.counts || {};
  const total = student.rows ? student.rows.length : 0;

  return [
    total + ' competence(s)',
    Number(c.acquired || 0) + ' acquis',
    Number(c.consolidating || 0) + ' consolidation',
    Number(c.in_progress || 0) + ' progression',
    Number(c.not_acquired || 0) + ' non acquis'
  ].join(' - ');
}



function formatAiComment(payload) {
  const parts = [];

  if (payload.teacher_comment) {
    parts.push(String(payload.teacher_comment).trim());
  } else if (payload.summary) {
    parts.push(String(payload.summary).trim());
  }

  if (Array.isArray(payload.strengths) && payload.strengths.length) {
    parts.push('Points solides : ' + payload.strengths.join(' ; ') + '.');
  }

  if (Array.isArray(payload.priorities) && payload.priorities.length) {
    parts.push('Priorites : ' + payload.priorities.join(' ; ') + '.');
  }

  if (Array.isArray(payload.next_steps) && payload.next_steps.length) {
    parts.push('Pistes de travail : ' + payload.next_steps.join(' ; ') + '.');
  }

  return parts.filter(Boolean).join('\n\n');
}



function printableCss() {
  return `
    @page { margin: 14mm; }
    body { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height: 1.35; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    h2 { font-size: 18px; margin: 0 0 8px; }
    h3 { font-size: 13px; margin: 14px 0 6px; }
    p { margin: 5px 0; }
    .meta { border-bottom: 2px solid #111; margin-bottom: 14px; padding-bottom: 8px; }
    .student { break-after: page; page-break-after: always; }
    .student:last-child { break-after: auto; page-break-after: auto; }
    .summary { background: #f4f6f8; border: 1px solid #d9dde3; padding: 9px 10px; margin: 8px 0 12px; white-space: pre-wrap; }
    .stats { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0 10px; }
    .pill { border: 1px solid #c9ced6; border-radius: 999px; padding: 3px 7px; }
    table { border-collapse: collapse; width: 100%; margin-top: 6px; }
    th, td { border: 1px solid #d2d6dc; padding: 4px 5px; text-align: left; vertical-align: top; }
    th { background: #eef1f5; font-weight: 700; }
    .status-acquired { color: #166534; }
    .status-consolidating { color: #1d4ed8; }
    .status-in_progress { color: #92400e; }
    .status-not_acquired { color: #991b1b; }
  `;
}



function buildPrintHtml(students) {
  const now = new Date();
  const title = 'Suivi de competences';
  const context = [
    selectedText('filter-year', ''),
    selectedText('filter-group', ''),
    selectedText('filter-domain', '')
  ].filter(Boolean).join(' - ');

  const body = students.map((student) => {
    const counts = student.counts || {};
    const rows = (student.rows || []).slice().sort((a, b) => {
      const ad = String(a.domain || '');
      const bd = String(b.domain || '');
      if (ad !== bd) return ad.localeCompare(bd);
      return String(a.label || '').localeCompare(String(b.label || ''));
    });

    const tableRows = rows.map((row) => `
      <tr>
        <td>${escapeHTML(row.domain || '')}</td>
        <td>${escapeHTML(row.label || '')}</td>
        <td class="status-${escapeHTML(row.status || 'not_acquired')}">${escapeHTML(statusLabel(row.status))}</td>
      </tr>
    `).join('');

    return `
      <section class="student">
        <div class="meta">
          <h1>${escapeHTML(title)}</h1>
          <p>${escapeHTML(context || 'Tous les filtres')}</p>
          <p>Edition du ${escapeHTML(now.toLocaleDateString('fr-FR'))}</p>
        </div>
        <h2>${escapeHTML(student.display_name || '')}</h2>
        <div class="stats">
          <span class="pill">${rows.length} competence(s)</span>
          <span class="pill">${Number(counts.acquired || 0)} acquis</span>
          <span class="pill">${Number(counts.consolidating || 0)} en consolidation</span>
          <span class="pill">${Number(counts.in_progress || 0)} en progression</span>
          <span class="pill">${Number(counts.not_acquired || 0)} non acquis</span>
        </div>
        <h3>Commentaire</h3>
        <div class="summary">${escapeHTML(student.comment || 'Aucun commentaire renseigne.')}</div>
        <h3>Detail des competences</h3>
        <table>
          <thead><tr><th>Domaine</th><th>Competence</th><th>Statut</th></tr></thead>
          <tbody>${tableRows || '<tr><td colspan="3">Aucune donnee.</td></tr>'}</tbody>
        </table>
      </section>
    `;
  }).join('');

  return `<!doctype html>
    <html>
    <head>
      <meta charset="utf-8">
      <title>${escapeHTML(title)}</title>
      <style>${printableCss()}</style>
    </head>
    <body>${body}</body>
    </html>`;
}



function collectSelectedExportStudents(panel) {
  const selected = [];

  panel.querySelectorAll('[data-export-student]').forEach((card) => {
    const checkbox = card.querySelector('[data-export-check]');
    if (!checkbox || !checkbox.checked) return;

    const textarea = card.querySelector('[data-export-comment]');
    const uid = Number(card.dataset.userId || 0);
    const student = card._student;
    if (!student || !uid) return;

    selected.push(Object.assign({}, student, {
      comment: textarea ? textarea.value.trim() : ''
    }));
  });

  return selected;
}



async function generateAiForCard(card) {
  const status = card.querySelector('[data-export-status]');
  const textarea = card.querySelector('[data-export-comment]');
  const uid = Number(card.dataset.userId || 0);

  if (!uid || !textarea) return;

  if (status) status.textContent = 'Synthese IA en cours...';

  const payload = await postJSON('/competencies/student-summary', {
    year_id: params.year,
    group_id: params.group || 0,
    user_id: uid,
    domain: params.domain || ''
  });

  textarea.value = formatAiComment(payload);

  if (status) status.textContent = 'Synthese ajoutee.';
}



function renderExportPanel(students) {
  const panel = H('section', { class: 'ouinpo-export-panel' });

  panel.innerHTML = `
    <div class="ouinpo-export-panel__head">
      <div>
        <h2>Preparer le PDF de suivi</h2>
        <p>Selectionne les eleves, ajuste le commentaire, puis imprime la page propre.</p>
      </div>
      <button type="button" class="button" data-export-close>Fermer</button>
    </div>
    <div class="ouinpo-export-actions">
      <button type="button" class="button" data-export-select-all>Tout selectionner</button>
      <button type="button" class="button" data-export-ai-selected>Synthese IA selection</button>
      <button type="button" class="button button-primary" data-export-print>Imprimer / PDF</button>
    </div>
    <div class="ouinpo-export-list"></div>
  `;

  const list = panel.querySelector('.ouinpo-export-list');

  students.forEach((student) => {
    const card = H('article', {
      class: 'ouinpo-export-student',
      'data-export-student': '1',
      'data-user-id': String(student.user_id)
    });

    card._student = student;
    card.innerHTML = `
      <div class="ouinpo-export-student__head">
        <label>
          <input type="checkbox" data-export-check checked>
          <strong>${escapeHTML(student.display_name || '')}</strong>
        </label>
        <button type="button" class="button button-small" data-export-ai>Generer synthese IA</button>
      </div>
      <p class="ouinpo-export-stats">${escapeHTML(studentStatsLine(student))}</p>
      <textarea data-export-comment rows="5" placeholder="Commentaire a faire apparaitre dans le PDF"></textarea>
      <p class="ouinpo-export-status" data-export-status></p>
    `;

    list.appendChild(card);
  });

  panel.querySelector('[data-export-close]').addEventListener('click', () => panel.remove());

  panel.querySelector('[data-export-select-all]').addEventListener('click', () => {
    const checks = Array.from(panel.querySelectorAll('[data-export-check]'));
    const shouldCheck = checks.some((check) => !check.checked);
    checks.forEach((check) => {
      check.checked = shouldCheck;
    });
  });

  panel.querySelector('[data-export-ai-selected]').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const cards = Array.from(panel.querySelectorAll('[data-export-student]'))
      .filter((card) => {
        const check = card.querySelector('[data-export-check]');
        return check && check.checked;
      });

    if (!cards.length) {
      alert('Selectionne au moins un eleve.');
      return;
    }

    button.disabled = true;

    for (const card of cards) {
      try {
        await generateAiForCard(card);
      } catch (e) {
        console.error(e);
        const status = card.querySelector('[data-export-status]');
        if (status) status.textContent = e.message || 'Erreur IA.';
      }
    }

    button.disabled = false;
  });

  panel.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-export-ai]');
    if (!button) return;

    const card = button.closest('[data-export-student]');
    if (!card) return;

    button.disabled = true;
    try {
      await generateAiForCard(card);
    } catch (e) {
      console.error(e);
      const status = card.querySelector('[data-export-status]');
      if (status) status.textContent = e.message || 'Erreur IA.';
    } finally {
      button.disabled = false;
    }
  });

  panel.querySelector('[data-export-print]').addEventListener('click', () => {
    const selected = collectSelectedExportStudents(panel);
    if (!selected.length) {
      alert('Selectionne au moins un eleve.');
      return;
    }

    const win = window.open('', '_blank');
    if (!win) {
      alert('La fenetre d impression a ete bloquee par le navigateur.');
      return;
    }

    win.document.open();
    win.document.write(buildPrintHtml(selected));
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 250);
  });

  return panel;
}



async function openExportBuilder(host) {
  if (queue.length) {
    await flush();
  }

  const old = host.querySelector('.ouinpo-export-panel');
  if (old) old.remove();

  const loading = H('p', { class: 'ouinpo-export-loading' }, 'Preparation des donnees...');
  host.insertBefore(loading, host.firstChild);

  try {
    const detailData = await getJSON(buildCompetenciesUrl('detail'));
    const students = groupDetailRows(detailData.rows || []);

    loading.remove();

    if (!students.length) {
      host.insertBefore(H('p', { class: 'ouinpo-export-loading' }, 'Aucun eleve a exporter pour ces filtres.'), host.firstChild);
      return;
    }

    host.insertBefore(renderExportPanel(students), host.firstChild);
  } catch (e) {
    console.error(e);
    loading.textContent = 'Impossible de preparer le PDF.';
  }
}



function buildCourseUrl() {
  const u = new URL(apiUrl('/competencies/teaching-state'), window.location.origin);

  u.searchParams.set('year_id', params.year);

  if (params.group) u.searchParams.set('group_id', params.group);
  if (params.domain) u.searchParams.set('domain', params.domain);

  return u.toString();
}



  function renderStudentMode(data) {

    const box = H('div');



    const tools = H('div', {

      style: 'margin:10px 0; display:flex; gap:8px; flex-wrap:wrap;'

    });



    const btnSeed = H(

      'button',

      { class: 'button', type: 'button' },

      'Synchroniser les compétences vues'

    );



    btnSeed.addEventListener('click', async () => {

      if (!params.group) {

        alert('Choisis une classe.');

        return;

      }



      try {

        await postJSON('/competencies/seed-group', {

          group_id: params.group,

          year_id: params.year

        });

        await load();

      } catch (e) {

        console.error(e);

        alert('Erreur de synchronisation.');

      }

    });



    tools.appendChild(btnSeed);



    const btnExport = H(

      'button',

      { class: 'button button-primary', type: 'button' },

      'Preparer PDF'

    );



    btnExport.addEventListener('click', async () => {

      btnExport.disabled = true;

      try {

        await openExportBuilder(box);

      } finally {

        btnExport.disabled = false;

      }

    });



    tools.appendChild(btnExport);

    box.appendChild(tools);



    const table = H('table', { class: 'widefat fixed striped' });

    const thead = H('thead');

    const tbody = H('tbody');



    if (data.view === 'detail') {

      thead.innerHTML = '<tr><th>ID</th><th>Élève</th><th>Domaine</th><th>Compétence</th><th>Statut</th><th>Modifié</th></tr>';

      (data.rows || []).forEach(r => tbody.appendChild(rowDetail(r)));

    } else {

      thead.innerHTML = '<tr><th>ID</th><th>Élève</th><th>Domaine</th><th>Total</th><th>Acquis</th><th>En consolidation</th><th>En progression</th><th>Non acquis</th></tr>';

      (data.rows || []).forEach(r => tbody.appendChild(rowDomain(r)));

    }



    table.appendChild(thead);

    table.appendChild(tbody);

    box.appendChild(table);



    return box;

  }



  function renderCourseMode(data) {

    const box = H('div');



    if (!params.group) {

      box.appendChild(H(

        'p',

        {},

        'Choisis une classe pour piloter les compétences vues en cours.'

      ));

      return box;

    }



    const tools = H('div', {

      style: 'margin:10px 0; display:flex; gap:8px; flex-wrap:wrap;'

    });



    const btnSync = H(

      'button',

      { class: 'button button-secondary', type: 'button' },

      'Synchroniser les compétences vues avec les élèves'

    );



    btnSync.addEventListener('click', async () => {

      try {

        await postJSON('/competencies/seed-group', {

          group_id: params.group,

          year_id: params.year

        });

        await load();

      } catch (e) {

        console.error(e);

        alert('Erreur de synchronisation.');

      }

    });



    const btnReload = H(

      'button',

      { class: 'button', type: 'button' },

      'Recharger'

    );



    btnReload.addEventListener('click', () => load());



    tools.appendChild(btnSync);

    tools.appendChild(btnReload);

    box.appendChild(tools);



    const table = H('table', { class: 'widefat fixed striped' });

    const thead = H('thead');

    const tbody = H('tbody');



    thead.innerHTML = `

      <tr>

        <th>ID</th>

        <th>Domaine</th>

        <th>Compétence</th>

        <th>État du cours</th>

        <th>Vue depuis</th>

        <th>Dernier changement</th>

      </tr>

    `;



    const rows = data.rows || [];



    if (!rows.length) {

      const tr = H('tr');

      tr.innerHTML = '<td colspan="6">Aucune compétence trouvée pour ce filtre.</td>';

      tbody.appendChild(tr);

    } else {

      rows.forEach((r) => {

        const tr = H('tr');



        tr.appendChild(H('td', {}, String(r.id)));

        tr.appendChild(H('td', {}, escapeHTML(r.domain || '')));

        tr.appendChild(H('td', {}, escapeHTML(r.label || '')));



        const tdState = H('td');

        const sel = teachingStateSelect(r.teaching_state || 'not_started');



        sel.addEventListener('change', async () => {

          try {

            sel.disabled = true;



            await postJSON('/competencies/teaching-state', {

              year_id: params.year,

              group_id: params.group,

              competency_id: r.id,

              teaching_state: sel.value

            });



            await load();

          } catch (e) {

            console.error(e);

            alert('Erreur lors de la mise à jour de l’état du cours.');

          } finally {

            sel.disabled = false;

          }

        });



        tdState.appendChild(sel);

        tr.appendChild(tdState);



        tr.appendChild(H('td', {}, r.first_seen_at ? escapeHTML(r.first_seen_at) : '—'));

        tr.appendChild(H('td', {}, r.state_changed_at ? escapeHTML(r.state_changed_at) : '—'));



        tbody.appendChild(tr);

      });

    }



    table.appendChild(thead);

    table.appendChild(tbody);

    box.appendChild(table);



    return box;

  }



  async function load() {

    app.innerHTML = '<p>Chargement…</p>';



    try {

      const data = await getJSON(

        params.mode === 'course' ? buildCourseUrl() : buildStudentUrl()

      );



      const box = params.mode === 'course'

        ? renderCourseMode(data)

        : renderStudentMode(data);



      app.innerHTML = '';

      app.appendChild(box);

    } catch (e) {

      console.error(e);

      app.innerHTML = '<p>Erreur de chargement.</p>';

    }

  }



  load();

})();
