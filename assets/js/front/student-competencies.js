(function () {

  function $(sel, ctx) {

    return (ctx || document).querySelector(sel);

  }



  function $$(sel, ctx) {

    return Array.from((ctx || document).querySelectorAll(sel));

  }



  function H(tag, attrs = {}, html = '') {

    const e = document.createElement(tag);

    for (const k in attrs) {

      if (Object.prototype.hasOwnProperty.call(attrs, k)) {

        e.setAttribute(k, attrs[k]);

      }

    }

    if (html) e.innerHTML = html;

    return e;

  }



  function escapeHTML(str) {

    if (str == null) return '';

    const div = document.createElement('div');

    div.textContent = String(str);

    return div.innerHTML;

  }



  function apiUrl(path, params = {}) {
    const raw = (window.OUINEXO && OUINEXO.api)
      ? String(OUINEXO.api)
      : '/wp-json/';

    const cleanPath = '/' + String(path || '').replace(/^\/+/, '');

    /*
     * Cas permaliens simples WordPress :
     * /index.php?rest_route=/
     * ou
     * /index.php?rest_route=/ouinpo/v1
     */
    if (raw.includes('rest_route=')) {
      const u = new URL(raw, window.location.origin);
      let baseRoute = (u.searchParams.get('rest_route') || '').replace(/\/+$/, '');

      if (!baseRoute) {
        baseRoute = '/ouinpo/v1';
      } else if (!/\/ouinpo\/v1$/i.test(baseRoute)) {
        baseRoute += '/ouinpo/v1';
      }

      u.searchParams.set('rest_route', baseRoute + cleanPath);

      Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '') {
          u.searchParams.set(k, String(v));
        }
      });

      return u.toString();
    }

    /*
     * Cas REST classique :
     * /wp-json/
     * ou
     * /wp-json/ouinpo/v1
     */
    let base = raw.replace(/\/+$/, '');

    if (/\/wp-json$/i.test(base)) {
      base += '/ouinpo/v1';
    } else if (!/\/ouinpo\/v1$/i.test(base)) {
      base += '/ouinpo/v1';
    }

    const u = new URL(base + cleanPath, window.location.origin);

    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        u.searchParams.set(k, String(v));
      }
    });

    return u.toString();
  }

  async function getJSON(url) {
    const headers = {
      Accept: 'application/json'
    };

    if (window.OUINEXO && OUINEXO.nonce) {
      headers['X-WP-Nonce'] = OUINEXO.nonce;
    }

    const res = await fetch(url, {
      headers,
      credentials: 'include'
    });

    const text = await res.text();

    if (!res.ok) {
      console.error('[OuInPo progression] HTTP', res.status, url, text);
      throw new Error('HTTP ' + res.status);
    }

    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('[OuInPo progression] Réponse non JSON', url, text);
      throw e;
    }
  }

  function buildUrl(path, params = {}) {
    return apiUrl(path, params);
  }

  

  function buildSegfaultParcoursUrl(limit = 3) {

  const url = new URL('/wp-json/ouinpo-segfault/v1/parcours', window.location.origin);

  url.searchParams.set('limit', String(limit));

  return url.toString();

  }



  function formatStatus(status) {

    switch (status) {

      case 'acquired':

        return { ico: '✅', label: 'Acquis' };

      case 'consolidating':

        return { ico: '📈', label: 'En consolidation' };

      case 'in_progress':

        return { ico: '⏳', label: 'En progression' };

      case 'not_acquired':

      default:

        return { ico: '❌', label: 'Non acquis' };

    }

  }



  function trendMeta(trend) {

    switch (trend) {

      case 'up':

        return { ico: '↗', label: 'en hausse', cls: 'up' };

      case 'down':

        return { ico: '↘', label: 'à retravailler', cls: 'down' };

      case 'confirmed':

        return { ico: '✓', label: 'confirmé', cls: 'stable' };

      case 'stable':

        return { ico: '→', label: 'stable', cls: 'stable' };

      default:

        return { ico: '•', label: 'première évaluation', cls: 'stable' };

    }

  }



  function statusBadgeHTML(status) {

    const st = formatStatus(status);

    const cls = {

      acquired: 'badge-acq',

      consolidating: 'badge-conso',

      in_progress: 'badge-prog',

      not_acquired: 'badge-na'

    }[status] || 'badge-na';



    return `<span class="ouinpo-status-badge ${cls}">${escapeHTML(st.label)}</span>`;

  }



  function setText(id, value, wrap) {

    const el = $('#' + id, wrap);

    if (el) el.textContent = String(value);

  }



  function setGlobalKPIs(summary, wrap) {

    setText('kpi-total', summary.total || 0, wrap);

    setText('kpi-acq', summary.acquired || 0, wrap);

    setText('kpi-conso', summary.consolidating || 0, wrap);

    setText('kpi-prog', summary.in_progress || 0, wrap);

    setText('kpi-na', summary.not_acquired || 0, wrap);

  }



  function setDsKPIs(summary, wrap) {

    setText('ds-kpi-evaluated', summary.evaluated || 0, wrap);

    setText('ds-kpi-acq', summary.acquired || 0, wrap);

    setText('ds-kpi-conso', summary.consolidating || 0, wrap);

    setText('ds-kpi-prog', summary.in_progress || 0, wrap);

    setText('ds-kpi-na', summary.not_acquired || 0, wrap);

  }



  function setTrainingKPIs(summary, wrap) {

    setText('ex-kpi-total', summary.total || 0, wrap);

    setText('ex-kpi-worked', summary.worked || 0, wrap);

    setText('ex-kpi-solid', summary.solid || 0, wrap);

    setText('ex-kpi-priority', summary.priority || 0, wrap);

  }



function sortByLabelFr(rows) {

  return [...rows].sort((a, b) =>

    String(a.label || '').localeCompare(String(b.label || ''), 'fr')

  );

}



function populateTrainingFilters(wrap, rows) {

  const domainSelect = $('.js-training-domain', wrap);

  const compSelect = $('.js-training-comp', wrap);

  if (!domainSelect || !compSelect) return;



  const selectedDomain = String(domainSelect.value || '');

  const selectedComp = String(compSelect.value || '');



  const allRows = Array.isArray(rows) ? rows : [];



  const domains = [...new Set(

    allRows

      .map((r) => String(r.domain || '').trim())

      .filter(Boolean)

  )].sort((a, b) => a.localeCompare(b, 'fr'));



  domainSelect.innerHTML = '<option value="">Tous les domaines</option>';

  domains.forEach((domain) => {

    const option = H('option', { value: domain }, escapeHTML(domain));

    if (domain === selectedDomain) option.selected = true;

    domainSelect.appendChild(option);

  });



  const rowsForComp = selectedDomain

    ? allRows.filter((r) => String(r.domain || '') === selectedDomain)

    : allRows;



  const comps = sortByLabelFr(rowsForComp);



  compSelect.innerHTML = '<option value="">Toutes les compétences</option>';

  comps.forEach((row) => {

    const value = String(row.competency_id || row.slug || row.label || '');

    const option = H('option', { value }, escapeHTML(row.label || ''));

    if (value === selectedComp) option.selected = true;

    compSelect.appendChild(option);

  });



  const stillExists = [...compSelect.options].some((o) => o.value === selectedComp);

  if (!stillExists) {

    compSelect.value = '';

  }

}



function getFilteredTrainingRows(wrap) {

  const allRows = Array.isArray(wrap._trainingRows) ? wrap._trainingRows : [];

  const domainSelect = $('.js-training-domain', wrap);

  const compSelect = $('.js-training-comp', wrap);



  const selectedDomain = String(domainSelect?.value || '');

  const selectedComp = String(compSelect?.value || '');



  return allRows.filter((row) => {

    const rowDomain = String(row.domain || '');

    const rowComp = String(row.competency_id || row.slug || row.label || '');



    if (selectedDomain && rowDomain !== selectedDomain) return false;

    if (selectedComp && rowComp !== selectedComp) return false;

    return true;

  });

}



function refreshTrainingView(wrap) {

  const filteredRows = getFilteredTrainingRows(wrap);

  renderTrainingCompetencies(wrap, filteredRows);

}



function bindTrainingFilters(wrap) {

  const domainSelect = $('.js-training-domain', wrap);

  const compSelect = $('.js-training-comp', wrap);



  if (domainSelect && !domainSelect.dataset.bound) {

    domainSelect.dataset.bound = '1';

    domainSelect.addEventListener('change', () => {

      populateTrainingFilters(wrap, wrap._trainingRows || []);

      refreshTrainingView(wrap);

    });

  }



  if (compSelect && !compSelect.dataset.bound) {

    compSelect.dataset.bound = '1';

    compSelect.addEventListener('change', () => {

      refreshTrainingView(wrap);

    });

  }

}



  function formatDateFr(dateStr) {

    if (!dateStr) return '—';

    return String(dateStr).split('-').reverse().join('/');

  }



  function barRow(domain, counts, details) {

    const total = Math.max(1, Number(counts.total || 0));

    const acquired = Number(counts.acquired || 0);

    const consolidating = Number(counts.consolidating || 0);

    const inProgress = Number(counts.in_progress || 0);

    const notAcquired = Number(counts.not_acquired || 0);



    const pctA = Math.round((100 * acquired) / total);

    const pctC = Math.round((100 * consolidating) / total);

    const pctP = Math.round((100 * inProgress) / total);

    const pctN = Math.max(0, 100 - pctA - pctC - pctP);



    const wrapper = H('div', { class: 'ouin-domain-block' });



    const row = H('div', { class: 'ouin-bar-row' });



    const label = H('div', { class: 'ouin-bar-label' }, escapeHTML(domain) + ' ▾');

    row.appendChild(label);



    const bar = H('div', { class: 'ouin-bar', role: 'button', tabindex: '0' });

    bar.appendChild(H('span', { class: 'seg seg-acq', style: `width:${pctA}%` }, pctA ? pctA + '%' : ''));

    if (pctC > 0) {

      bar.appendChild(H('span', { class: 'seg seg-conso', style: `width:${pctC}%` }, pctC ? pctC + '%' : ''));

    }

    bar.appendChild(H('span', { class: 'seg seg-prog', style: `width:${pctP}%` }, pctP ? pctP + '%' : ''));

    bar.appendChild(H('span', { class: 'seg seg-na', style: `width:${pctN}%` }, pctN ? pctN + '%' : ''));

    row.appendChild(bar);



    const legend = H(

      'div',

      { class: 'ouin-bar-legend' },

      `${acquired} acquis · ${consolidating} en consolidation · ${inProgress} en progression · ${notAcquired} non acquis`

    );

    row.appendChild(legend);



    const detailsWrap = H('div', {

      class: 'ouin-domain-details',

      style: 'display:none; margin:6px 0 10px;'

    });



    if (details && details.length) {

      const ul = H('ul', { class: 'ouinpo-list' });



      details.forEach((r) => {

        const li = H('li', { class: 'ouin-li' });

        const st = formatStatus(r.status);



        li.appendChild(H('span', { class: 'ouin-li-ico' }, st.ico));

        li.appendChild(H('span', { class: 'ouin-li-domain' }, escapeHTML(r.domain || '')));



        const tdLabel = H('span');

        const wrapperTip = H('span', { class: 'ouinpo-tooltip-wrapper' });

        const spanLabel = H('span', { class: 'ouin-li-label' }, escapeHTML(r.label || ''));

        wrapperTip.appendChild(spanLabel);



        const lines = [];

        if (r.capacity) {

          lines.push(

            `<div class="ouinpo-tooltip-line"><span class="ouinpo-tooltip-label">Capacité :</span> ${escapeHTML(r.capacity)}</div>`

          );

        }

        if (r.example) {

          lines.push(

            `<div class="ouinpo-tooltip-line"><span class="ouinpo-tooltip-label">Exemple :</span> ${escapeHTML(r.example)}</div>`

          );

        }



        if (lines.length) {

          wrapperTip.insertAdjacentHTML(

            'beforeend',

            `<div class="ouinpo-tooltip">

              <div class="ouinpo-tooltip-title">Détail de la compétence</div>

              ${lines.join('')}

            </div>`

          );

        }



        tdLabel.appendChild(wrapperTip);

        li.appendChild(tdLabel);

        li.appendChild(H('span', { class: 'ouin-li-status' }, escapeHTML(st.label)));



        ul.appendChild(li);

      });



      detailsWrap.appendChild(ul);

    } else {

      detailsWrap.appendChild(

        H('div', { class: 'ouin-bar-legend' }, 'Aucune compétence BO trouvée pour ce domaine.')

      );

    }



    function toggle() {

      const open = detailsWrap.style.display === 'block';

      detailsWrap.style.display = open ? 'none' : 'block';

      label.innerHTML = escapeHTML(domain) + (open ? ' ▾' : ' ▴');

    }



    label.addEventListener('click', toggle);

    legend.addEventListener('click', toggle);

    bar.addEventListener('click', toggle);

    bar.addEventListener('keydown', (e) => {

      if (e.key === 'Enter' || e.key === ' ') {

        e.preventDefault();

        toggle();

      }

    });



    wrapper.appendChild(row);

    wrapper.appendChild(detailsWrap);



    return wrapper;

  }



  function renderGlobalDomains(wrap, domains, rows) {

    const byDom = new Map();



    (rows || []).forEach((r) => {

      const d = r.domain || 'Sans domaine';

      if (!byDom.has(d)) byDom.set(d, []);

      byDom.get(d).push(r);

    });



    const host = $('#domains-bars', wrap);

    if (!host) return;



    if (!domains || !domains.length) {

      host.innerHTML = '<p>Aucune compétence suivie pour cette année.</p>';

      return;

    }



    host.innerHTML = '';

    domains.forEach((d) => {

      host.appendChild(

        barRow(

          d.domain,

          {

            total: d.total,

            acquired: d.acquired,

            consolidating: d.consolidating || 0,

            in_progress: d.in_progress,

            not_acquired: d.not_acquired

          },

          byDom.get(d.domain) || []

        )

      );

    });

  }



  function renderOptionalDetail(wrap, rows) {

    const block = $('#detail-block', wrap);

    const list = $('#detail-list', wrap);

    const show = String(wrap.dataset.detail || '') === '1';



    if (!block || !list) return;



    if (!show) {

      block.style.display = 'none';

      return;

    }



    list.innerHTML = '';

    (rows || []).forEach((r) => {

      const st = formatStatus(r.status);

      const li = H('li', { class: 'ouin-li' });

      li.innerHTML = `

        <span class="ouin-li-ico">${st.ico}</span>

        <span class="ouin-li-domain">${escapeHTML(r.domain || '')}</span>

        <span class="ouin-li-label">${escapeHTML(r.label || '')}</span>

        <span class="ouin-li-status">${escapeHTML(st.label)}</span>

      `;

      list.appendChild(li);

    });



    block.style.display = 'block';

  }



function progressIndicator(summary) {

  const total = Math.max(1, Number(summary.total || 0));

  const acquired = Number(summary.acquired || 0);

  const consolidating = Number(summary.consolidating || 0);

  const inProgress = Number(summary.in_progress || 0);



  // Indicateur visuel seulement, pas une "note"

  return Math.round(

    ((acquired + consolidating * 0.7 + inProgress * 0.35) / total) * 100

  );

}



function plural(n, one, many) {

  return n === 1 ? one : many;

}



function segfaultCardIcon(card) {

  switch (card.status) {

    case 'attempted':

      return '🟡';

    case 'solved':

      return '✅';

    default:

      return '🧭';

  }

}



function segfaultCardsToPriorities(cards) {

  return (cards || []).slice(0, 3).map((card) => ({

    source: 'segfault',

    title: card.title || 'Exercice',

    url: card.url || '#',

    badge: card.badge || '',

    status: card.status || 'new',

    difficulty: card.difficulty_label || card.difficulty || '',

  }));

}



function getGlobalPriorities(rows, limit = 3) {

  const rank = {

    not_acquired: 0,

    in_progress: 1,

    consolidating: 2,

    acquired: 3

  };



  return [...(rows || [])]

    .sort((a, b) => {

      const ra = rank[a.status] ?? 99;

      const rb = rank[b.status] ?? 99;

      if (ra !== rb) return ra - rb;



      const da = String(a.domain || '');

      const db = String(b.domain || '');

      return da.localeCompare(db, 'fr');

    })

    .slice(0, limit);

}



function buildGlobalIntro(summary, priorities, fromSegfault = false) {

  const total = Number(summary.total || 0);

  const acquired = Number(summary.acquired || 0);



  if (!total) {

    return "Aucune compétence BO suivie pour cette année.";

  }



  if (fromSegfault && priorities.length) {

    return `Tu maîtrises déjà ${acquired} ${plural(acquired, 'compétence', 'compétences')} sur ${total}. SegFault te propose maintenant ${priorities.length} ${plural(priorities.length, 'révision ciblée', 'révisions ciblées')} pour avancer au bon endroit.`;

  }



  if (!priorities.length) {

    return `Tu maîtrises déjà ${acquired} ${plural(acquired, 'compétence', 'compétences')} sur ${total}. Aucune priorité urgente ne se détache pour le moment.`;

  }



  return `Tu maîtrises déjà ${acquired} ${plural(acquired, 'compétence', 'compétences')} sur ${total}. ${priorities.length} ${plural(priorities.length, 'point mérite', 'points méritent')} maintenant un peu d’attention.`;

}



function buildGlobalSubline(summary) {

  const consolidating = Number(summary.consolidating || 0);

  const inProgress = Number(summary.in_progress || 0);

  const notAcquired = Number(summary.not_acquired || 0);



  if (notAcquired >= 4) {

    return "Le cap est clair : consolider les bases avant d’ouvrir trop de nouveaux fronts.";

  }



  if (inProgress > consolidating) {

    return "Beaucoup de compétences sont en cours d’installation : la pratique régulière fera la différence.";

  }



  if (consolidating >= 3) {

    return "Bonne dynamique : plusieurs compétences sont proches d’un vrai palier de maîtrise.";

  }



  return "Le tableau est stable : continue à entretenir les acquis sans négliger les points fragiles.";

}



function buildLearningAdvice(summary, priorities, fromSegfault = false) {

  const total = Number(summary.total || 0);

  const acquired = Number(summary.acquired || 0);

  const notAcquired = Number(summary.not_acquired || 0);

  const inProgress = Number(summary.in_progress || 0);



  if (!total) {

    return "Le grimoire des compétences est encore vide : il faut d’abord quelques traces d’évaluation pour en lire les constellations.";

  }



  if (fromSegfault && priorities.length) {

    const first = priorities[0]?.title || 'cet exercice';

    return `SegFault a repéré quelques révisions utiles. Commence par ${first}, puis poursuis sans te disperser : mieux vaut trois exercices bien choisis qu’une agitation sans méthode.`;

  }



  if (!priorities.length && acquired >= Math.ceil(total / 2)) {

    return "Belle tenue d’ensemble. Le plus utile maintenant n’est pas de courir partout, mais d’affermir calmement ce qui est déjà bien engagé.";

  }



  if (notAcquired >= 4) {

    return "Inutile de vouloir tout réparer d’un coup. Choisis deux ou trois compétences fragiles, entraîne-les souvent, puis reviens au reste.";

  }



  if (inProgress >= 4) {

    return "Beaucoup de notions sont en germination. La répétition, les petits exercices et la reformulation personnelle sont ici plus puissants que le bachotage.";

  }



  return "Ta progression ressemble moins à une ligne droite qu’à une carte céleste : certains astres brillent déjà, d’autres demandent encore quelques nuits d’observation.";

}

function domainMood(counts) {

  const total = Math.max(1, Number(counts.total || 0));

  const acquired = Number(counts.acquired || 0);

  const consolidating = Number(counts.consolidating || 0);

  const inProgress = Number(counts.in_progress || 0);

  const notAcquired = Number(counts.not_acquired || 0);



  const score = (acquired + consolidating * 0.7 + inProgress * 0.35) / total;



  if (notAcquired === 0 && inProgress === 0) {

    return {

      label: 'solide',

      cls: 'solid',

      text: 'Domaine bien tenu, avec peu de fragilité visible.'

    };

  }



  if (score >= 0.75) {

    return {

      label: 'bien engagé',

      cls: 'good',

      text: 'Ensemble cohérent, quelques points restent à fixer.'

    };

  }



  if (score >= 0.5) {

    return {

      label: 'à consolider',

      cls: 'mid',

      text: 'La progression est réelle, mais elle manque encore de stabilité.'

    };

  }



  return {

    label: 'à renforcer',

    cls: 'warn',

    text: 'Ce domaine demande davantage d’entraînement ciblé.'

  };

}



function renderDomainCard(domainRow, rows) {

  const total = Math.max(1, Number(domainRow.total || 0));

  const acquired = Number(domainRow.acquired || 0);

  const consolidating = Number(domainRow.consolidating || 0);

  const inProgress = Number(domainRow.in_progress || 0);

  const notAcquired = Number(domainRow.not_acquired || 0);



  const pctA = Math.round((100 * acquired) / total);

  const pctC = Math.round((100 * consolidating) / total);

  const pctP = Math.round((100 * inProgress) / total);

  const pctN = Math.max(0, 100 - pctA - pctC - pctP);



  const mood = domainMood({

    total,

    acquired,

    consolidating,

    in_progress: inProgress,

    not_acquired: notAcquired

  });



  const card = H('details', {

    class: `ouinpo-domain-card ${mood.cls}`

  });



  const summary = H('summary', { class: 'ouinpo-domain-summary' });

  summary.innerHTML = `

    <div class="ouinpo-domain-summary-main">

      <div class="ouinpo-domain-summary-top">

        <h4>${escapeHTML(domainRow.domain || 'Sans domaine')}</h4>

        <span class="ouinpo-domain-mood ${mood.cls}">${escapeHTML(mood.label)}</span>

      </div>



      <div class="ouinpo-domain-bar">

        <span class="seg seg-acq" style="width:${pctA}%"></span>

        <span class="seg seg-conso" style="width:${pctC}%"></span>

        <span class="seg seg-prog" style="width:${pctP}%"></span>

        <span class="seg seg-na" style="width:${pctN}%"></span>

      </div>



      <p class="ouinpo-domain-text">${escapeHTML(mood.text)}</p>

      <p class="ouinpo-domain-stats">

        ${acquired} acquis ·

        ${consolidating} en consolidation ·

        ${inProgress} en progression ·

        ${notAcquired} non acquis

      </p>

    </div>



    <div class="ouinpo-domain-summary-side">

      <span class="ouinpo-domain-fraction">${acquired}/${total}</span>

      <span class="ouinpo-domain-open">voir</span>

    </div>

  `;



  const body = H('div', { class: 'ouinpo-domain-body' });



  if (!rows || !rows.length) {

    body.innerHTML = `<p class="ouinpo-empty">Aucune compétence détaillée pour ce domaine.</p>`;

  } else {

    const ul = H('ul', { class: 'ouinpo-skill-list' });



    const rank = {

      not_acquired: 0,

      in_progress: 1,

      consolidating: 2,

      acquired: 3

    };



    [...rows]

      .sort((a, b) => {

        const ra = rank[a.status] ?? 99;

        const rb = rank[b.status] ?? 99;

        if (ra !== rb) return ra - rb;

        return String(a.label || '').localeCompare(String(b.label || ''), 'fr');

      })

      .forEach((r) => {

        const li = H('li', { class: 'ouinpo-skill-row' });

        li.innerHTML = `

          <div class="ouinpo-skill-main">

            <span class="ouinpo-skill-ico">${escapeHTML(formatStatus(r.status).ico)}</span>

            <span class="ouinpo-skill-label">${escapeHTML(r.label || '')}</span>

          </div>

          <div class="ouinpo-skill-side">

            ${statusBadgeHTML(r.status)}

          </div>

        `;

        ul.appendChild(li);

      });



    body.appendChild(ul);

  }



  card.appendChild(summary);

  card.appendChild(body);



  return card;

}



function renderGlobalOverview(wrap, summary, domains, rows, segfaultCards = []) {

  const panel = $('#ouinpo-tab-global', wrap);

  if (!panel) return;



  const total = Number(summary.total || 0);

  const acquired = Number(summary.acquired || 0);

  const consolidating = Number(summary.consolidating || 0);

  const inProgress = Number(summary.in_progress || 0);

  const notAcquired = Number(summary.not_acquired || 0);



    const prioritiesFromSegfault = Array.isArray(segfaultCards) && segfaultCards.length > 0;

    const priorities = prioritiesFromSegfault

      ? segfaultCardsToPriorities(segfaultCards)

      : getGlobalPriorities(rows, 3);

    

    const indicator = total ? progressIndicator(summary) : 0;

    const showDetail = String(wrap.dataset.detail || '') === '1';



  panel.innerHTML = `

    <div class="ouinpo-global-dashboard">

      <section class="ouinpo-global-hero">

        <div class="ouinpo-global-hero-main">

          <p class="ouinpo-eyebrow">Tableau de bord</p>

          <h3>Ma progression en NSI</h3>

          <p class="ouinpo-global-intro">${escapeHTML(buildGlobalIntro(summary, priorities, prioritiesFromSegfault))}</p>

          <p class="ouinpo-global-subline">${escapeHTML(buildGlobalSubline(summary))}</p>



          <div class="ouinpo-global-chips">

            <span class="ouinpo-chip chip-acq">${acquired} acquis</span>

            <span class="ouinpo-chip chip-conso">${consolidating} en consolidation</span>

            <span class="ouinpo-chip chip-prog">${inProgress} en progression</span>

            <span class="ouinpo-chip chip-na">${notAcquired} non acquis</span>

          </div>

        </div>



        <div class="ouinpo-global-hero-side">

          <div class="ouinpo-progress-ring" style="--pct:${indicator}%;">

            <div class="ouinpo-progress-ring-inner">

              <strong>${indicator}%</strong>

              <span>progression globale</span>

            </div>

          </div>

        </div>

      </section>



      <section class="ouinpo-global-focus">

        <article class="ouinpo-focus-card">

          <h4>Révisions suggérées par SegFault</h4>

          <ul class="ouinpo-focus-list">

            ${

              priorities.length

                ? priorities

                    .map((r) => {

                        if (r.source === 'segfault') {

                          return `

                            <li class="ouinpo-focus-item ouinpo-focus-item-segfault">

                              <span class="ouinpo-focus-ico">${escapeHTML(segfaultCardIcon(r))}</span>

                        

                              <span class="ouinpo-focus-main">

                                <a class="ouinpo-focus-link" href="${escapeHTML(r.url || '#')}">

                                  ${escapeHTML(r.title || '')}

                                </a>

                        

                                <span class="ouinpo-focus-meta">

                                  ${r.badge ? `<span class="ouinpo-focus-badge">Nouveau</span>` : ''}

                                  ${r.difficulty ? `<span class="ouinpo-focus-difficulty">${escapeHTML(r.difficulty)}</span>` : ''}

                                </span>

                              </span>

                            </li>

                          `;

                        }

            

                      return `

                        <li>

                          <span class="ouinpo-focus-ico">${escapeHTML(formatStatus(r.status).ico)}</span>

                          <span class="ouinpo-focus-text">${escapeHTML(r.label || '')}</span>

                        </li>

                      `;

                    })

                    .join('')

                : `<li class="is-empty">Aucune priorité urgente : l’ensemble est plutôt stable.</li>`

            }

          </ul>

        </article>



        <article class="ouinpo-focus-card learning-advice">

          <h4>Conseil de progression</h4>

          <p>${escapeHTML(buildLearningAdvice(summary, priorities, prioritiesFromSegfault))}</p>

        </article>

      </section>



      <section class="ouinpo-global-domains">

        <div class="ouinpo-section-head">

          <h4>Progression par domaine</h4>

          <p>Chaque domaine peut être ouvert pour voir le détail des compétences.</p>

        </div>

        <div class="ouinpo-domain-stack"></div>

      </section>



      ${

        showDetail

          ? `

        <section class="ouinpo-global-allskills">

          <details class="ouinpo-allskills-details">

            <summary>Toutes mes compétences</summary>

            <div class="ouinpo-allskills-body">

              <ul class="ouinpo-allskills-list"></ul>

            </div>

          </details>

        </section>

      `

          : ''

      }

    </div>

  `;



  const host = $('.ouinpo-domain-stack', panel);

  if (!host) return;



  if (!domains || !domains.length) {

    host.innerHTML = '<p class="ouinpo-empty">Aucune compétence suivie pour cette année.</p>';

  } else {

    const byDom = new Map();



    (rows || []).forEach((r) => {

      const d = r.domain || 'Sans domaine';

      if (!byDom.has(d)) byDom.set(d, []);

      byDom.get(d).push(r);

    });



    domains.forEach((d) => {

      host.appendChild(renderDomainCard(d, byDom.get(d.domain) || []));

    });

  }



const allSkillsHost = $('.ouinpo-allskills-list', panel);

if (!allSkillsHost) return;



if (!rows || !rows.length) {

  allSkillsHost.innerHTML = '<li class="ouinpo-empty">Aucune compétence détaillée disponible.</li>';

  return;

}



const rank = {

  not_acquired: 0,

  in_progress: 1,

  consolidating: 2,

  acquired: 3

};



const byDomain = new Map();



(rows || []).forEach((r) => {

  const d = r.domain || 'Sans domaine';

  if (!byDomain.has(d)) byDomain.set(d, []);

  byDomain.get(d).push(r);

});



const domainsSorted = [...byDomain.keys()].sort((a, b) => a.localeCompare(b, 'fr'));



domainsSorted.forEach((domainName) => {

  const domainRows = [...byDomain.get(domainName)].sort((a, b) => {

    const ra = rank[a.status] ?? 99;

    const rb = rank[b.status] ?? 99;

    if (ra !== rb) return ra - rb;

    return String(a.label || '').localeCompare(String(b.label || ''), 'fr');

  });



  const counts = {

    acquired: 0,

    consolidating: 0,

    in_progress: 0,

    not_acquired: 0

  };



  domainRows.forEach((r) => {

    if (counts[r.status] !== undefined) counts[r.status] += 1;

  });



  const detailsEl = H('details', { class: 'ouinpo-allskills-domain' });

  const summaryEl = H('summary', { class: 'ouinpo-allskills-domain-summary' });



  summaryEl.innerHTML = `

    <div class="ouinpo-allskills-domain-main">

      <strong>${escapeHTML(domainName)}</strong>

      <span class="ouinpo-allskills-domain-count">${domainRows.length} compétence(s)</span>

    </div>

    <div class="ouinpo-allskills-domain-badges">

      <span class="ouinpo-mini-badge acq">${counts.acquired}</span>

      <span class="ouinpo-mini-badge conso">${counts.consolidating}</span>

      <span class="ouinpo-mini-badge prog">${counts.in_progress}</span>

      <span class="ouinpo-mini-badge na">${counts.not_acquired}</span>

    </div>

  `;



  const bodyEl = H('div', { class: 'ouinpo-allskills-domain-body' });

  const ul = H('ul', { class: 'ouinpo-skill-list' });



  domainRows.forEach((r) => {

    const li = H('li', { class: 'ouinpo-skill-row' });



    const lines = [];

    if (r.capacity) {

      lines.push(

        `<div class="ouinpo-tooltip-line"><span class="ouinpo-tooltip-label">Capacité :</span> ${escapeHTML(r.capacity)}</div>`

      );

    }

    if (r.example) {

      lines.push(

        `<div class="ouinpo-tooltip-line"><span class="ouinpo-tooltip-label">Exemple :</span> ${escapeHTML(r.example)}</div>`

      );

    }



    li.innerHTML = `

      <div class="ouinpo-skill-main">

        <span class="ouinpo-skill-ico">${escapeHTML(formatStatus(r.status).ico)}</span>

        <div class="ouinpo-skill-meta">

          <span class="ouinpo-tooltip-wrapper">

            <span class="ouinpo-skill-label">${escapeHTML(r.label || '')}</span>

            ${

              lines.length

                ? `

              <div class="ouinpo-tooltip">

                <div class="ouinpo-tooltip-title">Détail de la compétence</div>

                ${lines.join('')}

              </div>

            `

                : ''

            }

          </span>

        </div>

      </div>

      <div class="ouinpo-skill-side">

        ${statusBadgeHTML(r.status)}

      </div>

    `;



    ul.appendChild(li);

  });



  bodyEl.appendChild(ul);

  detailsEl.appendChild(summaryEl);

  detailsEl.appendChild(bodyEl);

  allSkillsHost.appendChild(detailsEl);

});



}

  function renderPriorities(wrap, priorities) {

    const host = $('#ds-priority-list', wrap);

    if (!host) return;



    if (!priorities || !priorities.length) {

      host.innerHTML = `

        <li class="ouin-li ouin-li-empty">

          <span class="ouin-li-label">

            Aucune priorité immédiate : belle stabilité sur les compétences évaluées.

          </span>

        </li>

      `;

      return;

    }



    host.innerHTML = '';

    priorities.forEach((row) => {

      const li = H('li', { class: 'ouin-li' });

      li.innerHTML = `

        <span class="ouin-li-ico">${formatStatus(row.current_status).ico}</span>

        <span class="ouin-li-label">${escapeHTML(row.label || '')}</span>

        <span class="ouin-li-status">${escapeHTML(formatStatus(row.current_status).label)}</span>

      `;

      host.appendChild(li);

    });

  }



function renderDsCompetencies(wrap, rows, isSingleAssessment) {

  const host = $('#ds-competencies-list', wrap);

  if (!host) return;



  if (!rows || !rows.length) {

    host.innerHTML = '<p>Aucun devoir noté enregistré pour le moment.</p>';

    return;

  }



  host.innerHTML = '';



  rows.forEach((row) => {

    const trend = trendMeta(row.trend);

    const card = H('article', { class: 'ouinpo-ds-card' });



    const history = (row.history || [])

      .map((item) => {

        const st = formatStatus(item.status);

        const date = formatDateFr(item.due_on);



        return `

          <li>

            <strong>${escapeHTML(item.assessment_title || '')}</strong>

            <span class="ouinpo-ds-date">(${escapeHTML(date)})</span>

            — ${escapeHTML(st.label)}

          </li>

        `;

      })

      .join('');



    const trendHtml = isSingleAssessment

      ? ''

      : `<p><strong>Tendance :</strong> <span class="ouinpo-trend ${trend.cls}">${trend.ico} ${escapeHTML(trend.label)}</span></p>`;



    const assessmentLabel = isSingleAssessment ? 'Devoir sélectionné' : 'Dernier devoir noté';

    const historySummary = isSingleAssessment ? 'Voir le détail du devoir' : 'Voir l’historique récent';



    card.innerHTML = `

      <h4>${escapeHTML(row.label || '')}</h4>

      <p class="ouinpo-ds-domain">${escapeHTML(row.domain || '')}</p>



      <p><strong>Statut actuel :</strong> ${statusBadgeHTML(row.current_status)}</p>

      ${trendHtml}

      <p><strong>${assessmentLabel} :</strong> ${escapeHTML(row.last_assessment || '—')}</p>



      <details class="ouinpo-ds-history">

        <summary>${historySummary}</summary>

        <ul>${history}</ul>

      </details>

    `;



    host.appendChild(card);

  });

}



function trainingDiagnostic(row) {

  const available = Number(row.available_count || 0);

  const attempted = Number(row.attempted_count || 0);

  const solved = Number(row.solved_count || 0);

  const success = Number(row.success_pct || 0);



  if (available === 0) {

    return {

      short: 'Aucun exercice lié',

      detail: 'Cette compétence est suivie, mais aucun exercice de ton niveau n’y est encore rattaché.'

    };

  }



  if (attempted === 0) {

    return {

      short: 'Compétence à découvrir',

      detail: 'Tu n’as pas encore travaillé cette compétence.'

    };

  }



  if (solved === 0) {

    return {

      short: 'Compétence à renforcer',

      detail: 'Tu as commencé à t’entraîner, mais aucune réussite n’est encore enregistrée.'

    };

  }



  if (attempted < 3 || success < 70) {

    return {

      short: 'Compétence en consolidation',

      detail: 'Tu réussis déjà certains exercices, mais il faut encore stabiliser tes acquis.'

    };

  }



  return {

    short: 'Compétence solide',

    detail: 'Tes réussites sont assez régulières sur cette compétence.'

  };

}



function trainingMetrics(row) {

  return {

    available: Number(row.available_count || 0),

    attempted: Number(row.attempted_count || 0),

    solved: Number(row.solved_count || 0),

    coverage: Number(row.coverage_pct || 0),

    success: Number(row.success_pct || 0),

    beginner: Number(row.solved_beginner_count || 0),

    confirmed: Number(row.solved_confirmed_count || 0),

    expert: Number(row.solved_expert_count || 0),

    status: String(row.current_status || '')

  };

}



function trainingPriorityRank(row) {

  const m = trainingMetrics(row);



  if (m.available === 0) return 99;



  const statusRank = {

    not_acquired: 0,

    in_progress: 1,

    consolidating: 2,

    acquired: 3

  };



  return statusRank[m.status] ?? 98;

}



function compareTrainingRows(a, b) {

  const ma = trainingMetrics(a);

  const mb = trainingMetrics(b);



  const ra = trainingPriorityRank(a);

  const rb = trainingPriorityRank(b);

  if (ra !== rb) return ra - rb;



  if (ma.status === 'not_acquired') {

    // 1. jamais tentée d’abord

    if ((ma.attempted === 0) !== (mb.attempted === 0)) {

      return ma.attempted === 0 ? -1 : 1;

    }



    // 2. parmi celles tentées, celles sans réussite d’abord

    if ((ma.solved === 0) !== (mb.solved === 0)) {

      return ma.solved === 0 ? -1 : 1;

    }



    // 3. ensuite les moins couvertes puis les moins réussies

    if (ma.coverage !== mb.coverage) return ma.coverage - mb.coverage;

    if (ma.success !== mb.success) return ma.success - mb.success;

    if (ma.attempted !== mb.attempted) return ma.attempted - mb.attempted;

  }



  else if (ma.status === 'in_progress') {

    // priorité aux compétences encore fragiles

    if (ma.success !== mb.success) return ma.success - mb.success;

    if (ma.solved !== mb.solved) return ma.solved - mb.solved;

    if (ma.coverage !== mb.coverage) return ma.coverage - mb.coverage;

    if (ma.attempted !== mb.attempted) return ma.attempted - mb.attempted;

  }



  else if (ma.status === 'consolidating') {

    // déjà bien engagées, on met d’abord celles à stabiliser

    if (ma.coverage !== mb.coverage) return ma.coverage - mb.coverage;

    if (ma.success !== mb.success) return ma.success - mb.success;

    if (ma.confirmed !== mb.confirmed) return ma.confirmed - mb.confirmed;

    if (ma.expert !== mb.expert) return ma.expert - mb.expert;

  }



  else if (ma.status === 'acquired') {

    // tout en bas, mais les acquisitions les moins robustes d’abord

    if (ma.expert !== mb.expert) return ma.expert - mb.expert;

    if (ma.confirmed !== mb.confirmed) return ma.confirmed - mb.confirmed;

    if (ma.coverage !== mb.coverage) return ma.coverage - mb.coverage;

    if (ma.success !== mb.success) return ma.success - mb.success;

  }



  const da = String(a.domain || '');

  const db = String(b.domain || '');

  if (da !== db) return da.localeCompare(db, 'fr');



  return String(a.label || '').localeCompare(String(b.label || ''), 'fr');

}



function sortTrainingRowsForStudent(rows) {

  return [...(rows || [])].sort(compareTrainingRows);

}





function renderTrainingCompetencies(wrap, rows) {

  const host = $('#training-competencies-list', wrap);

  if (!host) return;



  if (!rows || !rows.length) {

    host.innerHTML = '<p>Aucune compétence ne correspond au filtre sélectionné.</p>';

    return;

  }



  host.innerHTML = '';



  sortTrainingRowsForStudent(rows).forEach((row) => {

    const available = Number(row.available_count || 0);

    const attempted = Number(row.attempted_count || 0);

    const solved = Number(row.solved_count || 0);

    const coverage = Number(row.coverage_pct || 0);

    const success = Number(row.success_pct || 0);

    const beginner = Number(row.solved_beginner_count || 0);

    const confirmed = Number(row.solved_confirmed_count || 0);

    const expert = Number(row.solved_expert_count || 0);

    const diag = trainingDiagnostic(row);

    const priorityLabel = {

      not_acquired: 'Priorité forte',

      in_progress: 'À travailler',

      consolidating: 'À consolider',

      acquired: 'Acquis à entretenir'

    }[row.current_status] || 'À suivre';



    const coverageRest = Math.max(0, 100 - coverage);

    const successRest = Math.max(0, 100 - success);



    const card = H('article', { class: 'ouinpo-ds-card' });



    card.innerHTML = `

      <h4>${escapeHTML(row.label || '')}</h4>

      <p class="ouinpo-ds-domain">${escapeHTML(row.domain || '')}</p>



      <p><strong>État actuel :</strong> ${statusBadgeHTML(row.current_status)}</p>

      <p><strong>Priorité :</strong> ${escapeHTML(priorityLabel)}</p>

      <p><strong>Exercices :</strong> ${available} disponibles · ${attempted} tentés · ${solved} réussis</p>



      <div class="ouin-bar-row">

        <div class="ouin-bar-label">Couverture</div>

        <div class="ouin-bar">

          <span class="seg seg-prog" style="width:${coverage}%;">${coverage > 0 ? coverage + '%' : ''}</span>

          <span class="seg seg-na" style="width:${coverageRest}%;"></span>

        </div>

        <div class="ouin-bar-legend">${attempted}/${available}</div>

      </div>



      <div class="ouin-bar-row">

        <div class="ouin-bar-label">Réussite</div>

        <div class="ouin-bar">

          <span class="seg seg-acq" style="width:${success}%;">${success > 0 ? success + '%' : ''}</span>

          <span class="seg seg-na" style="width:${successRest}%;"></span>

        </div>

        <div class="ouin-bar-legend">${solved}/${attempted || 0}</div>

      </div>



      <p><strong>Réussites par niveau :</strong> Débutant ${beginner} · Confirmé ${confirmed} · Expert ${expert}</p>

      <p><strong>Lecture pédagogique :</strong> <span class="ouinpo-trend stable">${escapeHTML(diag.short)}</span> — ${escapeHTML(diag.detail)}</p>

    `;



    host.appendChild(card);

  });

}



function populateDsAssessmentFilter(wrap, options, selectedId) {

  const select = $('.js-ds-assessment-select', wrap);

  const block = $('.js-ds-assessment-filter', wrap);

  if (!select || !block) return;



  const normalized = String(selectedId || '');

  const current = select.value;

  const keep = normalized !== '' ? normalized : current;



  select.innerHTML = '<option value="">Tous les devoirs notés</option>';



  (options || []).forEach((row) => {

    const title = String(row.title || 'Devoir');

    const date = formatDateFr(row.due_on);

    const option = H(

      'option',

      { value: String(row.id) },

      `${escapeHTML(title)}${date !== '—' ? ' — ' + escapeHTML(date) : ''}`

    );

    if (String(row.id) === keep) option.selected = true;

    select.appendChild(option);

  });



  block.style.display = (options && options.length) ? '' : 'none';

}



async function loadDsView(wrap, year, group, assessmentId) {

  const params = {

    year_id: year,

    group_id: group || ''

  };



  if (assessmentId) {

    params.assessment_id = assessmentId;

  }



  const dsData = await getJSON(buildUrl('/me/assessments/progress', params));



  populateDsAssessmentFilter(

    wrap,

    dsData.assessment_options || [],

    dsData.selected_assessment_id || assessmentId || ''

  );



  setDsKPIs(dsData.summary || {}, wrap);

  renderPriorities(wrap, dsData.priorities || []);

  renderDsCompetencies(

    wrap,

    dsData.competencies || [],

    Number(dsData.selected_assessment_id || assessmentId || 0) > 0

  );

}  

function bindTabs(wrap) {

  const tabs = $$('.ouinpo-me-tab', wrap);

  const panels = $$('.ouinpo-me-panel', wrap);



  if (!tabs.length || !panels.length) return;



  tabs.forEach((btn) => {

    btn.addEventListener('click', () => {

      const tab = String(btn.dataset.tab || '');



      tabs.forEach((b) => b.classList.remove('is-active'));

      panels.forEach((p) => p.classList.remove('is-active'));



      btn.classList.add('is-active');



      const panel = $('#ouinpo-tab-' + tab, wrap);

      if (panel) panel.classList.add('is-active');

    });

  });

}



async function initOne(wrap) {

  const year = parseInt(wrap.dataset.year || '0', 10);

  const group = parseInt(wrap.dataset.group || '0', 10);



  bindTabs(wrap);



  if (!year || isNaN(year)) {

    setGlobalKPIs({}, wrap);

    setDsKPIs({}, wrap);

    setTrainingKPIs({}, wrap);



    const dHost = $('#domains-bars', wrap);

    if (dHost) dHost.innerHTML = '<p>Pas d’année scolaire sélectionnée.</p>';



    const dsHost = $('#ds-competencies-list', wrap);

    if (dsHost) dsHost.innerHTML = '<p>Pas d’année scolaire sélectionnée.</p>';



    const trainingHost = $('#training-competencies-list', wrap);

    if (trainingHost) trainingHost.innerHTML = '<p>Pas d’année scolaire sélectionnée.</p>';



    const oldDetail = $('#detail-block', wrap);

    if (oldDetail) oldDetail.style.display = 'none';



    return;

  }



  try {

    const [data, det, dsData, trainingData, segfaultData] = await Promise.all([

      getJSON(buildUrl('/me/competencies', {

        year_id: year,

        group_id: group || ''

      })),

      getJSON(buildUrl('/me/competencies/detail', {

        year_id: year,

        group_id: group || ''

      })),

      getJSON(buildUrl('/me/assessments/progress', {

        year_id: year,

        group_id: group || ''

      })),

      getJSON(buildUrl('/me/competencies/kpi', {

        year_id: year,

        group_id: group || ''

      })),

      getJSON(buildSegfaultParcoursUrl(3)).catch(() => ({ cards: [] }))

    ]);



    renderGlobalOverview(

      wrap,

      data.summary || {},

      data.domains || [],

      det.rows || [],

      segfaultData.cards || []

    );



    populateDsAssessmentFilter(

      wrap,

      dsData.assessment_options || [],

      dsData.selected_assessment_id || ''

    );

    setDsKPIs(dsData.summary || {}, wrap);

    renderPriorities(wrap, dsData.priorities || []);

    renderDsCompetencies(

      wrap,

      dsData.competencies || [],

      Number(dsData.selected_assessment_id || 0) > 0

    );



    setTrainingKPIs(trainingData.summary || {}, wrap);

    wrap._trainingRows = Array.isArray(trainingData.rows) ? trainingData.rows : [];

    populateTrainingFilters(wrap, wrap._trainingRows);

    bindTrainingFilters(wrap);

    refreshTrainingView(wrap);



    const dsSelect = $('.js-ds-assessment-select', wrap);

    if (dsSelect && !dsSelect.dataset.bound) {

      dsSelect.dataset.bound = '1';

      dsSelect.addEventListener('change', async () => {

        const selected = dsSelect.value || '';

        try {

          await loadDsView(wrap, year, group, selected);

        } catch (err) {

          console.error('Ouinpo student devoir filter error:', err);

          const dsHost = $('#ds-competencies-list', wrap);

          if (dsHost) dsHost.innerHTML = '<p>Impossible de charger ce devoir.</p>';

        }

      });

    }

  } catch (e) {

    console.error('Ouinpo student progression error:', e);



    setGlobalKPIs({}, wrap);

    setDsKPIs({}, wrap);

    setTrainingKPIs({}, wrap);



    const globalPanel = $('#ouinpo-tab-global', wrap);

    if (globalPanel) {

      globalPanel.innerHTML = `

        <div class="ouinpo-global-dashboard">

          <p class="ouinpo-empty">Impossible de charger la progression globale.</p>

        </div>

      `;

    }



    const dsHost = $('#ds-competencies-list', wrap);

    if (dsHost) dsHost.innerHTML = '<p>Impossible de charger les données des devoirs notés.</p>';



    const pHost = $('#ds-priority-list', wrap);

    if (pHost) {

      pHost.innerHTML = `

        <li class="ouin-li ouin-li-empty">

          <span class="ouin-li-label">Impossible de charger les priorités.</span>

        </li>

      `;

    }



    const trainingHost = $('#training-competencies-list', wrap);

    if (trainingHost) {

      trainingHost.innerHTML = '<p>Impossible de charger les données d’exercices.</p>';

    }

  }

}

  function init() {

    const wraps = $$('.ouinpo-competences');

    if (!wraps.length) return;

    wraps.forEach(initOne);

  }



  document.addEventListener('DOMContentLoaded', init);



  document.addEventListener('click', (e) => {

    const wrap = e.target.closest('.ouinpo-tooltip-wrapper');

    if (!wrap) {

      document

        .querySelectorAll('.ouinpo-tooltip-wrapper.active')

        .forEach((w) => w.classList.remove('active'));

      return;

    }

    wrap.classList.toggle('active');

  });

})();
