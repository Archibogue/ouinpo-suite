(function () {
    window.OUINEXO = window.OUINEXO || {
    api: window.location.origin + '/wp-json/',
    nonce: '',
    is_logged_in: '0'
  };

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
    const h = { Accept: 'application/json' };

    const isLoggedIn =
      !!(window.OUINEXO && String(OUINEXO.is_logged_in || '0') === '1') ||
      document.body.classList.contains('logged-in');

    if (isLoggedIn && window.OUINEXO && OUINEXO.nonce) {
      h['X-WP-Nonce'] = OUINEXO.nonce;
    }

    return h;
  }



  async function apiGET(path) {

    const controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    const timer = controller ? window.setTimeout(function () {
      controller.abort();
    }, 12000) : null;

    let res;

    try {
      res = await fetch(restUrl(path), {

        headers: commonHeaders(),

        credentials: 'include',

        signal: controller ? controller.signal : undefined

      });
    } finally {
      if (timer) window.clearTimeout(timer);
    }

    if (!res.ok) throw new Error('HTTP ' + res.status);

    return res.json();

  }



  async function apiPOST(path, body) {

  const res = await fetch(restUrl(path), {

    method: 'POST',

    headers: Object.assign(

      { 'Content-Type': 'application/json' },

      commonHeaders()

    ),

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

    return String(s).replace(/[&<>"']/g, function (c) {

      return {

        '&': '&amp;',

        '<': '&lt;',

        '>': '&gt;',

        '"': '&quot;',

        "'": '&#39;'

      }[c];

    });

  }



  function renderMessage(container, text, className) {

    if (!container) return;

    container.innerHTML =

      '<div class="' + (className || 'ouinpo-empty') + '">' +

      escapeHtml(text) +

      '</div>';

  }



  function setupPracticalLevelSelect() {

    const sel = document.getElementById("ouinpo-practical-level");

    if (!sel || sel.dataset.ouinpoPracticalLevelReady === '1') return false;

    sel.dataset.ouinpoPracticalLevelReady = '1';

    sel.addEventListener("change", function() {

      const url = new URL(window.location.href);

      if (this.value) {

        url.searchParams.set("lvl", this.value);

      } else {

        url.searchParams.delete("lvl");

      }

      window.location.href = url.toString();

    });

    return true;

  }



  function buildMetaChip(text) {

    return '<span class="ouinpo-chip">' + escapeHtml(text) + '</span>';

  }



  function difficultyLabelFromSlug(slug) {

    const map = {

      debutant: 'Débutant',

      confirme: 'Confirmé',

      expert: 'Expert'

    };

    return map[String(slug || '').toLowerCase()] || '';

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



  function callStatusLabel(status) {

    if (status === 'solved') return 'Réussi ✅';

    if (status === 'attempted') return 'Tenté';

    return 'Non commencé';

  }



  function callStatusClass(status) {

    if (status === 'solved') return 'badge-solved';

    if (status === 'attempted') return 'badge-attempt';

    return 'badge-none';

  }



  function practicalLinkTarget(basePage, id) {
  const url = basePage && basePage.length ? basePage : window.location.href;
  const u = new URL(url, window.location.origin);

  u.searchParams.set('practical', String(id));

  return u.toString();
  }



  function insertCodeBlock(textarea) {

    if (!textarea) return;



    const start = textarea.selectionStart || 0;

    const end = textarea.selectionEnd || 0;

    const value = textarea.value || '';

    const selected = value.slice(start, end);



    const block = selected

      ? '[code]\n' + selected + '\n[/code]'

      : '[code]\n\n[/code]';



    textarea.value = value.slice(0, start) + block + value.slice(end);



    const cursorPos = selected ? start + block.length : start + '[code]\n'.length;

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



    let html = '<div class="ouin-exo-block ai-feedback-block">';

    html += '<h5>' + escapeHtml(title) + '</h5>';

    html += '<div class="ouin-exo-block-content">';



    if (data && data.feedback) {

      html += '<p>' + escapeHtml(data.feedback) + '</p>';

    } else {

      html += '<p>Retour indisponible.</p>';

    }



    if (data && Array.isArray(data.next_steps) && data.next_steps.length) {

      html += '<h6>À faire maintenant</h6>';

      html += '<ul>';

      data.next_steps.forEach(function (step) {

        html += '<li>' + escapeHtml(step) + '</li>';

      });

      html += '</ul>';

    }

    

    if (data && data.verdict === 'correct' && data.safe_to_mark_solved === false) {

      html += '<p><em>Réponse encourageante, mais non validée officiellement pour le moment.</em></p>';

    }



    if (data && typeof data.confidence !== 'undefined' && data.confidence !== null && data.confidence !== '') {

      const confidence = Number(data.confidence);

      if (!Number.isNaN(confidence)) {

        html += '<p><strong>Confiance :</strong> ' + escapeHtml(Math.round(confidence * 100) + ' %') + '</p>';

      }

    }



    html += '</div></div>';

    container.innerHTML = html;

  }



  function renderPracticalSubjectsList(container, items, opts) {

    if (!container) return;



    const basePage = container.dataset.subjectPage || container.dataset.exoPage || '';

    const currentFilters = (opts && opts.currentFilters) ? opts.currentFilters : {};



    if (!items || !items.length) {

      renderMessage(container, 'Aucun sujet pratique trouvé.', 'ouinpo-empty');

      return;

    }



    let html = '';

    const chips = [];



    chips.push(buildMetaChip(items.length + ' sujet' + (items.length > 1 ? 's' : '')));



    if (currentFilters.sourceLabel) {

      chips.push(buildMetaChip('Origine : ' + currentFilters.sourceLabel));

    }

    if (currentFilters.themeLabel) {

      chips.push(buildMetaChip('Thème bac : ' + currentFilters.themeLabel));

    }

    if (currentFilters.difficultyLabel) {

      chips.push(buildMetaChip('Difficulté : ' + currentFilters.difficultyLabel));

    }



    html += '<div class="ouinpo-exercises-meta">' + chips.join('') + '</div>';

    html += '<ul class="ouinpo-exercises-list ouinpo-exo-list">';



    items.forEach(function (it) {

      const diffLabel = it.difficulty_label || difficultyLabelFromSlug(it.difficulty_slug || '');

      const sourceLabel = sourceTypeLabel(it.source_type || '');



      const subBits = [];

      if (it.session_label) subBits.push(it.session_label);

      if (it.year_label) subBits.push(it.year_label);

      if (it.center_label) subBits.push(it.center_label);



      html += '<li class="ouinpo-exercise-item ouin-exo-li">';

      html += '  <div class="ouinpo-exercise-main ouin-exo-main">';

      html += '    <a class="ouinpo-exercise-link ouin-exo-link" href="' +

        escapeHtml(practicalLinkTarget(basePage, it.id)) +

        '">' + escapeHtml(it.title || 'Sujet pratique') + '</a>';



      if (subBits.length) {

        html += '    <div class="ouinpo-exercise-sub">' + escapeHtml(subBits.join(' • ')) + '</div>';

      }



      html += '  </div>';

      html += '  <div class="ouinpo-badges ouin-exo-status">';



      if (sourceLabel) {

        html += '    <span class="ouinpo-badge ouinpo-badge--exam">' + escapeHtml(sourceLabel) + '</span>';

      }

      if (it.theme_bac) {

      html += '    <span class="ouinpo-badge ouinpo-badge--competency">' + escapeHtml(themeBacLabel(it.theme_bac)) + '</span>';

      }

      if (diffLabel) {

        html += '    <span class="ouinpo-badge ouinpo-badge--difficulty">' + escapeHtml(diffLabel) + '</span>';

      }

      if (typeof it.calls_count !== 'undefined') {

        html += '    <span class="ouinpo-badge">' + escapeHtml(String(it.calls_count)) + ' appel' + (Number(it.calls_count) > 1 ? 's' : '') + '</span>';

      }

      if (typeof it.files_count !== 'undefined' && Number(it.files_count) > 0) {

        html += '    <span class="ouinpo-badge">' + escapeHtml(String(it.files_count)) + ' fichier' + (Number(it.files_count) > 1 ? 's' : '') + '</span>';

      }



      html += '  </div>';

      html += '</li>';

    });



    html += '</ul>';

    container.innerHTML = html;

  }



  async function buildSubjectsFiltersAndLoad(rootForList) {

    if (!rootForList) return;



    const existing = document.getElementById('ouinpo-practical-dynamic-filters');

    if (existing) existing.remove();



    const schoolLevel = (rootForList.dataset.level || '');

    const presetSourceType = (rootForList.dataset.sourceType || '');

    const presetThemeBac = (rootForList.dataset.themeBac || '');



    const filtersWrap = document.createElement('section');

    filtersWrap.id = 'ouinpo-practical-dynamic-filters';

    filtersWrap.className = 'ouinpo-panel ouinpo-panel--filters ouinpo-exo-filters';



    filtersWrap.innerHTML = `

      <h2 class="ouinpo-panel-title">Filtrer les sujets pratiques</h2>

      <div class="ouinpo-filters-grid">

        <div class="ouinpo-field">

          <label for="ouinpo-practical-filter-source">Origine</label>

          <select id="ouinpo-practical-filter-source" class="ouinpo-select" data-filter="source_type">

            <option value="">Toutes</option>

            <option value="annale">Annales</option>

            <option value="inspired">Inspirés d’annales</option>

            <option value="type_bac">Type bac</option>

          </select>

        </div>



        <div class="ouinpo-field">

          <label for="ouinpo-practical-filter-theme">Thème bac</label>

          <select id="ouinpo-practical-filter-theme" class="ouinpo-select" data-filter="theme_bac">

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

          <label for="ouinpo-practical-filter-difficulty">Difficulté</label>

          <select id="ouinpo-practical-filter-difficulty" class="ouinpo-select" data-filter="difficulty">

            <option value="">Toutes</option>

            <option value="debutant">Débutant</option>

            <option value="confirme">Confirmé</option>

            <option value="expert">Expert</option>

          </select>

        </div>

      </div>

    `;



    const shell = rootForList.closest('.ouinpo-exercises-shell');

    const resultsPanel = rootForList.closest('.ouinpo-panel--results');



    if (shell && resultsPanel && resultsPanel.parentNode === shell) {

      shell.insertBefore(filtersWrap, resultsPanel);

    } else if (rootForList.parentNode) {

      rootForList.parentNode.insertBefore(filtersWrap, rootForList);

    }



    const selSource = filtersWrap.querySelector('select[data-filter="source_type"]');

    const selTheme = filtersWrap.querySelector('select[data-filter="theme_bac"]');

    const selDifficulty = filtersWrap.querySelector('select[data-filter="difficulty"]');



    if (selSource && presetSourceType) {

      selSource.value = presetSourceType;

    }

    if (selTheme && presetThemeBac) {

      selTheme.value = presetThemeBac;

    }



    function selectedText(selectEl) {

      if (!selectEl) return '';

      if (!selectEl.value) return '';

      const idx = selectEl.selectedIndex;

      if (idx < 0) return '';

      return (selectEl.options[idx] && selectEl.options[idx].textContent)

        ? selectEl.options[idx].textContent.trim()

        : '';

    }



    async function reloadList() {

      const params = new URLSearchParams();
      const previousHtml = rootForList.innerHTML;
      const canRestoreServerFallback =
        rootForList.dataset.serverFallback === '1' &&
        previousHtml &&
        previousHtml.indexOf('ouinpo-loading') === -1;



      const sourceVal = selSource ? selSource.value : '';

      const themeVal = selTheme ? selTheme.value : '';

      const diffVal = selDifficulty ? selDifficulty.value : '';



      if (schoolLevel) params.set('school_level', schoolLevel);

      if (sourceVal) params.set('source_type', sourceVal);

      if (themeVal) params.set('theme_bac', themeVal);

      if (diffVal) params.set('difficulty', diffVal);



      renderMessage(rootForList, 'Chargement des sujets pratiques…', 'ouinpo-loading');



      try {

        const items = await apiGET('/practical-subjects' + (params.toString() ? '?' + params.toString() : ''));

        renderPracticalSubjectsList(rootForList, items, {

          currentFilters: {

            sourceLabel: selectedText(selSource),

            themeLabel: selectedText(selTheme),

            difficultyLabel: selectedText(selDifficulty)

          }

        });

      } catch (e) {

        console.error('[ouinpo] Erreur de chargement des sujets pratiques', e);

        if (canRestoreServerFallback) {
          rootForList.innerHTML = previousHtml;
        } else {
          renderMessage(rootForList, 'Erreur de chargement des sujets pratiques.', 'ouinpo-empty');
        }

      }

    }



    if (selSource) selSource.addEventListener('change', reloadList);

    if (selTheme) selTheme.addEventListener('change', reloadList);

    if (selDifficulty) selDifficulty.addEventListener('change', reloadList);



    reloadList();

  }



  function ensureBlock(root, selector, className, titleText) {

    let block = root.querySelector(selector);

    if (block) return block;



    block = document.createElement('section');

    block.className = className;



    if (titleText) {

      const h = document.createElement('h3');

      h.textContent = titleText;

      block.appendChild(h);

    }



    root.appendChild(block);

    return block;

  }



  function setCallStatus(card, status) {

    if (!card) return;

    const badge = card.querySelector('.ouinpo-practical-call-status .exo-badge');

    if (!badge) return;



    const s = status || 'none';

    badge.className = 'exo-badge ' + callStatusClass(s);

    badge.textContent = callStatusLabel(s);

  }



  function createCallCard(subjectId, call, currentStatus, isLogged, previewOnly) {

    const card = document.createElement('article');

    card.className = 'ouinpo-practical-call';

    card.setAttribute('data-call-id', String(call.id));



    const title = call.title && String(call.title).trim()

      ? call.title

      : 'Appel ' + String(call.call_order);



    const mode = String(call.answer_mode || 'code');



    let html = '';

    html += '<div class="ouinpo-practical-call-head">';

    html += '  <h4>' + escapeHtml(title) + '</h4>';

    html += '  <div class="ouinpo-practical-call-status"><span class="exo-badge"></span></div>';

    html += '</div>';



    html += '<div class="ouinpo-practical-call-prompt">';

    html += call.prompt_html || '<p>(Consigne vide)</p>';

    html += '</div>';



    if (typeof call.max_points !== 'undefined' && call.max_points !== null && call.max_points !== '') {

      html += '<p class="ouinpo-practical-call-points"><strong>Barème indicatif :</strong> ' + escapeHtml(String(call.max_points)) + ' pt(s)</p>';

    }



    if (previewOnly) {
      html += '<div class="exo-answer-box ouinpo-practical-answer-box">';
      html += '  <p class="exo-answer-help">Ce sujet est masqué côté élèves. La correction IA est désactivée en prévisualisation.</p>';
      html += '</div>';
      card.innerHTML = html;
      setCallStatus(card, currentStatus);
      return card;
    }

    html += '<div class="exo-answer-box ouinpo-practical-answer-box">';

    html += '  <h5>Ma réponse</h5>';

    

    if (mode !== 'text') {

      html += '  <button type="button" class="exo-insert-code">Insérer un bloc code</button>';

    }

    

    html += '  <textarea class="exo-answer-text" rows="10" placeholder="' +

      escapeHtml(

        mode === 'text'

          ? 'Rédige ici ta réponse.'

          : (mode === 'mixed'

            ? 'Rédige ici ta réponse. Tu peux aussi insérer du code avec [code] ... [/code].'

            : 'Colle ici ton code ou ta réponse. Encadre le code avec [code] et [/code].')

      ) +

      '"></textarea>';

    

    if (!isLogged) {

      html += '  <p class="exo-answer-help exo-answer-help--public">';

      html += '    <em>Mode visiteur : SegFault donne un retour, mais aucune progression, note, badge ou tentative n’est enregistré.</em>';

      html += '  </p>';

    }

    

    html += '  <div class="exo-answer-actions">';

    html += '    <button type="button" class="exo-submit-answer">Soumettre à SegFault</button>';

    html += '  </div>';

    html += '  <div class="exo-ai-feedback"></div>';

    html += '</div>';



    card.innerHTML = html;

    setCallStatus(card, currentStatus);



    const textarea = card.querySelector('.exo-answer-text');

    const btnCode = card.querySelector('.exo-insert-code');

    const btnSubmit = card.querySelector('.exo-submit-answer');

    const feedback = card.querySelector('.exo-ai-feedback');

    

    if (btnCode && textarea) {

      btnCode.addEventListener('click', function () {

        insertCodeBlock(textarea);

      });

    }

    

    if (btnSubmit && textarea && feedback) {

      btnSubmit.addEventListener('click', async function () {

        const answer = (textarea.value || '').trim();

    

        if (!answer) {

          feedback.innerHTML = '<p>Écris d’abord une réponse.</p>';

          return;

        }

    

        btnSubmit.disabled = true;

        if (btnCode) btnCode.disabled = true;

    

        feedback.innerHTML =

          '<div class="ouin-exo-block ai-feedback-block">' +

          '<div class="ouin-exo-block-content"><p>Évaluation en cours…</p></div>' +

          '</div>';

    

        try {

          const result = await apiPOST(

            '/practical-subjects/' + subjectId + '/calls/' + call.id + '/ai-evaluate',

            { answer: answer }

          );

    

          renderAiFeedback(feedback, result);

    

          // Connecté : statut réel enregistré côté serveur.

          // Visiteur : statut seulement affiché localement.

          setCallStatus(card, result && result.status ? result.status : 'attempted');

        } catch (e) {

          console.error('[ouinpo] Erreur évaluation appel pratique', e);

        

          const msg = e && e.message

            ? e.message

            : 'Évaluation impossible pour le moment.';

        

          feedback.innerHTML =

            '<div class="ouin-exo-block ai-feedback-block">' +

              '<div class="ouin-exo-block-content">' +

                '<p>' + escapeHtml(msg) + '</p>' +

              '</div>' +

            '</div>';

        } finally {

          btnSubmit.disabled = false;

          if (btnCode) btnCode.disabled = false;

        }

      });

    }

    

    return card;

  }



  async function loadPracticalSubjectDetail(root) {

    if (!root) return;



    const subjectId = parseInt(root.getAttribute('data-subject-id') || root.getAttribute('data-exo-id'), 10);

    if (!subjectId) return;



    const isLogged = (root.dataset.logged === '1');



    const titleEl = root.querySelector('.ouinpo-practical-title') || root.querySelector('.exo-title');

    const statementBlock = ensureBlock(root, '.ouinpo-practical-statement', 'ouinpo-practical-statement', 'Sujet');

    const filesBlock = ensureBlock(root, '.ouinpo-practical-files', 'ouinpo-practical-files', 'Fichiers fournis');

    const callsBlock = ensureBlock(root, '.ouinpo-practical-calls', 'ouinpo-practical-calls', 'Appels évalués');



    statementBlock.innerHTML = '<h3>Sujet</h3><div class="ouinpo-loading">Chargement…</div>';

    filesBlock.innerHTML = '<h3>Fichiers fournis</h3><div class="ouinpo-loading">Chargement…</div>';

    callsBlock.innerHTML = '<h3>Appels évalués</h3><div class="ouinpo-loading">Chargement…</div>';



    try {

      const [subject, files, calls, progress] = await Promise.all([

        apiGET('/practical-subjects/' + subjectId),

        apiGET('/practical-subjects/' + subjectId + '/files'),

        apiGET('/practical-subjects/' + subjectId + '/calls'),

        isLogged

          ? apiGET('/practical-subjects/' + subjectId + '/progress').catch(function () { return []; })

          : Promise.resolve([])

      ]);



      if (titleEl && subject && subject.title) {

        titleEl.textContent = subject.title;

      } else if (subject && subject.title) {

        const h2 = document.createElement('h2');

        h2.className = 'ouinpo-practical-title';

        h2.textContent = subject.title;

        root.insertBefore(h2, root.firstChild);

      }



    let metaEl = root.querySelector('.ouinpo-practical-meta');

    if (!metaEl) {

      metaEl = document.createElement('div');

      metaEl.className = 'ouinpo-practical-meta';

    

      const anchor = root.querySelector('.ouinpo-practical-title') || root.querySelector('.exo-title');

    

      if (anchor && anchor.parentNode) {

        if (anchor.nextSibling) {

          anchor.parentNode.insertBefore(metaEl, anchor.nextSibling);

        } else {

          anchor.parentNode.appendChild(metaEl);

        }

      } else {

        root.insertBefore(metaEl, root.firstChild);

      }

    }



      const chips = [];



      if (subject.source_type) chips.push('<span class="ouinpo-badge ouinpo-badge--exam">' + escapeHtml(sourceTypeLabel(subject.source_type)) + '</span>');

        if (subject.theme_bac) {

          chips.push('<span class="ouinpo-badge ouinpo-badge--competency">' + escapeHtml(themeBacLabel(subject.theme_bac)) + '</span>');

        }

        if (subject.difficulty_label || subject.difficulty_slug) {

        chips.push('<span class="ouinpo-badge ouinpo-badge--difficulty">' +

          escapeHtml(subject.difficulty_label || difficultyLabelFromSlug(subject.difficulty_slug)) +

          '</span>');

      }

      if (Array.isArray(subject.school_levels) && subject.school_levels.length) {

        chips.push('<span class="ouinpo-badge ouinpo-badge--level">Niveau : ' + escapeHtml(subject.school_levels.join(' / ')) + '</span>');

      }

      if (subject.session_label) chips.push('<span class="ouinpo-badge">' + escapeHtml(subject.session_label) + '</span>');

      if (subject.year_label) chips.push('<span class="ouinpo-badge">' + escapeHtml(subject.year_label) + '</span>');

      if (subject.center_label) chips.push('<span class="ouinpo-badge">' + escapeHtml(subject.center_label) + '</span>');

      if (subject.estimated_minutes) chips.push('<span class="ouinpo-badge">~ ' + escapeHtml(String(subject.estimated_minutes)) + ' min</span>');
      if (subject._preview_notice) chips.push('<span class="ouinpo-badge">' + escapeHtml(subject._preview_notice) + '</span>');



      metaEl.innerHTML = chips.join('');



      statementBlock.innerHTML = '<h3>Sujet</h3><div class="ouinpo-practical-statement-content">' +

        (subject.statement || '<p>(Sujet vide)</p>') +

        '</div>';



      if (files && files.length) {

        let filesHtml = '<h3>Fichiers fournis</h3><ul class="ouinpo-practical-files-list">';

        files.forEach(function (file) {

          filesHtml += '<li>';

          if (file.url) {

            filesHtml += '<a href="' + escapeHtml(file.url) + '" target="_blank" rel="noopener noreferrer">' +

              escapeHtml(file.label || 'Télécharger') +

              '</a>';

          } else {

            filesHtml += escapeHtml(file.label || 'Fichier');

          }

          if (file.file_kind) {

            filesHtml += ' <span class="ouinpo-badge">' + escapeHtml(file.file_kind) + '</span>';

          }

          filesHtml += '</li>';

        });

        filesHtml += '</ul>';

        filesBlock.innerHTML = filesHtml;

      } else {

        filesBlock.innerHTML = '<h3>Fichiers fournis</h3><p>Aucun fichier associé à ce sujet.</p>';

      }



      const progressMap = Object.create(null);

      (progress || []).forEach(function (p) {

        progressMap[String(p.practical_call_id)] = p.status || 'none';

      });



      if (calls && calls.length) {

        callsBlock.innerHTML = '<h3>Appels évalués</h3>';

        calls.forEach(function (call) {

          const status = progressMap[String(call.id)] || 'none';

          callsBlock.appendChild(createCallCard(subjectId, call, status, isLogged, subject && Number(subject.is_active) !== 1));

        });

      } else {

        callsBlock.innerHTML = '<h3>Appels évalués</h3><p>Aucun appel pour ce sujet.</p>';

      }

    } catch (e) {

      console.error('[ouinpo] Erreur chargement sujet pratique', e);

      renderMessage(root, 'Erreur de chargement du sujet pratique.', 'ouinpo-empty');

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

  function ouinpoPracticalBoot() {
    enableTabInAnswerTextareas();

    let didWork = setupPracticalLevelSelect();

    const listRoot = document.getElementById('ouinpo-practical-subjects');

    if (listRoot && listRoot.dataset.ouinpoPracticalListBooted !== '1') {
      listRoot.dataset.ouinpoPracticalListBooted = '1';
      buildSubjectsFiltersAndLoad(listRoot);
      didWork = true;
    }

    document.querySelectorAll('.ouinpo-practical-subject').forEach(function (subjectRoot) {
      if (subjectRoot.dataset.ouinpoPracticalDetailBooted === '1') return;

      subjectRoot.dataset.ouinpoPracticalDetailBooted = '1';
      loadPracticalSubjectDetail(subjectRoot);
      didWork = true;
    });

    return didWork;
  }

  function ouinpoPracticalScheduleBoot() {
    let tries = 0;
    const maxTries = 30;

    function attempt() {
      const ok = ouinpoPracticalBoot();

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
        if (ouinpoPracticalBoot()) {
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

  ouinpoPracticalScheduleBoot();

})();
