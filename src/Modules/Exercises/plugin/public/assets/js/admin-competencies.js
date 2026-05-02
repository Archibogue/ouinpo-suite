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



    if (!res.ok) throw new Error('HTTP ' + res.status);

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



    if (!res.ok) throw new Error('HTTP ' + res.status);

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
  const u = new URL(apiUrl('/competencies'), window.location.origin);

  u.searchParams.set('year_id', params.year);

  if (params.group) u.searchParams.set('group_id', params.group);
  if (params.domain) u.searchParams.set('domain', params.domain);
  if (params.user) u.searchParams.set('user_id', params.user);

  u.searchParams.set('view', params.view);

  return u.toString();
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