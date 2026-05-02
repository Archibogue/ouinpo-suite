(function () {
  function restRoot() {
    const base = (window.OUINEXO && OUINEXO.api) ? OUINEXO.api : '/wp-json/';
    return base.replace(/\/+$/, '') + '/ouinpo/v1';
  }

  function commonHeaders() {
    const h = { 'Accept': 'application/json' };
    if (window.OUINEXO && OUINEXO.nonce) {
      h['X-WP-Nonce'] = OUINEXO.nonce;
    }
    return h;
  }

  function fetchJSON(url) {
    return fetch(url, {
      headers: commonHeaders(),
      credentials: 'same-origin'
    }).then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function postJSON(url, body) {
    const headers = commonHeaders();
    headers['Content-Type'] = 'application/json';

    return fetch(url, {
      method: 'POST',
      headers,
      credentials: 'same-origin',
      body: JSON.stringify(body || {})
    }).then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  let API_ROOT = null;
  let CURRENT_TITLE_BADGE_ID = 0;

  // 🔗 Domaines connus (slug -> label lisible)
  const KNOWN_DOMAIN_SLUGS = new Set();
  const KNOWN_DOMAIN_LABELS = {};

  function H(tag, attrs, text) {
    const el = document.createElement(tag);
    if (attrs) {
      for (const k in attrs) {
        if (k === 'dataset') {
          const ds = attrs[k];
          for (const dk in ds) el.dataset[dk] = ds[dk];
        } else {
          el.setAttribute(k, attrs[k]);
        }
      }
    }
    if (text != null) el.textContent = text;
    return el;
  }

  // 🔍 Essaie de deviner le niveau à partir du slug / theme
  function inferLevelFromBadge(b) {
    const slug  = (b.slug  || '').toLowerCase();
    const theme = (b.theme || '').toLowerCase();

    // Badges spéciaux
    if (theme === 'special' || slug.startsWith('special-')) {
      return 'Spécial';
    }

    // Meta / badges nommés avec le niveau
    if (slug.includes('seconde') || theme.includes('seconde')) {
      return 'Seconde';
    }
    if (
      slug.includes('premiere') || slug.includes('première') ||
      theme.includes('premiere') || theme.includes('première')
    ) {
      return 'Première';
    }
    if (slug.includes('terminale') || theme.includes('terminale')) {
      return 'Terminale';
    }

    return null; // on ne sait pas
  }

    function displayLevelLabel(level) {
      return level === 'Transversal' ? 'Transversale' : level;
    }

  // 🔧 Normalisation
  function normalizeBadgeData(raw) {
    function prettifyDomain(fromTheme) {
      if (!fromTheme) return null;
      let label = fromTheme.replace(/-/g, ' ');
      label = label.charAt(0).toUpperCase() + label.slice(1);
      return label;
    }

    function processBadge(b) {
      const theme = (b.theme || '').toString().toLowerCase();
      const slug  = (b.slug  || '').toString().toLowerCase();

      let level =
        b.level ||
        inferLevelFromBadge(b) ||
        'Transversal';

      if (theme === 'special' || slug.startsWith('special-')) {
        level = 'Spécial';
      }

      let domain_slug = b.domain_slug || null;
      let domain = b.domain || null;

      // 🧠 NOUVEAU : si pas de domain_slug, on vérifie si le theme est un slug de domaine connu
    if (!domain_slug && theme && theme !== 'special' && !theme.startsWith('meta')) {
      const t = theme.toLowerCase();
    
      if (KNOWN_DOMAIN_SLUGS.has(t)) {
        domain_slug = t;
        domain = KNOWN_DOMAIN_LABELS[t] || prettifyDomain(theme);
      } else {
        for (const known of KNOWN_DOMAIN_SLUGS) {
          if (known === t || known.startsWith(t) || t.startsWith(known)) {
            domain_slug = known;
            domain = KNOWN_DOMAIN_LABELS[known] || prettifyDomain(known);
            break;
          }
        }
      }
    }

      // ❌ On supprime l'ancien fallback "domain_slug = theme" :
      // on NE VEUT PAS que tous les thèmes deviennent des domaines.

      return Object.assign({}, b, {
        level,
        domain_slug,
        domain
      });
    }

    let meta = [];
    let special = [];
    let domainBadges = [];
    let competency = [];

    // Cas 1 : déjà structuré (meta/domain/competency/special)
    if (!Array.isArray(raw) && raw && (raw.meta || raw.domain || raw.competency || raw.special)) {
      meta         = Array.isArray(raw.meta)       ? raw.meta       : [];
      special      = Array.isArray(raw.special)    ? raw.special    : [];
      domainBadges = Array.isArray(raw.domain)     ? raw.domain     : [];
      competency   = Array.isArray(raw.competency) ? raw.competency : [];

      meta         = meta.map(processBadge);
      special      = special.map(processBadge);
      domainBadges = domainBadges.map(processBadge);
      competency   = competency.map(processBadge);
    }
    // Cas 2 : tableau simple
    else if (Array.isArray(raw)) {
      const all = raw.map(processBadge);

      all.forEach(b => {
        const theme = (b.theme || '').toString().toLowerCase();
        const slug  = (b.slug  || '').toString().toLowerCase();

        const isMeta    = theme.startsWith('meta') || slug.startsWith('meta-');
        const isSpecial = theme === 'special' || slug.startsWith('special-');

        if (isMeta) {
          meta.push(b);
        } else if (isSpecial) {
          special.push(b);
        } else if (b.domain_slug) {
          // ✅ Ici, domain_slug n'est défini que si theme == slug d'un domaine BO
          domainBadges.push(b);
        } else {
          competency.push(b);
        }
      });
    } else {
      return { meta: [], special: [], domain: [], competency: [], levels_order: [] };
    }

    return {
      meta,
      special,
      domain: domainBadges,
      competency,
      levels_order: (
        raw &&
        !Array.isArray(raw) &&
        Array.isArray(raw.levels_order) &&
        raw.levels_order.length
      )
        ? raw.levels_order
        : ['Spécial', 'Terminale', 'Première', 'Seconde', 'Transversal'],
      current_title_badge_id: (
        raw &&
        !Array.isArray(raw) &&
        raw.current_title_badge_id
      ) ? Number(raw.current_title_badge_id) : 0
    };
  }

  // 🧱 Rendu d’un badge (avec description + tier)
  function renderBadge(badge) {
    const slug  = (badge.slug || '').toLowerCase();
    const theme = (badge.theme || '').toLowerCase();

    // Tiers "classiques" bo-* (bronze/argent/or)
    let tier = '';
    if (slug.endsWith('-bronze')) tier = 'bronze';
    else if (slug.endsWith('-argent')) tier = 'silver';
    else if (slug.endsWith('-or')) tier = 'gold';

    // META / spéciaux
    const isMeta    = theme.startsWith('meta') || slug.startsWith('meta-');
    const isSpecial = (theme === 'special') || slug.startsWith('special-');

    const classes = ['ouinpo-badge-item', 'ouinpo-badge-split'];
    if (tier) classes.push('ouinpo-badge-tier-' + tier);
    if (isSpecial) classes.push('ouinpo-badge-tier-platinum');
    if (CURRENT_TITLE_BADGE_ID && badge.id === CURRENT_TITLE_BADGE_ID) {
      classes.push('ouinpo-badge-current-title');
    }

    const card = H('div', {
      class: classes.join(' '),
      dataset: {
        badgeId: badge.id || '',
        meta: isMeta ? '1' : '0',
        special: isSpecial ? '1' : '0'
      }
    });

    // Colonne gauche
    const left = H('div', { class: 'ouinpo-badge-left' });
    const titleEl = H('div', { class: 'ouinpo-badge-title' }, badge.title);
    left.appendChild(titleEl);

    if (badge.description) {
      const desc = H('div', { class: 'ouinpo-badge-description' });
      desc.textContent = badge.description;
      left.appendChild(desc);
    }

    const meta = H('div', { class: 'ouinpo-badge-meta' });
    if (badge.level) {
    meta.appendChild(H('span', { class: 'ouinpo-badge-level' }, displayLevelLabel(badge.level)));
    }
    if (badge.domain) {
      meta.appendChild(H('span', { class: 'ouinpo-badge-domain' }, badge.domain));
    }
    left.appendChild(meta);

    // Colonne droite : image
    const right = H('div', { class: 'ouinpo-badge-right' });
    if (badge.image_url) {
      const img = H('img', {
        src: badge.image_url,
        alt: badge.title,
        class: 'ouinpo-badge-bigimg'
      });
      right.appendChild(img);
    }

    card.appendChild(left);
    card.appendChild(right);

    // Clic => choisir ce badge comme titre
    card.addEventListener('click', function (e) {
      e.preventDefault();
      if (!API_ROOT || !badge.id) return;

      const ok = confirm('Utiliser ce badge comme titre affiché ?');
      if (!ok) return;

      postJSON(API_ROOT + '/me/title', { badge_id: badge.id })
        .then(resp => {
          alert('Ton titre a été mis à jour ✨');
          window.location.reload();
        })
        .catch(err => {
          console.error('Erreur mise à jour titre', err);
          alert('Impossible de mettre à jour ton titre pour le moment.');
        });
    });

    return card;
  }

  // 🧩 Rendu d’une section de badges (par niveau puis domaine)
    function renderSection(container, title, badges, options) {
      options = options || {};
      if (!badges || !badges.length) return;
    
      const section = H('section', { class: 'ouinpo-badges-section' });
      const h2 = H('h2', { class: 'ouinpo-badges-section-title' }, title);
      section.appendChild(h2);
    
      const byLevel = new Map();
    
      badges.forEach(b => {
        const levels = Array.isArray(b.levels) && b.levels.length
          ? b.levels
          : [b.level || 'Transversal'];
    
        levels.forEach(lvl => {
          let L = lvl || 'Transversal';
    
          const theme = (b.theme || '').toLowerCase();
          const slug  = (b.slug  || '').toLowerCase();
    
          if (theme === 'special' || slug.startsWith('special-')) {
            L = 'Spécial';
          }
    
          if (!byLevel.has(L)) byLevel.set(L, []);
          byLevel.get(L).push(b);
        });
      });
    
      const levelsOrdered = (
        options.levels_order && options.levels_order.length
          ? options.levels_order
          : Array.from(byLevel.keys())
      ).filter(lvl => byLevel.has(lvl));
    
      function tierWeight(badge) {
        const slug  = (badge.slug || '').toLowerCase();
        const theme = (badge.theme || '').toLowerCase();
    
        if (theme === 'special' || slug.startsWith('special-')) return 4;
        if (slug.endsWith('-or')) return 3;
        if (slug.endsWith('-argent')) return 2;
        if (slug.endsWith('-bronze')) return 1;
        return 0;
      }
    
      levelsOrdered.forEach(lvl => {
        const items = byLevel.get(lvl) || [];
        if (!items.length) return;
    
        const lvlBlock = H('div', { class: 'ouinpo-badges-level-block' });
        const lvlTitle = H('h3', { class: 'ouinpo-badges-level-title' }, displayLevelLabel(lvl));
        lvlBlock.appendChild(lvlTitle);
    
        const byDomain = new Map();
    
        items.forEach((b, idx) => {
          if (typeof b._display_idx === 'undefined') {
            b._display_idx = idx;
          }
    
          const dslug = b.domain_slug || '';
          const dlabel = b.domain || '';
    
          if (!byDomain.has(dslug)) {
            byDomain.set(dslug, { label: dlabel, items: [] });
          }
          byDomain.get(dslug).items.push(b);
        });
    
        Array.from(byDomain.entries())
          .sort((a, b) => a[1].label.localeCompare(b[1].label))
          .forEach(([slug, obj]) => {
            obj.items.sort((a, b) => {
              const wa = tierWeight(a);
              const wb = tierWeight(b);
    
              if (wa !== wb) return wb - wa;
              return (a._display_idx || 0) - (b._display_idx || 0);
            });
    
            const domainBlock = H('div', { class: 'ouinpo-badges-domain-block' });
    
            if (obj.label) {
              const domainTitle = H('h4', { class: 'ouinpo-badges-domain-title' }, obj.label);
              domainBlock.appendChild(domainTitle);
            }
    
            const grid = H('div', { class: 'ouinpo-badges-grid' });
            obj.items.forEach(badge => {
              grid.appendChild(renderBadge(badge));
            });
    
            domainBlock.appendChild(grid);
            lvlBlock.appendChild(domainBlock);
          });
    
        section.appendChild(lvlBlock);
      });
    
      container.appendChild(section);
    }
    
function applyMetaRanks() {
  // rank meta basé sur le tier réel :
  // bronze -> 1, silver -> 2, gold -> 3
  document.querySelectorAll('.ouinpo-badges-level-block').forEach(levelBlock => {
    const metas = Array.from(levelBlock.querySelectorAll('.ouinpo-badge-item[data-meta="1"]'));

    metas.forEach(el => {
      el.classList.remove(
        'ouinpo-badge-meta-rank-1',
        'ouinpo-badge-meta-rank-2',
        'ouinpo-badge-meta-rank-3'
      );

      let rank = 0;
      if (el.classList.contains('ouinpo-badge-tier-gold')) rank = 3;
      else if (el.classList.contains('ouinpo-badge-tier-silver')) rank = 2;
      else if (el.classList.contains('ouinpo-badge-tier-bronze')) rank = 1;

      // Fallback : si pas de tier (meta “hors bronze/argent/or”), on garde l'ancien comportement
      if (rank === 0) {
        const untiered = metas.filter(x =>
          !x.classList.contains('ouinpo-badge-tier-gold') &&
          !x.classList.contains('ouinpo-badge-tier-silver') &&
          !x.classList.contains('ouinpo-badge-tier-bronze')
        );
        const idx = untiered.indexOf(el);
        if (idx >= 0 && idx < 3) rank = idx + 1;
      }

      if (rank > 0) el.classList.add('ouinpo-badge-meta-rank-' + rank);
    });
  });
}

document.addEventListener('DOMContentLoaded', function () {
  const root = restRoot();
  API_ROOT = root;
  const container = document.getElementById('ouinpo-student-badges');
  if (!container) return;

  container.innerHTML = '<p>Chargement de vos badges…</p>';

  // 1) Charger les domaines pour avoir les domain_slug
  fetchJSON(root + '/competencies/options')
    .then(opt => {
      if (opt && Array.isArray(opt.domains)) {
        opt.domains.forEach(d => {
          const s = (d.slug || '').toLowerCase();
          if (!s) return;
          KNOWN_DOMAIN_SLUGS.add(s);
          KNOWN_DOMAIN_LABELS[s] = d.label || d.slug;
        });
      }
    })
    .catch(err => {
      console.error('[ouinpo] Erreur chargement domaines/compétences', err);
    })
    .then(() => {
      // 2) Charger les badges
      return fetchJSON(root + '/me/badges');
    })
    .then(raw => {
      const data = normalizeBadgeData(raw);
      CURRENT_TITLE_BADGE_ID = Number(raw.current_title_badge_id || data.current_title_badge_id || 0);

      container.innerHTML = '';

      const metaAndSpecial = []
        .concat(data.meta || [])
        .concat(data.special || []);

      renderSection(
        container,
        'Badges spéciaux & méta-badges',
        metaAndSpecial,
        { levels_order: data.levels_order || [] }
      );

      renderSection(
        container,
        'Badges de domaines',
        data.domain || [],
        { levels_order: data.levels_order || [] }
      );

      renderSection(
        container,
        'Badges de compétences',
        data.competency || [],
        { levels_order: data.levels_order || [] }
      );

      applyMetaRanks();

      if (
        !metaAndSpecial.length &&
        !(data.domain || []).length &&
        !(data.competency || []).length
      ) {
        container.innerHTML = '<p>Tu n’as encore obtenu aucun badge… mais les mystères ouinpiens t’attendent 🌙</p>';
      }
    })
    .catch(err => {
      console.error('Erreur chargement badges', err);
      container.innerHTML = '<p>Impossible de charger tes badges pour le moment.</p>';
    });
});

})();