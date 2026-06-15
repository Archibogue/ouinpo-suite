(function () {
  const sel = (s, el = document) => el.querySelector(s);

  // Échappe le HTML pour afficher du texte brut (code, etc.)
  function sfEscapeHtml(str) {
    return (str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function sfLooksLikeHtml(s) {
    return /<\/?[a-z][\s\S]*>/i.test(s);
  }

  // Rend du Markdown inline très simple :
  // - **gras**, __gras__
  // - *italique*, _italique_
  // - `code`
  // puis convertit les retours à la ligne en <br>
  function sfRenderInlineMarkdown(str) {
    if (sfLooksLikeHtml(str)) return str;
    let safe = sfEscapeHtml(str);

    safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    safe = safe.replace(/__(.+?)__/g, '<strong>$1</strong>');

    safe = safe.replace(/\*(.+?)\*/g, '<em>$1</em>');
    safe = safe.replace(/_(.+?)_/g, '<em>$1</em>');

    safe = safe.replace(/`([^`]+)`/g, '<code>$1</code>');

    return safe.replace(/\n/g, '<br>');
  }

  // Convertit le texte de SegFault en HTML :
  // - texte normal -> <br> + rendu Markdown simple
  // - blocs ```lang\ncode\n``` -> bloc code
  function sfRenderMessageHtml(text) {
    if (!text) return '';
    if (sfLooksLikeHtml(text)) return text;

    const parts = text.split('```');
    if (parts.length === 1) return sfRenderInlineMarkdown(text);

    let html = '';
    parts.forEach((chunk, index) => {
      if (index % 2 === 0) {
        if (!chunk) return;
        html += sfRenderInlineMarkdown(chunk);
      } else {
        const cleaned = chunk.replace(/\r/g, '');
        const lines = cleaned.split('\n');
        let lang = (lines[0] || '').trim().toLowerCase();
        let codeLines = lines.slice(1);

        if (!lang || /\s/.test(lang)) {
          lang = '';
          codeLines = lines;
        }

        const codeText = codeLines.join('\n');

        html += `
<div class="ouinpo-code-block">
  <button type="button" class="ouinpo-copy-code">Copier</button>
  <pre><code${lang ? ' class="language-' + lang + '"' : ''}>${sfEscapeHtml(codeText)}</code></pre>
</div>`;
      }
    });

    return html;
  }

  // --- Répliques humoristiques pendant la recherche ---
  const SF_THINKING_QUOTES = [
    "😼 *Mmmh… fouillons les grimoires numériques...*",
    "😾 *Un instant, je recalcule le carré du néant...*",
    "🐾 *Patience, bipèdes. J'interroge les oracles de la NSI...*",
    "👁️‍🗨️ *Le Professeur Bogue aurait dit : « Ce n’est pas un bug, c’est de la métaphysique. »*",
    "💤 *Segment de faute détecté. J’essaie de le flatter...*",
    "😸 *Si seulement les humains commentaient leur code...*",
    "😼 *Je renifle un indice dans le cache de WordPress...*",
    "🐱‍👓 *Hmm… je crois que ça compile dans une autre dimension...*",
    "🌀 *Je tourne en rond comme un pointeur fou...*",
    "😼 *Silence ! Je médite sur le sens du bit...*",
    "🐈 *Encore une question existentielle... le cours m'avait prevenu.*",
    "😾 *Je fouille dans la pile d’appels. Ça sent le débordement...*",
    "😼 *Le Professeur Bogue dirait que c’est trivial. Moi, je doute.*",
    "🐾 *Recherche en cours… ou peut-être sieste, je ne sais plus.*",
    "🐈‍⬛ *Je trie les octets comme d’autres trient les pensées.*",
    "😼 *Toujours ces humains qui veulent des réponses immédiates...*",
    "👁️‍🗨️ *J'entends l'echo des cours ranges dans le disque dur...*",
    "🌀 *Une boucle infinie ? Non, c’est juste mon inspiration.*",
    "🐾 *Un segfault par-ci, un paradoxe par-là… la routine, quoi.*",
    "😼 *Pendant ce temps, le professeur croit encore que je dors.*"
  ];

  /**
   * Message d’accueil si l’historique est vide.
   */
  function greet(box, addBubble) {
    const messages = sel('.sf-messages', box);
    if (!messages || messages.dataset.greeted === '1' || messages.childElementCount > 0) return;
    let intro =
      "Miaou. Je suis *SegFault* — ton assistant NSI. " +
      "Pose ta question sur Python, algorithmique, structures de données, réseaux, bases ou web. " +
      "Hors-sujet ? Je t’indiquerai un cours NSI à la place. 🐾";
    if (
      window.OUINPO_SF &&
      typeof window.OUINPO_SF.chatbox_welcome_message === 'string'
    ) {
      intro = window.OUINPO_SF.chatbox_welcome_message.trim();
    }

    if (!intro) {
      messages.dataset.greeted = '1';
      return;
    }

    addBubble(intro, 'assistant');
    messages.dataset.greeted = '1';
  }

  /**
   * Connecte une fenêtre de chat (inline ou flottante)
   */
  function wireChatBox(box) {
    if (!box || box.dataset.wired === '1') return;
    box.dataset.wired = '1';

    const messages  = sel('.sf-messages', box);
    if (messages) {
      messages.querySelectorAll('.sf-sources:empty').forEach((el) => el.remove());
    }
    const inputWrap = sel('.sf-input', box);
    const input     = sel('textarea, input', inputWrap);
    const sendBtn   = sel('button', inputWrap);
    const consentEl = sel('#sf-consent', box);
    const clearBtn  = sel('#sf-clear', box);
    let session     = localStorage.getItem('sf_session') || '';

    function addBubble(text, role = 'user', sources) {
      const d = document.createElement('div');
      d.className = 'sf-bubble ' + role;
      const raw = (text || '').toString();

      if (role === 'user') {
        d.textContent = raw;
        d.classList.add('sf-msg-text');
      } else {
        d.innerHTML = sfRenderMessageHtml(raw);
      }
      messages.appendChild(d);

// Sources désormais intégrées dans la bulle côté PHP.
// Ancien encart séparé désactivé.

      messages.scrollTop = messages.scrollHeight;
      return d;
    }

    window.SegFaultChat = window.SegFaultChat || {};
    window.SegFaultChat.addBubble = (html, role = 'assistant', sources) =>
      addBubble(html, role, sources);

    async function send() {
      const q = (input && input.value ? input.value : '').trim();
      if (!q) return;

      addBubble(q, 'user');
      if (input) input.value = '';
      if (sendBtn) sendBtn.disabled = true;

      const funny = SF_THINKING_QUOTES[Math.floor(Math.random() * SF_THINKING_QUOTES.length)];
      const thinkingBubble = addBubble(funny, 'assistant');
      thinkingBubble.classList.add('sf-thinking');

      try {
        const usePublicChat = !(OUINPO_SF && parseInt(OUINPO_SF.is_logged_in || 0, 10))
          && !!(OUINPO_SF && parseInt(OUINPO_SF.public_ai || 0, 10))
          && !!(OUINPO_SF && OUINPO_SF.public_rest);
        const chatEndpoint = usePublicChat ? OUINPO_SF.public_rest : OUINPO_SF.rest;

        const res = await fetch(chatEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': OUINPO_SF.nonce || '',
          },
          body: JSON.stringify({
            message: q,
            session: usePublicChat ? '' : session,
            consent: usePublicChat ? false : !!(consentEl && consentEl.checked),
            page: window.location.href
          }),
        });

        const data = await res.json();
        
        thinkingBubble.classList.add('sf-done');
        setTimeout(() => thinkingBubble.remove(), 400);

        if (data && data.session) {
          session = data.session;
          localStorage.setItem('sf_session', session);
        }

        addBubble(
          (data && data.answer) ? data.answer : '…',
          'assistant',
          (data && data.sources) ? data.sources : []
        );
      } catch (e) {
        try {
          thinkingBubble.classList.add('sf-done');
          setTimeout(() => thinkingBubble.remove(), 400);
        } catch (_){}
        addBubble("Miaou : un poil s’est coincé dans le réseau. Réessaie.", 'assistant');
      } finally {
        if (sendBtn) sendBtn.disabled = false;
        if (input) input.focus();
      }
    }

    (function bindMobileSendFallback(){
      if (box.dataset.sendFallback === '1') return;
      box.dataset.sendFallback = '1';

      let lastTap = 0;

      function handler(e){
        const btn = e.target && e.target.closest ? e.target.closest('.sf-input button') : null;
        if (!btn || !box.contains(btn)) return;

        const now = Date.now();
        if (now - lastTap < 350) return;
        lastTap = now;

        e.preventDefault();
        e.stopPropagation();
        send();
      }

      document.addEventListener('touchend', handler, { passive: false });
      document.addEventListener('click', handler, true);
    })();

    let lastSendTs = 0;

    function triggerSend(e) {
      const now = Date.now();
      if (now - lastSendTs < 350) return;
      lastSendTs = now;

      if (e && e.preventDefault) e.preventDefault();
      if (e && e.stopPropagation) e.stopPropagation();
      send();
    }

    if (sendBtn) {
      sendBtn.addEventListener('click', triggerSend);
      sendBtn.addEventListener('touchend', triggerSend, { passive: false });
      sendBtn.addEventListener('pointerup', triggerSend);
    }

    if (input) {
      input.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;

        const isTextarea = (input.tagName && input.tagName.toLowerCase() === 'textarea');

        if (isTextarea) {
          if (!e.shiftKey) {
            e.preventDefault();
            send();
          }
          return;
        }

        e.preventDefault();
        send();
      });
    }

    if (clearBtn) {
      clearBtn.addEventListener('click', async () => {
        try {
          if (session) {
            await fetch((OUINPO_SF.memory_rest || OUINPO_SF.rest.replace(/\/chat$/, '/memory/clear')), {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': OUINPO_SF.nonce,
              },
              body: JSON.stringify({ session }),
            });
          }
        } catch (_) {}
    
        try { localStorage.removeItem('sf_session'); } catch(_) {}
        session = '';
        addBubble("Mémoire oubliée. (Cette fois, côté serveur aussi.)", 'assistant');
      });
    }

    greet(box, addBubble);
  }

  // --- Câbler inline et flottant ---
  const inlineBox   = document.getElementById('sf-chat-inline');
  const floatingBox = document.getElementById('sf-chat-floating');

  if (inlineBox) wireChatBox(inlineBox);
  if (floatingBox) {
    wireChatBox(floatingBox);

    // État initial cohérent
    if (floatingBox.classList.contains('sf-hidden')) {
      floatingBox.style.display = 'none';
      floatingBox.setAttribute('aria-hidden', 'true');
    } else {
      floatingBox.style.display = 'flex';
      floatingBox.setAttribute('aria-hidden', 'false');
    }
  }

  // --- Helpers ouverture / fermeture du chat flottant ---
  function getFloatingChat() {
    return document.getElementById('sf-chat-floating');
  }

  function getToggleButton() {
    return document.getElementById('sf-toggle');
  }

  function ensureGreeting(chat) {
    const messages = sel('.sf-messages', chat);
    if (!messages || messages.childElementCount > 0) return;

    const addBubble = (text, role = 'assistant', sources) => {
      const d = document.createElement('div');
      d.className = 'sf-bubble ' + role;
      const raw = (text || '').toString();

      if (role === 'user') {
        d.textContent = raw;
        d.classList.add('sf-msg-text');
      } else {
        d.innerHTML = sfRenderMessageHtml(raw);
      }
      messages.appendChild(d);

// Sources désormais intégrées dans la bulle côté PHP.
// Ancien encart séparé désactivé.

      messages.scrollTop = messages.scrollHeight;
    };

    greet(chat, addBubble);
  }

  function openFloatingChat() {
    const chat = getFloatingChat();
    if (!chat) return;

    chat.style.display = 'flex';
    chat.setAttribute('aria-hidden', 'false');

    requestAnimationFrame(() => {
      chat.classList.remove('sf-hidden');
    });

    wireChatBox(chat);
    ensureGreeting(chat);
  }

  function closeFloatingChat() {
    const chat = getFloatingChat();
    if (!chat) return;

    chat.classList.add('sf-hidden');
    chat.setAttribute('aria-hidden', 'true');
    chat.classList.remove('sf-fullscreen-active');

    const toggleBtn = getToggleButton();
    if (toggleBtn) toggleBtn.classList.remove('sf-toggle-hidden');

    window.setTimeout(() => {
      if (chat.classList.contains('sf-hidden')) {
        chat.style.display = 'none';
      }
    }, 350);
  }

  function toggleFloatingChat() {
    const chat = getFloatingChat();
    if (!chat) return;

    if (chat.classList.contains('sf-hidden')) {
      openFloatingChat();
    } else {
      closeFloatingChat();
    }
  }

  // --- Ouverture / fermeture du chat flottant ---
  document.addEventListener('click', (e) => {
    const toggle = e.target && e.target.closest ? e.target.closest('#sf-toggle') : null;
    const close  = e.target && e.target.closest ? e.target.closest('.sf-close') : null;

    if (toggle) {
      e.preventDefault();
      toggleFloatingChat();
      return;
    }

    if (close) {
      e.preventDefault();
      closeFloatingChat();
    }
  });

  // --- Ouvrir liens dans nouvel onglet ---
  document.addEventListener('click', (e) => {
    const a = e.target && e.target.closest ? e.target.closest('.ouinpo-sf-widget a') : null;
    if (!a) return;
    e.preventDefault();
    const url = a.getAttribute('href');
    if (url) window.open(url, '_blank', 'noopener,noreferrer');
  });

  // --- Bouton "Copier" pour les blocs de code du chat SegFault ---
  document.addEventListener('click', (e) => {
    const btn = e.target && e.target.closest ? e.target.closest('.ouinpo-copy-code') : null;
    if (!btn) return;

    const block = btn.closest('.ouinpo-code-block');
    if (!block) return;

    const codeEl = block.querySelector('code');
    if (!codeEl) return;

    const text = codeEl.textContent || '';
    if (!text) return;

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => {
        const old = btn.textContent;
        btn.textContent = 'Copié !';
        setTimeout(() => { btn.textContent = old || 'Copier'; }, 1500);
      }).catch(() => {
        const old = btn.textContent;
        btn.textContent = 'Erreur';
        setTimeout(() => { btn.textContent = old || 'Copier'; }, 1500);
      });
    } else {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.left = '-9999px';
      document.body.appendChild(textarea);
      textarea.select();
      try {
        document.execCommand('copy');
        const old = btn.textContent;
        btn.textContent = 'Copié !';
        setTimeout(() => { btn.textContent = old || 'Copier'; }, 1500);
      } catch (_err) {
        const old = btn.textContent;
        btn.textContent = 'Erreur';
        setTimeout(() => { btn.textContent = old || 'Copier'; }, 1500);
      }
      document.body.removeChild(textarea);
    }
  });

  // --- Mode plein écran ---
  document.addEventListener('click', (e) => {
    const btn = e.target && e.target.closest ? e.target.closest('.sf-fullscreen') : null;
    if (!btn) return;

    const chat   = document.getElementById('sf-chat-floating');
    const toggle = document.getElementById('sf-toggle');
    if (!chat) return;

    chat.classList.toggle('sf-fullscreen-active');

    if (toggle) {
      if (chat.classList.contains('sf-fullscreen-active')) {
        toggle.classList.add('sf-toggle-hidden');
      } else {
        toggle.classList.remove('sf-toggle-hidden');
      }
    }
  });

  // ======================== Parcours (pleine page) + filtre domaine ========================
  (async function () {
    const root = document.querySelector('#sf-parcours');
    if (!root) return;

    const limit = parseInt(root.dataset.limit || '8', 10);
    const listHost = document.getElementById('sf-parcours-list');
    const select = document.getElementById('sf-domain');
    const status = document.getElementById('sf-parcours-status');
    if (!listHost) return;

    function setStatus(t) {
      if (status) status.textContent = t || '';
    }

    function renderList(cards) {
      const safeCards = Array.isArray(cards) ? cards : [];

      if (safeCards.length === 0) {
        listHost.innerHTML = '<p>Aucun exercice trouvé pour ce filtre.</p>';
        return;
      }

      const ul = document.createElement('ul');
      ul.className = 'sf-parcours-ul';

      safeCards.forEach((x) => {
        if (!x || !x.url) return;

        const li = document.createElement('li');
        li.className = 'sf-parcours-li';
        if (x.status) li.dataset.status = x.status;

        const a = document.createElement('a');
        a.href = x.url;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = x.title || x.url;

        li.appendChild(a);

        if (x.badge) {
          const badge = document.createElement('span');
          badge.className = 'sf-parcours-badge';
          badge.textContent = x.badge;
          badge.style.marginLeft = '.5rem';
          badge.style.opacity = '.85';
          li.appendChild(badge);
        }

        if (x.excerpt) {
          const p = document.createElement('div');
          p.className = 'sf-parcours-excerpt';
          p.textContent = x.excerpt;
          p.style.marginTop = '.25rem';
          p.style.opacity = '.9';
          li.appendChild(p);
        }

        if (x.exo_domains) {
          const d = document.createElement('div');
          d.className = 'sf-parcours-domains';
          d.style.opacity = '.75';
          d.style.marginTop = '.15rem';
          d.textContent = 'Domaines : ' + x.exo_domains;
          li.appendChild(d);
        }

        ul.appendChild(li);
      });

      listHost.innerHTML = '';
      listHost.appendChild(ul);
    }

    async function load(domainValue) {
      listHost.innerHTML = '<p>Chargement du parcours…</p>';
      setStatus('');

      try {
        const base = (OUINPO_SF.rest || '');
        const urlBase = base.replace(/\/chat\/?(\?.*)?$/, '/parcours');

        const qs = new URLSearchParams();
        qs.set('limit', String(limit));
        if (domainValue) qs.set('domain', domainValue);

        const res = await fetch(urlBase + '?' + qs.toString(), {
          method: 'GET',
          headers: { 'X-WP-Nonce': OUINPO_SF.nonce },
        });

        const data = await res.json();

        if (!data || !data.ok) {
          listHost.innerHTML = '<p>Impossible de générer ton parcours.</p>';
          return;
        }

        if (select && Array.isArray(data.domains)) {
          const prev = select.value;
          select.innerHTML = '<option value="">Tous</option>';
          data.domains.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.value;
            opt.textContent = d.label || d.value;
            select.appendChild(opt);
          });
          select.value = domainValue || data.selected_domain || prev || '';
        }

        const cards = data.cards || [];
        renderList(cards);
        setStatus(`(${cards.length} exercice(s))`);
      } catch (e) {
        listHost.innerHTML = '<p>Réseau capricieux : je n’ai pas pu charger ton parcours.</p>';
      }
    }

    if (select) {
      select.addEventListener('change', () => load(select.value));
    }

    await load(select ? select.value : '');
  })();

  // =================== Notifier "Nouvelles ressources" — badge flottant & persistant ===================
})();
