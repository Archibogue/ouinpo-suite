(function () {
    window.OUINEXO = window.OUINEXO || {
    api: window.location.origin + '/wp-json/',
    nonce: '',
    is_logged_in: '0'
  };



  // Helpers REST

  function restRoot() {
    const base =
      (window.OUINEXO && OUINEXO.api)
        ? OUINEXO.api
        : (window.location.origin + '/wp-json/');

    return base.replace(/\/+$/, '') + '/ouinpo/v1';
  }

  function restUrl(path) {
    const base = restRoot();

    const parts = String(path || '').split('?');
    const routePath = parts[0] || '';
    const query = parts.slice(1).join('?');

    let url = base + routePath;

    if (query) {
      url += (url.indexOf('?') === -1 ? '?' : '&') + query;
    }

    return url;
  }

  function commonHeaders() {
    const h = { 'Accept': 'application/json' };

    const isLoggedIn =
      !!(window.OUINEXO && String(OUINEXO.is_logged_in || '0') === '1') ||
      document.body.classList.contains('logged-in');

    if (isLoggedIn && window.OUINEXO && OUINEXO.nonce) {
      h['X-WP-Nonce'] = OUINEXO.nonce;
    }

    return h;
  }

  async function apiGET(path) {
    const res = await fetch(restUrl(path), {
      headers: commonHeaders(),
      credentials: 'include'
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
  }



  async function apiPOST(path, body) {

  const res = await fetch(restUrl(path), {

    method: 'POST',

    headers: Object.assign({ 'Content-Type': 'application/json' }, commonHeaders()),

    credentials: 'include',

    body: JSON.stringify(body || {})

  });



  let data = null;



  try {

    data = await res.json();

  } catch (_) {

    data = null;

  }



  if (!res.ok) {

    const message =

      (data && data.message) ||

      (data && data.error) ||

      'Évaluation impossible pour le moment.';



    const err = new Error(message);

    err.status = res.status;

    err.data = data;

    throw err;

  }



  return data;

}



  function escapeHtml(s) {

    return String(s).replace(/[&<>"']/g, c => ({

      '&': '&amp;',

      '<': '&lt;',

      '>': '&gt;',

      '"': '&quot;',

      "'": '&#39;'

    }[c]));

  }



  function renderMessage(container, text, className) {

    if (!container) return;

    container.innerHTML = `<div class="${className || 'ouinpo-empty'}">${escapeHtml(text)}</div>`;

  }



  // URL cible pour un exo

  function linkTarget(basePage, id) {
    const url = basePage && basePage.length ? basePage : window.location.href;
    const u = new URL(url, window.location.origin);

    u.searchParams.set('exo', String(id));

    return u.toString();
  }



  function difficultyLabelFromSlug(slug) {

    const map = {

      debutant: 'Débutant',

      confirme: 'Confirmé',

      expert: 'Expert'

    };

    return map[String(slug || '').toLowerCase()] || '';

  }



  function difficultyRankFromSlug(slug) {

    const map = {

      debutant: 1,

      confirme: 2,

      expert: 3

    };

    return map[String(slug || '').toLowerCase()] || 99;

  }



  function sourceTypeLabel(sourceType) {

    const map = {

      annale: 'Annale',

      inspired: 'Inspiré annale',

      type_bac: 'Type bac'

    };

    return map[String(sourceType || '').toLowerCase()] || '';

  }



  function themeBacLabel(theme) {

    const map = {

      algorithmique: 'Algorithmique',

      programmation: 'Programmation',

      structures_de_donnees: 'Structures de données',

      bases_de_donnees_sql: 'Bases de données / SQL',

      reseaux_securite: 'Réseaux et sécurité',

      architecture_systemes: 'Architecture et systèmes'

    };

    return map[String(theme || '').toLowerCase()] || String(theme || '');

  }



  function bacFormatLabel(format) {

    const map = {

      question_courte: 'Question courte',

      lecture_code: 'Lecture de code',

      code_a_completer: 'Code à compléter',

      ecriture_complete: 'Écriture complète',

      raisonnement: 'Raisonnement'

    };

    return map[String(format || '').toLowerCase()] || String(format || '');

  }



  function statusLabel(status) {

    if (status === 'solved') return 'Réussi ✅';

    if (status === 'attempted') return 'Tenté';

    return '';

  }



  function statusClass(status) {

    if (status === 'solved') return 'ouinpo-badge--solved';

    if (status === 'attempted') return 'ouinpo-badge--attempted';

    return 'ouinpo-badge--none';

  }



  function normalizedDomainLabel(slug, fallbackLabel, labelsBySlug) {

    if (fallbackLabel) return fallbackLabel;

    if (labelsBySlug && labelsBySlug[slug]) return labelsBySlug[slug];

    if (!slug || slug === '_none') return 'Tous les exercices';

    return slug;

  }



  function buildMetaChip(text) {

    return `<span class="ouinpo-chip">${escapeHtml(text)}</span>`;

  }



function buildExamSubtitleHtml(ex) {

  let center = String(ex.centerLabel || '').trim();

  let session = String(ex.sessionLabel || '').trim();

  const year = String(ex.yearLabel || '').trim();



  // Cas où session_label contient déjà tout :

  // "Centres étrangers Afrique 2 — 13 juin 2025"

  if (center && session.startsWith(center)) {

    session = session.slice(center.length).replace(/^\s*[—–-]\s*/, '').trim();

  }



  // Cas où center_label est vide mais session_label contient centre + date

  if (!center && /[—–-]/.test(session)) {

    const parts = session.split(/\s*[—–-]\s*/);

    if (parts.length >= 2) {

      center = parts.shift().trim();

      session = parts.join(' — ').trim();

    }

  }



  // Si l'année n'est pas déjà dans session, on l'ajoute

  if (year && session && !session.includes(year)) {

    session += ' ' + year;

  }



  const chunks = session ? session.split(/\s+/).filter(Boolean) : [];



  let html = '';



  if (center) {

    html += `<span class="ouinpo-exam-center">${escapeHtml(center)}</span>`;

  }



  if (center && chunks.length) {

    html += ` <span class="ouinpo-exam-sep" aria-hidden="true">—</span> `;

  }



  if (chunks.length) {

    html += `<span class="ouinpo-exam-date">` +

      chunks.map(function (part) {

        return `<span class="ouinpo-exam-date-part">${escapeHtml(part)}</span>`;

      }).join(' ') +

      `</span>`;

  }



  return html;

}

  function renderExercisesList(container, items, opts) {

  if (!container) return;



  const basePage = container.dataset.exoPage || '';

  const domainLabelsBySlug = (opts && opts.domainLabelsBySlug) ? opts.domainLabelsBySlug : {};

  const currentDomainSlug = (opts && opts.currentDomainSlug) ? opts.currentDomainSlug : '';

  const currentFilters = (opts && opts.currentFilters) ? opts.currentFilters : {};



  if (!items || !items.length) {

    renderMessage(container, 'Aucun exercice trouvé.', 'ouinpo-empty');

    return;

  }

  

  const domainMap = Object.create(null);



  items.forEach(function (it) {

    if (!it || !it.id) return;



    let dSlug = it.domain_slug || '';

    if (!dSlug && currentDomainSlug) dSlug = currentDomainSlug;

    if (!dSlug) dSlug = '_none';



    const dLabel = normalizedDomainLabel(

      dSlug,

      it.domain || it.domain_label || '',

      domainLabelsBySlug

    );



    if (!domainMap[dSlug]) {

      domainMap[dSlug] = {

        slug: dSlug,

        label: dLabel,

        seenIds: new Set(),

        exos: []

      };

    }



    const group = domainMap[dSlug];

    if (group.seenIds.has(it.id)) return;

    group.seenIds.add(it.id);



    const diffSlug = String(it.difficulty_slug || it.difficulty || '').toLowerCase();

    const diffLabel = it.difficulty_label || difficultyLabelFromSlug(diffSlug);



    group.exos.push({

      id: it.id,

      title: it.title || 'Exercice',

      status: it.status || 'none',

      diffSlug: diffSlug,

      diffLabel: diffLabel,

      diffRank: difficultyRankFromSlug(diffSlug),

      isExamLike: !!Number(it.is_exam_like || 0),

      sourceType: String(it.source_type || '').toLowerCase(),

      sessionLabel: String(it.session_label || ''),

      yearLabel: String(it.year_label || ''),

      centerLabel: String(it.center_label || ''),

      themeBac: String(it.theme_bac || ''),

      bacFormat: String(it.bac_format || '').toLowerCase()

    });

  });



  const domains = Object.values(domainMap)

    .filter(function (d) { return d.exos && d.exos.length; })

    .sort(function (a, b) {

      return a.label.localeCompare(b.label, 'fr', { sensitivity: 'base' });

    });



  if (!domains.length) {

    renderMessage(container, 'Aucun exercice trouvé.', 'ouinpo-empty');

    return;

  }



  const totalExercises = domains.reduce(function (sum, d) {

    return sum + d.exos.length;

  }, 0);



  let html = '';



  const chips = [];

  chips.push(buildMetaChip(totalExercises + ' exercice' + (totalExercises > 1 ? 's' : '')));



    if (currentFilters.examLabel) {

      chips.push(buildMetaChip('Type : ' + currentFilters.examLabel));

    }

    if (currentFilters.sourceLabel) {

      chips.push(buildMetaChip('Origine : ' + currentFilters.sourceLabel));

    }

    if (currentFilters.themeBacLabel) {

      chips.push(buildMetaChip('Thème bac : ' + currentFilters.themeBacLabel));

    }

    if (currentFilters.bacFormatLabel) {

      chips.push(buildMetaChip('Format bac : ' + currentFilters.bacFormatLabel));

    }

    if (currentFilters.levelLabel) {

      chips.push(buildMetaChip('Niveau : ' + currentFilters.levelLabel));

    }

    if (currentFilters.difficultyLabel) {

      chips.push(buildMetaChip('Difficulté : ' + currentFilters.difficultyLabel));

    }

    if (currentFilters.domainLabel) {

      chips.push(buildMetaChip('Domaine : ' + currentFilters.domainLabel));

    }

    if (currentFilters.competencyLabel) {

      chips.push(buildMetaChip('Compétence : ' + currentFilters.competencyLabel));

    }





  html += `<div class="ouinpo-exercises-meta">${chips.join('')}</div>`;



  domains.forEach(function (domain) {

    const title = (domain.slug === '_none') ? 'Tous les exercices' : domain.label;



    html += `<section class="ouinpo-exo-domain-block">`;

    html += `<h3 class="ouinpo-exo-domain-title">${escapeHtml(title)}</h3>`;

    html += `<ul class="ouinpo-exercises-list ouinpo-exo-list">`;



    domain.exos

      .sort(function (a, b) {

        if (a.diffRank !== b.diffRank) return a.diffRank - b.diffRank;

        return a.title.localeCompare(b.title, 'fr', { sensitivity: 'base' });

      })

      .forEach(function (ex) {

        html += `<li class="ouinpo-exercise-item ouin-exo-li">`;

        html += `  <div class="ouinpo-exercise-main ouin-exo-main">`;

        html += `    <a class="ouinpo-exercise-link ouin-exo-link" href="${escapeHtml(linkTarget(basePage, ex.id))}">${escapeHtml(ex.title)}</a>`;



        const examSubtitleHtml = buildExamSubtitleHtml(ex);

        

        if (examSubtitleHtml) {

          html += `    <div class="ouinpo-exercise-sub">${examSubtitleHtml}</div>`;

        } else {

          const subBits = [];

          if (ex.diffLabel) subBits.push(ex.diffLabel);

          if (currentDomainSlug && domain.label) subBits.push(domain.label);

        

          if (subBits.length) {

            html += `    <div class="ouinpo-exercise-sub">${escapeHtml(subBits.join(' • '))}</div>`;

          }

        }



        html += `  </div>`;



        html += `  <div class="ouinpo-badges ouin-exo-status">`;



        if (ex.isExamLike && ex.sourceType) {

          html += `    <span class="ouinpo-badge ouinpo-badge--exam">${escapeHtml(sourceTypeLabel(ex.sourceType) || 'Type bac')}</span>`;

        } else if (ex.isExamLike) {

          html += `    <span class="ouinpo-badge ouinpo-badge--exam">Type bac</span>`;

        }



        if (ex.bacFormat) {

          html += `    <span class="ouinpo-badge ouinpo-badge--competency">${escapeHtml(bacFormatLabel(ex.bacFormat))}</span>`;

        }



        if (ex.diffLabel) {

          html += `    <span class="ouinpo-badge ouinpo-badge--difficulty">${escapeHtml(ex.diffLabel)}</span>`;

        }



        if (ex.status && ex.status !== 'none') {

          html += `    <span class="ouinpo-badge ${statusClass(ex.status)} exo-badge ${ex.status === 'solved' ? 'badge-solved' : 'badge-attempt'}">${escapeHtml(statusLabel(ex.status))}</span>`;

        }



        html += `  </div>`;

        html += `</li>`;

      });



    html += `</ul>`;

    html += `</section>`;

  });



  container.innerHTML = html;

}

  // ----- Filtres liste d'exercices -----



async function buildFiltersAndLoad(rootForList) {

  if (!rootForList) return;



  const existing = document.getElementById('ouinpo-exo-dynamic-filters');

  if (existing) existing.remove();



const schoolLevel = (rootForList.dataset.level || '');

const schoolLevelLabel = (rootForList.dataset.levelLabel || '');

const presetExamOnly = (rootForList.dataset.examOnly || '');

  const isExamMode = (presetExamOnly === '1');



  const filtersWrap = document.createElement('section');

  filtersWrap.id = 'ouinpo-exo-dynamic-filters';

  filtersWrap.className = 'ouinpo-panel ouinpo-panel--filters ouinpo-exo-filters';



  const filtersHtmlDefault = `

    <h2 class="ouinpo-panel-title">Filtrer les exercices</h2>

    <div class="ouinpo-filters-grid">

      <div class="ouinpo-field">

        <label for="ouinpo-filter-difficulty">Difficulté</label>

        <select id="ouinpo-filter-difficulty" class="ouinpo-select" data-filter="difficulty">

          <option value="">Toutes</option>

          <option value="debutant">Débutant</option>

          <option value="confirme">Confirmé</option>

          <option value="expert">Expert</option>

        </select>

      </div>



      <div class="ouinpo-field">

        <label for="ouinpo-filter-domain">Domaine BO</label>

        <select id="ouinpo-filter-domain" class="ouinpo-select" data-filter="domain_slug">

          <option value="">Tous les domaines</option>

        </select>

      </div>



      <div class="ouinpo-field">

        <label for="ouinpo-filter-competency">Compétence BO</label>

        <select id="ouinpo-filter-competency" class="ouinpo-select" data-filter="competency_id">

          <option value="">Toutes les compétences</option>

        </select>

      </div>

    </div>

  `;



  const filtersHtmlExam = `

    <h2 class="ouinpo-panel-title">Filtrer les exercices</h2>

    <div class="ouinpo-filters-grid">

      <div class="ouinpo-field">

        <label for="ouinpo-filter-source">Origine</label>

        <select id="ouinpo-filter-source" class="ouinpo-select" data-filter="source_type">

          <option value="">Toutes</option>

          <option value="annale">Annales</option>

          <option value="inspired">Inspirés d’annales</option>

          <option value="type_bac">Type bac</option>

        </select>

      </div>



      <div class="ouinpo-field">

        <label for="ouinpo-filter-theme-bac">Thème bac</label>

        <select id="ouinpo-filter-theme-bac" class="ouinpo-select" data-filter="theme_bac">

          <option value="">Tous les thèmes</option>

          <option value="algorithmique">Algorithmique</option>

          <option value="programmation">Programmation</option>

          <option value="structures_de_donnees">Structures de données</option>

          <option value="bases_de_donnees_sql">Bases de données / SQL</option>

          <option value="reseaux_securite">Réseaux et sécurité</option>

          <option value="architecture_systemes">Architecture et systèmes</option>

        </select>

      </div>



      <div class="ouinpo-field">

        <label for="ouinpo-filter-bac-format">Format bac</label>

        <select id="ouinpo-filter-bac-format" class="ouinpo-select" data-filter="bac_format">

          <option value="">Tous les formats</option>

          <option value="question_courte">Question courte</option>

          <option value="lecture_code">Lecture de code</option>

          <option value="code_a_completer">Code à compléter</option>

          <option value="ecriture_complete">Écriture complète</option>

          <option value="raisonnement">Raisonnement</option>

        </select>

      </div>



      <div class="ouinpo-field">

        <label for="ouinpo-filter-difficulty">Difficulté</label>

        <select id="ouinpo-filter-difficulty" class="ouinpo-select" data-filter="difficulty">

          <option value="">Toutes</option>

          <option value="debutant">Débutant</option>

          <option value="confirme">Confirmé</option>

          <option value="expert">Expert</option>

        </select>

      </div>



      <div class="ouinpo-field">

        <label for="ouinpo-filter-domain">Domaine BO</label>

        <select id="ouinpo-filter-domain" class="ouinpo-select" data-filter="domain_slug">

          <option value="">Tous les domaines</option>

        </select>

      </div>



      <div class="ouinpo-field">

        <label for="ouinpo-filter-competency">Compétence BO</label>

        <select id="ouinpo-filter-competency" class="ouinpo-select" data-filter="competency_id">

          <option value="">Toutes les compétences</option>

        </select>

      </div>

    </div>

  `;



  filtersWrap.innerHTML = isExamMode ? filtersHtmlExam : filtersHtmlDefault;



  const shell = rootForList.closest('.ouinpo-exercises-shell');

  const resultsPanel = rootForList.closest('.ouinpo-panel--results');

  const filtersSlot = document.getElementById('ouinpo-exo-dynamic-filters-slot');



  if (shell && filtersSlot && filtersSlot.parentNode === shell) {

    while (filtersSlot.firstChild) {
      filtersSlot.removeChild(filtersSlot.firstChild);
    }

    filtersSlot.appendChild(filtersWrap);

  } else if (shell && resultsPanel && resultsPanel.parentNode === shell) {

    shell.insertBefore(filtersWrap, resultsPanel);

  } else if (rootForList.parentNode) {

    rootForList.parentNode.insertBefore(filtersWrap, rootForList);

  }



  const selExam = filtersWrap.querySelector('select[data-filter="exam_only"]');

  const selSource = filtersWrap.querySelector('select[data-filter="source_type"]');

  const selThemeBac = filtersWrap.querySelector('select[data-filter="theme_bac"]');

  const selBacFormat = filtersWrap.querySelector('select[data-filter="bac_format"]');

  const selDifficulty = filtersWrap.querySelector('select[data-filter="difficulty"]');

  const selDomain = filtersWrap.querySelector('select[data-filter="domain_slug"]');

  const selComp = filtersWrap.querySelector('select[data-filter="competency_id"]');



  if (selExam && presetExamOnly) {

    selExam.value = presetExamOnly;

  }



  let optionsData = { domains: [], competencies: [] };



  try {

    const params = new URLSearchParams();

    if (schoolLevel) params.set('school_level', schoolLevel);

    optionsData = await apiGET('/competencies/options' + (params.toString() ? '?' + params.toString() : ''));

  } catch (e) {

    console.error('[ouinpo] Erreur chargement domaines/compétences', e);

  }



  const domainLabelsBySlug = {};



  if (Array.isArray(optionsData.domains) && selDomain) {

    optionsData.domains.forEach(function (d) {

      const opt = document.createElement('option');

      opt.value = d.slug;

      opt.textContent = d.label;

      selDomain.appendChild(opt);

      domainLabelsBySlug[d.slug] = d.label;

    });

  }



  function refreshCompetenciesOptions() {

    if (!selComp) return;



    const currentDomain = selDomain ? selDomain.value : '';



    selComp.innerHTML = '';

    const defaultOpt = document.createElement('option');

    defaultOpt.value = '';

    defaultOpt.textContent = 'Toutes les compétences';

    selComp.appendChild(defaultOpt);



    if (!Array.isArray(optionsData.competencies)) return;



    optionsData.competencies

      .filter(function (c) {

        return !currentDomain || c.domain_slug === currentDomain;

      })

      .forEach(function (c) {

        const opt = document.createElement('option');

        opt.value = String(c.id);

        opt.textContent = '[' + c.domain + '] ' + c.label;

        selComp.appendChild(opt);

      });

  }



  function selectedText(selectEl, emptyText) {

    if (!selectEl) return '';

    const idx = selectEl.selectedIndex;

    if (idx < 0) return '';

    const txt = (selectEl.options[idx] && selectEl.options[idx].textContent) ? selectEl.options[idx].textContent.trim() : '';

    if (!selectEl.value) return '';

    return txt || emptyText || '';

  }



  refreshCompetenciesOptions();



  async function reloadList() {

    const params = new URLSearchParams();

    

    if (rootForList.dataset.logged === '1') {

      params.set('include_status', '1');

    }



    const examVal = selExam ? selExam.value : (isExamMode ? '1' : '');

    const sourceVal = selSource ? selSource.value : '';

    const themeBacVal = selThemeBac ? selThemeBac.value : '';

    const bacFormatVal = selBacFormat ? selBacFormat.value : '';

    const diffVal = selDifficulty ? selDifficulty.value : '';

    const domVal = selDomain ? selDomain.value : '';

    const compVal = selComp ? selComp.value : '';



    if (examVal) params.set('exam_only', examVal);

    if (sourceVal) params.set('source_type', sourceVal);

    if (themeBacVal) params.set('theme_bac', themeBacVal);

    if (bacFormatVal) params.set('bac_format', bacFormatVal);

    if (diffVal) params.set('difficulty', diffVal);

    if (domVal) params.set('domain_slug', domVal);

    if (compVal) params.set('competency_id', compVal);

    if (schoolLevel) params.set('school_level', schoolLevel);



    renderMessage(rootForList, 'Chargement des exercices…', 'ouinpo-loading');



    try {

      const items = await apiGET('/exercises' + (params.toString() ? '?' + params.toString() : ''));



      renderExercisesList(rootForList, items, {

        domainLabelsBySlug: domainLabelsBySlug,

        currentDomainSlug: domVal || '',

        currentFilters: {

          examLabel: isExamMode ? 'Exercices type bac' : selectedText(selExam),

          sourceLabel: selectedText(selSource),

          themeBacLabel: selectedText(selThemeBac),

          bacFormatLabel: selectedText(selBacFormat),

            levelLabel: schoolLevelLabel,

            difficultyLabel: selectedText(selDifficulty),

            domainLabel: selectedText(selDomain),

          competencyLabel: selectedText(selComp)

        }

      });

    } catch (e) {

      console.error('[ouinpo] Erreur de chargement des exercices', e);

      renderMessage(rootForList, 'Erreur de chargement des exercices.', 'ouinpo-empty');

    }

  }



  if (selExam) {

    selExam.addEventListener('change', reloadList);

  }

  if (selSource) {

    selSource.addEventListener('change', reloadList);

  }

  if (selThemeBac) {

    selThemeBac.addEventListener('change', reloadList);

  }

  if (selBacFormat) {

    selBacFormat.addEventListener('change', reloadList);

  }  

  if (selDifficulty) {

    selDifficulty.addEventListener('change', reloadList);

  }

  if (selDomain) {

    selDomain.addEventListener('change', function () {

      refreshCompetenciesOptions();

      if (selComp) selComp.value = '';

      reloadList();

    });

  }

  if (selComp) {

    selComp.addEventListener('change', reloadList);

  }



  reloadList();

}

  // Rendu bloc HTML

  function appendBlock(zone, title, html, extraAttrs) {

    const wrap = document.createElement('div');



    const extraClass = extraAttrs && extraAttrs.class ? String(extraAttrs.class) : '';

    wrap.className = extraClass ? extraClass : 'ouin-exo-block';



    if (extraAttrs) {

      Object.keys(extraAttrs).forEach(function (k) {

        if (k === 'class') return;

        wrap.setAttribute(k, String(extraAttrs[k]));

      });

    }



    wrap.innerHTML = `<h5>${escapeHtml(title)}</h5><div class="ouin-exo-block-content"></div>`;

    wrap.querySelector('.ouin-exo-block-content').innerHTML = html || '<em>(vide)</em>';



    zone.appendChild(wrap);

    wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });

    return wrap;

  }



  function ensureActions(root) {

    let actions = root.querySelector('.exo-actions');

    if (!actions) {

      actions = document.createElement('div');

      actions.className = 'exo-actions';

      actions.innerHTML = `

        <button type="button" class="mark-attempt">J’ai tenté</button>

        <button type="button" class="mark-solved">J’ai réussi</button>

        <span class="exo-badge"></span>

      `;

      const before = root.querySelector('.exo-reveal');

      if (before && before.parentNode) before.parentNode.insertBefore(actions, before);

      else root.appendChild(actions);

    }

    return {

      el: actions,

      btnAttempt: actions.querySelector('.mark-attempt'),

      btnSolved: actions.querySelector('.mark-solved'),

      badge: actions.querySelector('.exo-badge')

    };

  }



  function ensureStatusBadge(root) {

    let line = root.querySelector('.exo-status-line');

    if (!line) {

      line = document.createElement('div');

      line.className = 'exo-status-line';

      line.innerHTML = '<span class="exo-badge"></span>';



      const before = root.querySelector('.exo-reveal');

      if (before && before.parentNode) before.parentNode.insertBefore(line, before);

      else root.appendChild(line);

    }



    return {

      el: line,

      badge: line.querySelector('.exo-badge')

    };

  }



  function insertCodeBlock(textarea) {

    if (!textarea) return;



    const start = textarea.selectionStart || 0;

    const end = textarea.selectionEnd || 0;

    const value = textarea.value || '';

    const selected = value.slice(start, end);

    const block = selected

      ? `[code]

${selected}

[/code]`

      : `[code]



[/code]`;



    textarea.value = value.slice(0, start) + block + value.slice(end);



    const cursorPos = selected

      ? start + block.length

      : start + `[code]

`.length;



    textarea.focus();

    textarea.setSelectionRange(cursorPos, cursorPos);

  }



  function renderAiFeedback(container, data) {

    if (!container) return;



    const verdictMap = {

      correct: 'Réponse correcte ✅',

      partial: 'Réponse partiellement correcte 🟡',

      incorrect: 'Réponse à revoir ❌'

    };



    const verdict = (data && data.verdict) ? String(data.verdict) : 'incorrect';

    const title = verdictMap[verdict] || 'Retour de SegFault';



    let html = `<div class="ouin-exo-block ai-feedback-block">`;

    html += `<h5>${escapeHtml(title)}</h5>`;

    html += `<div class="ouin-exo-block-content">`;



    if (data && data.feedback) {

      html += `<p>${escapeHtml(data.feedback)}</p>`;

    } else {

      html += `<p>Retour indisponible.</p>`;

    }



    if (data && Array.isArray(data.next_steps) && data.next_steps.length) {

      html += `<p><strong>Pistes :</strong></p><ul>`;

      data.next_steps.forEach(function (step) {

        html += `<li>${escapeHtml(step)}</li>`;

      });

      html += `</ul>`;

    }



    if (data && Array.isArray(data.style_warnings) && data.style_warnings.length) {

      html += `<p><strong>Bonnes pratiques Python :</strong></p><ul>`;

      data.style_warnings.forEach(function (item) {

        html += `<li>${escapeHtml(item)}</li>`;

      });

      html += `</ul>`;

    }



    html += `</div></div>`;

    container.innerHTML = html;

  }



  // --- Helpers solution officielle (toggle)

  function isOfficialSolution(s) {

    return (s && (s.is_official === 1 || s.is_official === '1' || s.is_official === true || s.is_official === 'true'));

  }



  function officialLabel(s) {

    return (s.title || 'Corrigé') + ' ⭐ Officielle';

  }



  function setOfficialButtonState(btn, visible, s) {

    if (!btn) return;

    btn.dataset.officialVisible = visible ? '1' : '0';

    btn.textContent = visible ? 'Masquer la solution' : officialLabel(s);

  }



  // Charge le détail d’un exo

  async function loadExerciseDetail(root) {

    const id = parseInt(root.getAttribute('data-exo-id'), 10);

    if (!id) return;



    const isLogged = (root.dataset.logged === '1');

    const canShowStatusActions = (root.dataset.showStatusActions === '1');



    const statementEl = root.querySelector('.exo-statement');

    const revealZone = root.querySelector('.exo-reveal');

    const btn1 = root.querySelector('button[data-hint="1"]');

    const btn2 = root.querySelector('button[data-hint="2"]');

    const btn3 = root.querySelector('button[data-hint="3"]');

    const solList = root.querySelector('.solutions-list');

    const answerText = root.querySelector('.exo-answer-text');

    const insertCodeBtn = root.querySelector('.exo-insert-code');

    const submitAnswer = root.querySelector('.exo-submit-answer');

    const aiFeedbackEl = root.querySelector('.exo-ai-feedback');



    let btnAttempt = null;

    let btnSolved = null;

    let badge = null;

    let currentStatus = 'none';



    let revealedHintOrders = new Set();

    let revealedSolutionIds = new Set();



    function paintStatus(s) {

      currentStatus = s || 'none';

      if (!badge) return;



      badge.className = 'exo-badge';



      if (currentStatus === 'attempted') {

        badge.textContent = 'Tenté';

        badge.classList.add('badge-attempt');

      } else if (currentStatus === 'solved') {

        badge.textContent = 'Réussi ✅';

        badge.classList.add('badge-solved');

      } else {

        badge.textContent = '';

      }

    }



    function toggleActButtons(disabled) {

      if (btnAttempt) btnAttempt.disabled = disabled;

      if (btnSolved) btnSolved.disabled = disabled;

    }



    function canAccessSolutions() {

      // Visiteur : accès libre aux corrigés, sans suivi.

      if (!isLogged) {

        return true;

      }

    

      // Élève connecté : on conserve le fonctionnement actuel.

      // Le corrigé se débloque après tentative ou réussite.

      return currentStatus === 'attempted' || currentStatus === 'solved';

    }

    

    function refreshSolutionButtons() {

      if (!solList) return;



      solList.querySelectorAll('button[data-solution-id]').forEach(function (btn) {

        const isOfficial = btn.dataset.isOfficial === '1';

        const alreadySeen = btn.dataset.alreadySeen === '1';

        const officialVisible = btn.dataset.officialVisible === '1';



        if (!canAccessSolutions()) {

          btn.disabled = true;

          btn.title = 'Le corrigé se débloque après une tentative ou une réussite.';

          return;

        }



        if (!isOfficial && alreadySeen) {

          btn.disabled = true;

          btn.title = 'Déjà affiché';

          return;

        }



        btn.disabled = false;

        btn.title = '';



        if (isOfficial && officialVisible) {

          btn.disabled = false;

        }

      });

    }



    function addSolutionButton(solution, alreadySeen) {

      if (!solList) return;



      const li = document.createElement('li');

      const btn = document.createElement('button');

      const official = isOfficialSolution(solution);

      const sid = parseInt(solution.id, 10);



      btn.type = 'button';

      btn.dataset.solutionId = String(solution.id);

      btn.dataset.isOfficial = official ? '1' : '0';

      btn.dataset.alreadySeen = alreadySeen ? '1' : '0';

      btn.dataset.officialVisible = (official && alreadySeen) ? '1' : '0';



      btn.textContent = official ? officialLabel(solution) : (solution.title || 'Corrigé');



      if (alreadySeen) {

        if (official) {

          setOfficialButtonState(btn, true, solution);

        } else {

          btn.disabled = true;

          btn.title = 'Déjà affiché';

        }

      } else if (official) {

        setOfficialButtonState(btn, false, solution);

      }



      btn.addEventListener('click', async function () {

        if (!canAccessSolutions()) {

          refreshSolutionButtons();

          return;

        }



        const isOff = isOfficialSolution(solution);

        const blockSelector = `.solution-block[data-solution-id="${solution.id}"]`;

        const already = root.querySelector(blockSelector);



        if (isOff && already) {

          already.remove();

          btn.dataset.officialVisible = '0';

          setOfficialButtonState(btn, false, solution);

          refreshSolutionButtons();

          return;

        }



        if (!isOff && already) {

          btn.disabled = true;

          btn.dataset.alreadySeen = '1';

          btn.title = 'Déjà affiché';

          already.scrollIntoView({ behavior: 'smooth', block: 'start' });

          return;

        }



        btn.disabled = true;

        try {

          const data = await apiPOST(`/exercises/${id}/solutions/${solution.id}/reveal`, {});



          appendBlock(

            revealZone,

            isOff ? officialLabel(solution) : (solution.title || 'Corrigé'),

            data && data.content,

            { 'data-solution-id': solution.id, class: 'ouin-exo-block solution-block' }

          );



          revealedSolutionIds.add(sid);



          if (isOff) {

            btn.dataset.alreadySeen = '1';

            btn.dataset.officialVisible = '1';

            setOfficialButtonState(btn, true, solution);

          } else {

            btn.dataset.alreadySeen = '1';

          }

        } catch (e) {

          alert('Corrigé indisponible');

        } finally {

          refreshSolutionButtons();

        }

      });



      li.appendChild(btn);

      solList.appendChild(li);

    }



    // reset UI

    if (statementEl) statementEl.innerHTML = 'Chargement…';

    if (revealZone) revealZone.innerHTML = '';

    if (solList) solList.innerHTML = '';



    if (btn1) btn1.disabled = false;

    if (btn2) btn2.disabled = true;

    if (btn3) btn3.disabled = true;



    // badge de statut toujours visible pour un connecté

    if (isLogged) {

      const statusUi = ensureStatusBadge(root);

      badge = statusUi.badge;

      paintStatus('none');

    } else {

      root.querySelectorAll('.exo-status-line').forEach(function (el) { el.remove(); });

    }



    // boutons manuels uniquement si option admin activée

    if (isLogged && canShowStatusActions) {

      const actions = ensureActions(root);

      btnAttempt = actions.btnAttempt;

      btnSolved = actions.btnSolved;

    } else {

      root.querySelectorAll('.exo-actions').forEach(function (el) { el.remove(); });

    }



    // 1) Charger l’énoncé

    try {

      const exo = await apiGET('/exercises/' + id);



      if (exo && exo.title) {

        let titleEl = root.querySelector('.exo-title');

        if (!titleEl) {

          titleEl = document.createElement('h2');

          titleEl.className = 'exo-title';

          root.insertBefore(titleEl, root.firstChild);

        }

        titleEl.textContent = exo.title;

      }



if (exo) {

  let difficultyText = '';



  if (exo.difficulty_label) {

    difficultyText = exo.difficulty_label;

  } else if (exo.difficulty_slug || exo.difficulty) {

    const slug = String(exo.difficulty_slug || exo.difficulty || '').toLowerCase();

    difficultyText = difficultyLabelFromSlug(slug);

  }



  const levels = Array.isArray(exo.school_levels) ? exo.school_levels.filter(Boolean) : [];

  const competencies = Array.isArray(exo.competencies) ? exo.competencies.filter(Boolean) : [];



  let metaEl = root.querySelector('.exo-meta');

  if (!metaEl) {

    metaEl = document.createElement('div');

    metaEl.className = 'exo-meta';

    const afterTitle = root.querySelector('.exo-title');

    if (afterTitle && afterTitle.nextSibling) {

      root.insertBefore(metaEl, afterTitle.nextSibling);

    } else {

      root.insertBefore(metaEl, root.firstChild);

    }

  }



  const chips = [];



  if (difficultyText) {

    const diffClassMap = {

      'débutant': 'ouinpo-badge--difficulty-beginner',

      'confirmé': 'ouinpo-badge--difficulty-intermediate',

      'expert': 'ouinpo-badge--difficulty-expert'

    };



    const diffKey = difficultyText.trim().toLowerCase();

    const diffExtraClass = diffClassMap[diffKey] || '';



    chips.push(

      `<span class="ouinpo-badge ouinpo-badge--difficulty ${diffExtraClass}">Difficulté : ${escapeHtml(difficultyText)}</span>`

    );

  }



  if (levels.length) {

    chips.push(

      `<span class="ouinpo-badge ouinpo-badge--level">Niveau : ${escapeHtml(levels.join(' / '))}</span>`

    );

  }



  competencies.forEach(function (comp) {

    chips.push(

      `<span class="ouinpo-badge ouinpo-badge--competency">${escapeHtml(comp)}</span>`

    );

  });



  metaEl.innerHTML = chips.join('');

        }



      if (statementEl) {

        statementEl.innerHTML = exo && exo.statement ? exo.statement : '<p>(Énoncé vide)</p>';

      }

    } catch (e) {

      if (statementEl) statementEl.innerHTML = '<p>Erreur de chargement.</p>';

    }



    // 2) Charger le statut connecté

    if (isLogged) {

      try {

        const st = await apiGET(`/exercises/${id}/status`);

        paintStatus(st && st.status ? st.status : 'none');

      } catch (_) {

        paintStatus('none');

      }

    }



    // 3) Charger les révélations déjà vues pour connecté

    if (isLogged) {

      try {

        const rev = await apiGET(`/exercises/${id}/reveals`);



        (rev.hints || []).forEach(function (h) {

          const order = parseInt(h.order || h.hint_order || h.ref, 10);

          if (!Number.isNaN(order)) {

            revealedHintOrders.add(order);

            appendBlock(revealZone, 'Indice ' + order, h.content);

          }

        });



        (rev.solutions || []).forEach(function (s) {

          const sid = parseInt(s.id, 10);

          if (!Number.isNaN(sid)) revealedSolutionIds.add(sid);



          if (!root.querySelector(`.solution-block[data-solution-id="${s.id}"]`)) {

            const title = (s.title || 'Corrigé') + (isOfficialSolution(s) ? ' ⭐ Officielle' : '');

            appendBlock(

              revealZone,

              title,

              s.content,

              { 'data-solution-id': s.id, class: 'ouin-exo-block solution-block' }

            );

          }

        });

      } catch (_) {}

    }



    // 4) Charger la liste des corrigés

    try {

      const sols = await apiGET(`/exercises/${id}/solutions`);



      if (solList) {

        if (!sols || !sols.length) {

          const li = document.createElement('li');

          li.textContent = 'Aucun corrigé pour le moment.';

          solList.appendChild(li);

        } else {

          sols.forEach(function (s) {

            const sid = parseInt(s.id, 10);

            addSolutionButton(s, revealedSolutionIds.has(sid));

          });

        }

      }

    } catch (e) {

      if (solList) {

        const li = document.createElement('li');

        li.textContent = 'Erreur de chargement des corrigés.';

        solList.appendChild(li);

      }

    }



    refreshSolutionButtons();



    // 5) État initial des indices

    if (btn1) btn1.disabled = revealedHintOrders.has(1);

    if (btn2) btn2.disabled = !revealedHintOrders.has(1) ? true : revealedHintOrders.has(2);

    if (btn3) btn3.disabled = !revealedHintOrders.has(2) ? true : revealedHintOrders.has(3);



    // 6) Hints

    if (btn1) {

      btn1.onclick = async function () {

        btn1.disabled = true;

        try {

          const data = await apiPOST(`/exercises/${id}/hints/1/reveal`, {});

          appendBlock(revealZone, 'Indice 1', data && data.content);

          revealedHintOrders.add(1);

          if (btn2 && !revealedHintOrders.has(2)) btn2.disabled = false;

        } catch (e) {

          btn1.disabled = false;

          alert('Indice indisponible');

        }

      };

    }



    if (btn2) {

      btn2.onclick = async function () {

        btn2.disabled = true;

        try {

          const data = await apiPOST(`/exercises/${id}/hints/2/reveal`, {});

          appendBlock(revealZone, 'Indice 2', data && data.content);

          revealedHintOrders.add(2);

          if (btn3 && !revealedHintOrders.has(3)) btn3.disabled = false;

        } catch (e) {

          btn2.disabled = false;

          alert('Indice indisponible');

        }

      };

    }



    if (btn3) {

      btn3.onclick = async function () {

        btn3.disabled = true;

        try {

          const data = await apiPOST(`/exercises/${id}/hints/3/reveal`, {});

          appendBlock(revealZone, 'Indice 3', data && data.content);

          revealedHintOrders.add(3);

        } catch (e) {

          btn3.disabled = false;

          alert('Indice indisponible');

        }

      };

    }



    // 7) Bloc réponse IA

    if (insertCodeBtn && answerText) {

      insertCodeBtn.onclick = function () {

        insertCodeBlock(answerText);

      };

    }



    if (submitAnswer && answerText && aiFeedbackEl) {

      submitAnswer.onclick = async function () {

        const answer = (answerText.value || '').trim();



        if (!answer) {

          aiFeedbackEl.innerHTML = '<p>Écris d’abord une réponse.</p>';

          return;

        }



        submitAnswer.disabled = true;

        if (insertCodeBtn) insertCodeBtn.disabled = true;

        aiFeedbackEl.innerHTML = '<div class="ouin-exo-block ai-feedback-block"><div class="ouin-exo-block-content"><p>Évaluation en cours…</p></div></div>';



        try {

          const result = await apiPOST(`/exercises/${id}/ai-evaluate`, { answer: answer });

          renderAiFeedback(aiFeedbackEl, result);



          if (

            isLogged &&

            result &&

            result.verdict === 'correct' &&

            result.safe_to_mark_solved === true

          ) {

            try {

              const r = await apiPOST(`/exercises/${id}/status`, { status: 'solved' });

              paintStatus((r && r.status) ? r.status : 'solved');

              refreshSolutionButtons();

            } catch (_) {}

          } else if (

            isLogged &&

            result &&

            currentStatus !== 'solved'

          ) {

            try {

              const r = await apiPOST(`/exercises/${id}/status`, { status: 'attempted' });

              paintStatus((r && r.status) ? r.status : 'attempted');

              refreshSolutionButtons();

            } catch (_) {}

          }

            } catch (e) {

              console.error('[ouinpo] Erreur évaluation IA', e);

            

              const msg = e && e.message

                ? e.message

                : 'Évaluation impossible pour le moment.';

            

              aiFeedbackEl.innerHTML =

                '<div class="ouin-exo-block ai-feedback-block">' +

                  '<div class="ouin-exo-block-content">' +

                    '<p>' + escapeHtml(msg) + '</p>' +

                  '</div>' +

                '</div>';

            } finally {

          submitAnswer.disabled = false;

          if (insertCodeBtn) insertCodeBtn.disabled = false;

        }

      };

    }



    // 8) Boutons manuels

    if (btnAttempt) {

      btnAttempt.onclick = async function () {

        toggleActButtons(true);

        try {

          const r = await apiPOST(`/exercises/${id}/status`, { status: 'attempted' });

          paintStatus(r.status || 'attempted');

          refreshSolutionButtons();

        } catch (e) {

          alert('Écriture du statut impossible');

        } finally {

          toggleActButtons(false);

        }

      };

    }



    if (btnSolved) {

      btnSolved.onclick = async function () {

        toggleActButtons(true);

        try {

          const r = await apiPOST(`/exercises/${id}/status`, { status: 'solved' });

          paintStatus(r.status || 'solved');

          refreshSolutionButtons();

        } catch (e) {

          alert('Écriture du statut impossible');

        } finally {

          toggleActButtons(false);

        }

      };

    }

  }



function enableTabInAnswerTextareas() {

  if (window.OUINPO_TAB_IN_ANSWER_TEXTAREAS_ENABLED) return;

  window.OUINPO_TAB_IN_ANSWER_TEXTAREAS_ENABLED = true;



  const INDENT = '    '; // 4 espaces, préférable pour Python



  function isAnswerTextarea(element) {

    return element instanceof HTMLTextAreaElement

      && element.classList.contains('exo-answer-text');

  }



  function clearEscapeMode(textarea) {

    delete textarea.dataset.ouinpoNextTabLeaves;

    textarea.classList.remove('ouinpo-tab-leave-armed');

  }



  function ensureKeyboardHint(textarea) {

    if (textarea.dataset.ouinpoTabHintReady === '1') return;



    textarea.dataset.ouinpoTabHintReady = '1';



    const hint = document.createElement('p');

    const hintId = 'ouinpo-tab-hint-' + Math.random().toString(36).slice(2);



    hint.id = hintId;

    hint.className = 'ouinpo-tab-hint';

    hint.textContent = 'Clavier : Tab indente, Maj + Tab désindente, Échap puis Tab passe à la zone suivante.';



    textarea.insertAdjacentElement('afterend', hint);



    const describedBy = textarea.getAttribute('aria-describedby');

    textarea.setAttribute(

      'aria-describedby',

      describedBy ? describedBy + ' ' + hintId : hintId

    );



    textarea.setAttribute(

      'title',

      'Tab : indenter ; Maj + Tab : désindenter ; Échap puis Tab : changer de zone'

    );

  }



  document.addEventListener('focusin', function (e) {

    if (isAnswerTextarea(e.target)) {

      ensureKeyboardHint(e.target);

    }

  });



  document.addEventListener('keydown', function (e) {

    const textarea = e.target;



    if (!isAnswerTextarea(textarea)) return;



    ensureKeyboardHint(textarea);



    // Échap arme le prochain Tab pour sortir de la zone

    if (e.key === 'Escape') {

      textarea.dataset.ouinpoNextTabLeaves = '1';

      textarea.classList.add('ouinpo-tab-leave-armed');

      return;

    }



    // Si on appuie sur autre chose que Tab après Échap, on annule le mode sortie

    if (

      e.key !== 'Tab'

      && !['Shift', 'Control', 'Alt', 'Meta'].includes(e.key)

    ) {

      clearEscapeMode(textarea);

      return;

    }



    if (e.key !== 'Tab') return;



    // Échap puis Tab : on laisse le navigateur changer de champ

    if (textarea.dataset.ouinpoNextTabLeaves === '1') {

      clearEscapeMode(textarea);

      return;

    }



    // On ne gêne pas les raccourcis système/navigateur

    if (e.ctrlKey || e.altKey || e.metaKey) return;



    e.preventDefault();



    const value = textarea.value || '';

    const start = textarea.selectionStart ?? 0;

    const end = textarea.selectionEnd ?? 0;



    // TAB simple : indenter

    if (!e.shiftKey) {

      const selected = value.slice(start, end);



      // Plusieurs lignes sélectionnées : indenter chaque ligne

      if (selected.includes('\n')) {

        const lineStart = value.lastIndexOf('\n', start - 1) + 1;

        const before = value.slice(0, lineStart);

        const block = value.slice(lineStart, end);

        const after = value.slice(end);



        const indentedBlock = block

          .split('\n')

          .map(function (line) {

            return INDENT + line;

          })

          .join('\n');



        textarea.value = before + indentedBlock + after;

        textarea.selectionStart = start + INDENT.length;

        textarea.selectionEnd = end + (indentedBlock.length - block.length);

      } else {

        textarea.setRangeText(INDENT, start, end, 'end');

      }



      textarea.dispatchEvent(new Event('input', { bubbles: true }));

      return;

    }



    // MAJ + TAB : désindenter

    const lineStart = value.lastIndexOf('\n', start - 1) + 1;

    const nextLineBreak = value.indexOf('\n', end);

    const blockEnd = nextLineBreak === -1 ? value.length : nextLineBreak;



    const before = value.slice(0, lineStart);

    const block = value.slice(lineStart, blockEnd);

    const after = value.slice(blockEnd);



    let removedBeforeStart = 0;

    let removedTotal = 0;

    let absolutePos = lineStart;

    let cursorLineHandled = false;



    const unindentedBlock = block

      .split('\n')

      .map(function (line) {

        let remove = 0;



        if (line.startsWith(INDENT)) {

          remove = INDENT.length;

        } else if (line.startsWith('\t')) {

          remove = 1;

        } else {

          const match = line.match(/^ {1,3}/);

          remove = match ? match[0].length : 0;

        }



        const currentLineStart = absolutePos;

        const currentLineEnd = absolutePos + line.length;



        if (

          !cursorLineHandled

          && start >= currentLineStart

          && start <= currentLineEnd

        ) {

          removedBeforeStart = Math.min(

            remove,

            Math.max(0, start - currentLineStart)

          );

          cursorLineHandled = true;

        }



        removedTotal += remove;

        absolutePos += line.length + 1;



        return line.slice(remove);

      })

      .join('\n');



    textarea.value = before + unindentedBlock + after;



    const newStart = Math.max(lineStart, start - removedBeforeStart);

    const newEnd = Math.max(newStart, end - removedTotal);



    textarea.selectionStart = newStart;

    textarea.selectionEnd = newEnd;



    textarea.dispatchEvent(new Event('input', { bubbles: true }));

  });

}

  function bindExerciseLevelFilter() {
    const sel = document.getElementById('ouinpo-exo-level');

    if (!sel || sel.dataset.ouinpoLevelFilterBooted === '1') {
      return false;
    }

    sel.dataset.ouinpoLevelFilterBooted = '1';

    sel.addEventListener('change', function () {
      const url = new URL(window.location.href);

      if (this.value) {
        url.searchParams.set('lvl', this.value);
      } else {
        url.searchParams.delete('lvl');
      }

      window.location.href = url.toString();
    });

    return true;
  }

  function ouinpoExercisesBoot() {
    enableTabInAnswerTextareas();

    let didWork = false;

    if (bindExerciseLevelFilter()) {
      didWork = true;
    }

    const rootA = document.getElementById('ouinpo-exercises');
    const rootB = document.getElementById('ouinpo-exo-list');
    const root = rootA || rootB;

    if (root && root.dataset.ouinpoListBooted !== '1') {
      root.dataset.ouinpoListBooted = '1';
      buildFiltersAndLoad(root);
      didWork = true;
    }

    document.querySelectorAll('.ouinpo-exo').forEach(function (exoRoot) {
      if (exoRoot.dataset.ouinpoDetailBooted === '1') return;

      exoRoot.dataset.ouinpoDetailBooted = '1';
      loadExerciseDetail(exoRoot);
      didWork = true;
    });

    return didWork;
  }

  function ouinpoExercisesScheduleBoot() {
    let tries = 0;
    const maxTries = 30;

    function attempt() {
      const ok = ouinpoExercisesBoot();

      if (ok) return;

      tries += 1;

      if (tries <= maxTries) {
        window.setTimeout(attempt, 150);
      }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', attempt);
    } else {
      attempt();
    }

    window.addEventListener('load', attempt);

    if (window.MutationObserver && document.documentElement) {
      const observer = new MutationObserver(function () {
        if (ouinpoExercisesBoot()) {
          observer.disconnect();
        }
      });

      observer.observe(document.documentElement, {
        childList: true,
        subtree: true
      });

      window.setTimeout(function () {
        observer.disconnect();
      }, 6000);
    }
  }

  ouinpoExercisesScheduleBoot();



})();
