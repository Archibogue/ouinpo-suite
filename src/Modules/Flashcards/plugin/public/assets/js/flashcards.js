(function () {

  if (!window.OUINPO_FLASHCARDS) return;



  const cfg = window.OUINPO_FLASHCARDS;



  function qs(sel, ctx) {

    return (ctx || document).querySelector(sel);

  }



  function qsa(sel, ctx) {

    return Array.from((ctx || document).querySelectorAll(sel));

  }



  function esc(str) {

    const div = document.createElement('div');

    div.textContent = str == null ? '' : String(str);

    return div.innerHTML;

  }



  function appState(app) {

    if (!app._ouinpoFcState) {

      app._ouinpoFcState = {

        decks: [],

        domains: [],

        selectedDeckIds: new Set(),

        domainsLoaded: false,

        sessionStarted: false

      };

    }

    return app._ouinpoFcState;

  }



  function selectedDeckIds(app) {

    return Array.from(appState(app).selectedDeckIds).map(Number).filter(Boolean);

  }



  function currentDomain(app) {

    const sel = qs('.ouinpo-fc-domain-select', app);

    return sel ? (sel.value || '') : '';

  }



  function currentDomainLabel(app) {

    const sel = qs('.ouinpo-fc-domain-select', app);

    if (!sel) return 'Tous les domaines vus';

    const opt = sel.options[sel.selectedIndex];

    return opt ? opt.textContent.replace(/\s*\(\d+\)\s*$/, '') : 'Tous les domaines vus';

  }



  function commonParams(app, includeDecks) {

    const params = new URLSearchParams();

    if (cfg.track) params.set('track', cfg.track);

    if (cfg.level) params.set('level', cfg.level);



    const domain = currentDomain(app);

    if (domain) params.set('domain_slug', domain);



    if (includeDecks) {

      const ids = selectedDeckIds(app);

      if (ids.length) params.set('deck_ids', ids.join(','));

    }



    const query = params.toString();

    return query ? '?' + query : '';

  }



  function apiUrl(path) {
    const raw = String(cfg.api || '/wp-json/ouinpo/v1/flashcards').replace(/\/+$/, '');
    const cleanPath = '/' + String(path || '').replace(/^\/+/, '');

    if (raw.includes('rest_route=')) {
      const u = new URL(raw, window.location.origin);
      const baseRoute = (u.searchParams.get('rest_route') || '/ouinpo/v1/flashcards').replace(/\/+$/, '');

      const parts = cleanPath.split('?');
      const routePath = parts[0] || '';
      const queryString = parts[1] || '';

      u.searchParams.set('rest_route', baseRoute + routePath);

      if (queryString !== '') {
        const query = new URLSearchParams(queryString);
        query.forEach((value, key) => {
          u.searchParams.set(key, value);
        });
      }

      return u.toString();
    }

    return new URL(raw + cleanPath, window.location.origin).toString();
  }

  async function api(path, options) {
    const url = apiUrl(path);

    const res = await fetch(url, {
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce
      },
      ...options
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
      console.error('[OuInPo Flashcards] API error', res.status, url, data);
      throw new Error(data.message || 'Erreur API');
    }

    return data;
  }



  function renderDomainOptions(app, domains) {

    const select = qs('.ouinpo-fc-domain-select', app);

    if (!select) return;



    const previous = select.value || app.dataset.defaultDomain || cfg.domain || '';

    select.innerHTML = '<option value="">Tous les domaines vus</option>';



    (domains || []).forEach(domain => {

      const opt = document.createElement('option');

      opt.value = String(domain.domain_slug || '');

      opt.textContent = `${domain.domain || domain.domain_slug} (${domain.total_cards || 0})`;

      if (String(opt.value) === String(previous)) opt.selected = true;

      select.appendChild(opt);

    });

  }



  function pruneSelectionToVisible(app, decks) {

    const state = appState(app);

    const visible = new Set((decks || []).map(d => Number(d.id)));

    state.selectedDeckIds.forEach(id => {

      if (!visible.has(Number(id))) state.selectedDeckIds.delete(id);

    });

  }



  function activeDecks(app) {

    const state = appState(app);

    const ids = selectedDeckIds(app);

    if (!ids.length) return state.decks || [];

    const wanted = new Set(ids);

    return (state.decks || []).filter(deck => wanted.has(Number(deck.id)));

  }



  function deckStats(app) {

    const decks = activeDecks(app);

    const stats = {

      active_decks: decks.length,

      total_cards: 0,

      due_cards: 0,

      new_cards: 0,

      seen_cards: 0,

      mastered_cards: 0

    };



    decks.forEach(deck => {

      const total = Number(deck.total_cards || 0);

      const seen = Number(deck.seen_cards || 0);

      stats.total_cards += total;

      stats.due_cards += Number(deck.due_cards || 0);

      stats.seen_cards += seen;

      stats.mastered_cards += Number(deck.mastered_cards || 0);

      stats.new_cards += Math.max(0, total - seen);

    });



    return stats;

  }



  function setText(app, selector, text) {

    const el = qs(selector, app);

    if (el) el.textContent = text;

  }



  function updateKpis(app, counts) {

    const stats = deckStats(app);



    if (counts && typeof counts.due !== 'undefined') {

      stats.due_cards = Number(counts.due || 0);

    }

    if (counts && typeof counts.new !== 'undefined') {

      stats.new_cards = Number(counts.new || 0);

    }



    const progress = stats.total_cards > 0

      ? Math.round((stats.mastered_cards / stats.total_cards) * 100)

      : 0;



    const map = {

      active_decks: stats.active_decks,

      total_cards: stats.total_cards,

      mastered_cards: stats.mastered_cards,

      mastered_ratio: `${stats.mastered_cards} / ${stats.total_cards}`,

      progress_pct: `${progress}%`,

      due_cards: stats.due_cards,

      new_cards: stats.new_cards

    };



    qsa('[data-kpi]', app).forEach(el => {

      const key = el.getAttribute('data-kpi');

      el.textContent = map[key] != null ? map[key] : '0';

    });



    qsa('[data-count]', app).forEach(el => {

      const key = el.getAttribute('data-count');

      if (key === 'due') el.textContent = String(stats.due_cards);

      if (key === 'new') el.textContent = String(stats.new_cards);

    });



    const ids = selectedDeckIds(app);

    const domainLabel = currentDomainLabel(app);

    const scope = ids.length

      ? `${ids.length} paquet${ids.length > 1 ? 's' : ''} coché${ids.length > 1 ? 's' : ''}`

      : 'tous les paquets visibles';



    const summary = `${domainLabel} — ${scope} — ${stats.total_cards} carte${stats.total_cards > 1 ? 's' : ''} disponible${stats.total_cards > 1 ? 's' : ''}`;

    setText(app, '.ouinpo-fc-context-summary', summary);

    setText(app, '.ouinpo-fc-session-summary', summary);

  }



  function setFeedback(app, msg, tone) {

    const box = qs('.ouinpo-fc-feedback', app);

    if (!box) return;

    box.className = 'ouinpo-fc-feedback' + (tone ? ' is-' + tone : '');

    box.textContent = msg || '';

  }



  function emptyCard(app, message) {

    const card = qs('.ouinpo-fc-card', app);

    if (!card) return;



    card.classList.add('is-empty');

    qs('.ouinpo-fc-meta', app).innerHTML = '';

    qs('.ouinpo-fc-front', app).innerHTML = esc(message || 'Aucune carte disponible.');



    const back = qs('.ouinpo-fc-back', app);

    back.innerHTML = '';

    back.hidden = true;



    qs('.ouinpo-fc-reveal', app).hidden = true;

    qs('.ouinpo-fc-grade-actions', app).hidden = true;

    app.dataset.cardId = '';

  }



  function renderCard(app, card) {

    if (!card) {

      emptyCard(app, 'Plus rien pour le moment. Tu peux revenir demain, changer de domaine ou modifier les paquets cochés.');

      return;

    }



    const meta = [];

    meta.push(card.deck_title || 'Paquet');

    if (card.level) meta.push(card.level);

    if (card.track) meta.push(card.track);

    if (card.card_type) meta.push(card.card_type);

    if (card.box) meta.push('boîte ' + card.box);



    qs('.ouinpo-fc-meta', app).innerHTML = meta.map(x => `<span>${esc(x)}</span>`).join('');

    qs('.ouinpo-fc-front', app).innerHTML = card.front_html || '';



    const back = qs('.ouinpo-fc-back', app);

    back.innerHTML = card.back_html || '';

    back.hidden = true;



    qs('.ouinpo-fc-card', app).classList.remove('is-empty');

    qs('.ouinpo-fc-reveal', app).hidden = false;

    qs('.ouinpo-fc-grade-actions', app).hidden = true;

    app.dataset.cardId = String(card.id);

  }



  function renderDeckCards(app, decks) {

    const wrap = qs('.ouinpo-fc-decks', app);

    const state = appState(app);

    if (!wrap) return;



    wrap.innerHTML = '';



    if (!decks || !decks.length) {

      wrap.innerHTML = '<p class="ouinpo-fc-empty-list">Aucun paquet disponible pour ce domaine. Il faut peut-être marquer les compétences correspondantes comme vues en cours.</p>';

      updateKpis(app);

      return;

    }



    decks.forEach(deck => {

      const id = Number(deck.id);

      const checked = state.selectedDeckIds.has(id);

      const total = Number(deck.total_cards || 0);

      const seen = Number(deck.seen_cards || 0);

      const newCards = Math.max(0, total - seen);

      const mastered = Number(deck.mastered_cards || 0);



      const article = document.createElement('article');

      article.className = 'ouinpo-fc-deck-card' + (checked ? ' is-selected' : '');

      article.innerHTML = `

        <label class="ouinpo-fc-deck-choice">

          <input type="checkbox" class="ouinpo-fc-deck-checkbox" value="${id}" ${checked ? 'checked' : ''}>

          <span>

            <strong>${esc(deck.title)}</strong>

            <small>${esc(deck.track)} · ${esc(deck.level)}</small>

          </span>

        </label>

        <p>${esc(deck.description || '')}</p>

        <div class="ouinpo-fc-deck-meta">

          <span>${total} cartes</span>

          <span>${mastered}/${total} mémorisées</span>

          <span>${Number(deck.due_cards || 0)} à revoir</span>

          <span>${newCards} nouvelles</span>

        </div>`;

      wrap.appendChild(article);

    });



    qsa('.ouinpo-fc-deck-checkbox', wrap).forEach(input => {

      input.addEventListener('change', async () => {

        const id = Number(input.value);

        if (input.checked) state.selectedDeckIds.add(id);

        else state.selectedDeckIds.delete(id);



        input.closest('.ouinpo-fc-deck-card')?.classList.toggle('is-selected', input.checked);

        updateKpis(app);



        if (state.sessionStarted) {

          try {

            setFeedback(app, '', '');

            await loadSession(app);

          } catch (err) {

            setFeedback(app, err.message || 'Impossible de charger la session.', 'error');

          }

        }

      });

    });



    updateKpis(app);

  }



  async function loadSummary(app) {

    const data = await api('/me' + commonParams(app, true));

    const state = appState(app);



    if (!state.domainsLoaded) {

      state.domains = data.domains || [];

      renderDomainOptions(app, state.domains);

      state.domainsLoaded = true;

    }



    state.decks = data.decks || [];

    pruneSelectionToVisible(app, state.decks);

    renderDeckCards(app, state.decks);

    updateKpis(app, data.counts || {});

  }



  async function refreshDeckList(app) {

    const data = await api('/decks' + commonParams(app, false));

    const state = appState(app);

    state.decks = data.decks || [];

    pruneSelectionToVisible(app, state.decks);

    renderDeckCards(app, state.decks);

    updateKpis(app);

  }



  async function loadSession(app) {

    const data = await api('/session' + commonParams(app, true));

    updateKpis(app, data.counts || {});

    renderCard(app, data.card || null);

  }



  async function startSession(app) {

    const state = appState(app);

    const session = qs('.ouinpo-fc-session', app);

    const chooser = qs('.ouinpo-fc-chooser', app);



    state.sessionStarted = true;

    app.classList.add('is-session-running');

    if (session) session.hidden = false;

    if (chooser) chooser.open = false;



    emptyCard(app, 'Chargement de la première carte…');

    setFeedback(app, '', '');

    await loadSession(app);

    if (session) session.scrollIntoView({ behavior: 'smooth', block: 'start' });

  }



  async function grade(app, gradeValue) {

    const cardId = app.dataset.cardId;

    if (!cardId) return;



    const body = {

      card_id: Number(cardId),

      grade: gradeValue

    };



    const ids = selectedDeckIds(app);

    if (ids.length) body.deck_ids = ids.join(',');



    const domain = currentDomain(app);

    if (domain) body.domain_slug = domain;

    if (cfg.track) body.track = cfg.track;

    if (cfg.level) body.level = cfg.level;



    const data = await api('/grade', {

      method: 'POST',

      body: JSON.stringify(body)

    });



    await refreshDeckList(app);

    updateKpis(app, data.counts || {});

    renderCard(app, data.card || null);



    const review = data.review || {};

    setFeedback(app, `Carte enregistrée · boîte ${review.new_box || '?'} · prochaine révision le ${review.next_review_at || '?'}.`, 'ok');

  }



  function bind(app) {

    qs('.ouinpo-fc-reveal', app)?.addEventListener('click', () => {

      qs('.ouinpo-fc-back', app).hidden = false;

      qs('.ouinpo-fc-grade-actions', app).hidden = false;

    });



    qsa('.ouinpo-fc-grade', app).forEach(btn => {

      btn.addEventListener('click', async () => {

        try {

          await grade(app, btn.getAttribute('data-grade'));

        } catch (err) {

          setFeedback(app, err.message || 'Impossible d’enregistrer la réponse.', 'error');

        }

      });

    });



    qs('.ouinpo-fc-domain-select', app)?.addEventListener('change', async () => {

      try {

        const state = appState(app);

        state.selectedDeckIds.clear();

        setFeedback(app, '', '');

        await refreshDeckList(app);



        if (state.sessionStarted) {

          await loadSession(app);

        }

      } catch (err) {

        setFeedback(app, err.message || 'Impossible de charger ce domaine.', 'error');

      }

    });



    qs('.ouinpo-fc-select-all', app)?.addEventListener('click', async () => {

      try {

        const state = appState(app);

        state.decks.forEach(deck => state.selectedDeckIds.add(Number(deck.id)));

        renderDeckCards(app, state.decks);



        if (state.sessionStarted) {

          await loadSession(app);

        }

      } catch (err) {

        setFeedback(app, err.message || 'Impossible de sélectionner les paquets.', 'error');

      }

    });



    qs('.ouinpo-fc-clear-selection', app)?.addEventListener('click', async () => {

      try {

        const state = appState(app);

        state.selectedDeckIds.clear();

        renderDeckCards(app, state.decks);



        if (state.sessionStarted) {

          await loadSession(app);

        }

      } catch (err) {

        setFeedback(app, err.message || 'Impossible de réinitialiser la sélection.', 'error');

      }

    });



    qs('.ouinpo-fc-start-session', app)?.addEventListener('click', async () => {

      try {

        await startSession(app);

      } catch (err) {

        setFeedback(app, err.message || 'Impossible de démarrer la révision.', 'error');

      }

    });



    qs('.ouinpo-fc-start-all', app)?.addEventListener('click', async () => {

      try {

        const state = appState(app);

        const select = qs('.ouinpo-fc-domain-select', app);

        state.selectedDeckIds.clear();

        if (select) select.value = '';

        await refreshDeckList(app);

        await startSession(app);

      } catch (err) {

        setFeedback(app, err.message || 'Impossible de démarrer la révision globale.', 'error');

      }

    });



    qs('.ouinpo-fc-edit-selection', app)?.addEventListener('click', () => {

      const chooser = qs('.ouinpo-fc-chooser', app);

      if (chooser) {

        chooser.open = true;

        chooser.scrollIntoView({ behavior: 'smooth', block: 'start' });

      }

    });

  }



  document.addEventListener('DOMContentLoaded', async () => {

    const apps = qsa('.ouinpo-fc-app');

    for (const app of apps) {

      bind(app);

      try {

        const defaultDeck = Number(app.dataset.defaultDeck || cfg.deck || 0);

        if (defaultDeck > 0) appState(app).selectedDeckIds.add(defaultDeck);



        await loadSummary(app);

        emptyCard(app, 'Choisis un domaine ou des paquets, puis clique sur “Commencer la révision”.');

      } catch (err) {

        emptyCard(app, err.message || 'Erreur de chargement.');

        setFeedback(app, err.message || 'Erreur de chargement.', 'error');

      }

    }

  });

})();

