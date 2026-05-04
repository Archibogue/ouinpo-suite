(function() {
    function parseJSONSmart(response) {
        var contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            return response.json();
        }

        return response.text().then(function(text) {
            try {
                return JSON.parse(text);
            } catch (error) {
                return { ok: false, msg: 'Réponse non JSON', raw: text };
            }
        });
    }

    function b64(value) {
        return btoa(unescape(encodeURIComponent(value)));
    }

    function parseGateData(root) {
        var raw = root.getAttribute('data-enigmes') || '[]';

        try {
            return JSON.parse(raw);
        } catch (error) {
            return [];
        }
    }

    function initGateGame(root) {
        var data;
        var needed;
        var page;
        var reveal;
        var plink;
        var ajaxUrl;
        var nonce;
        var current;

        if (!root || root.dataset.ouinpoGateReady === '1') {
            return;
        }

        root.dataset.ouinpoGateReady = '1';

        data = parseGateData(root);
        needed = parseInt(root.dataset.needed, 10);
        page = root.dataset.page;
        reveal = root.dataset.reveal || 'embed';
        plink = root.dataset.plink || '#';
        ajaxUrl = root.dataset.ajaxUrl || '';
        nonce = root.dataset.nonce || '';
        current = parseInt(root.dataset.progress, 10) || 0;

        function find(selector) {
            return root.querySelector(selector);
        }

        function render(index) {
            var slot = find('#ouinpo-question');
            var item;
            var submitButton;

            if (!slot) {
                return;
            }

            if (index >= data.length) {
                slot.innerHTML = "<p><em>Aucune autre énigme pour l’instant.</em></p>";
                return;
            }

            item = data[index];
            slot.innerHTML = ''
                + '<div class="eldritch">'
                + '<h3>Énigme ' + (index + 1) + ' / ' + data.length + ' — <small>' + item.theme + '</small></h3>'
                + '<p>' + item.prompt + '</p>'
                + '<input id="ouinpo-answer" placeholder="Ta réponse">'
                + '<button id="ouinpo-submit" type="button">Valider</button>'
                + '<div id="ouinpo-msg" class="ouinpo-gate-msg"></div>'
                + '</div>';

            submitButton = find('#ouinpo-submit');
            if (submitButton) {
                submitButton.addEventListener('click', function() {
                    var answer = find('#ouinpo-answer');
                    submit(index, answer ? (answer.value || '') : '');
                }, { passive: true });
            }
        }

        function afterCompleted() {
            var final = find('#ouinpo-final');
            var container = find('#ouinpo-secret-content');
            var formData;

            if (final) {
                final.style.display = 'block';
            }

            if (!container) {
                return;
            }

            if (reveal === 'embed') {
                formData = new FormData();
                formData.append('action', 'ouinpo_secret');
                formData.append('page', page);
                formData.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                    .then(function(response) {
                        return response.text();
                    })
                    .then(function(html) {
                        container.innerHTML = html;
                    });
            } else if (reveal === 'link') {
                container.innerHTML = '<p><a class="button" href="' + plink + '">🚪 Accéder à la page secrète</a></p>';
            } else if (reveal === 'redirect') {
                container.innerHTML = '<p>Redirection en cours… Si rien ne se passe, <a href="' + plink + '">clique ici</a>.</p>';
                window.location.href = root.dataset.redirectUrl || plink;
            }
        }

        function submit(index, answer) {
            var formData = new FormData();

            formData.append('action', 'ouinpo_check');
            formData.append('index', index);
            formData.append('payload', b64(answer));
            formData.append('page', page);
            formData.append('nonce', nonce);

            fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(parseJSONSmart)
                .then(function(json) {
                    var msg = find('#ouinpo-msg');
                    var count = find('#ouinpo-count');

                    if (!msg) {
                        return;
                    }

                    if (json.ok) {
                        if (count) {
                            count.textContent = json.progress;
                        }

                        msg.innerHTML = '<p class="lab-note">✔ Bravo, énigme résolue !</p>';
                        current = index + 1;

                        if (json.progress >= needed) {
                            afterCompleted();
                        } else {
                            render(current);
                        }
                    } else {
                        msg.innerHTML = '<p class="lab-note danger">✖ Mauvaise réponse.' + (json.msg ? (' ' + json.msg) : '') + '</p>';
                    }
                })
                .catch(function(error) {
                    var msg = find('#ouinpo-msg');

                    if (msg) {
                        msg.innerHTML = '<p class="lab-note danger">Erreur réseau.' + (error && error.message ? (' ' + error.message) : '') + '</p>';
                    }
                });
        }

        render(current);

        if (parseInt((find('#ouinpo-count') || {}).textContent, 10) >= needed) {
            if (reveal !== 'embed') {
                afterCompleted();
            }
        }
    }

    function initGateGames() {
        document.querySelectorAll('#ouinpo-game').forEach(function(root) {
            initGateGame(root);
        });
    }

    function initSignForm() {
        var form = document.querySelector('.ouinpo-sign-form');

        if (!form) {
            return;
        }

        form.addEventListener('submit', function(event) {
            var out;
            var btn;
            var fd;
            var ajaxUrl;
            var certificateUrl;

            event.preventDefault();

            out = document.getElementById('ouinpo-sign-result');
            btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
            fd = new FormData(form);
            ajaxUrl = form.getAttribute('data-ajax-url') || '';
            certificateUrl = form.getAttribute('data-certificate-url') || '';

            fd.append('action', 'ouinpo_sign');

            if (btn) {
                btn.disabled = true;
                btn.dataset._old = btn.textContent;
                btn.textContent = '…';
            }

            if (out) {
                out.innerHTML = '<p class="lab-note">⏳ Envoi en cours…</p>';
            }

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(parseJSONSmart)
                .then(function(json) {
                    var msg;

                    if (!out) {
                        return;
                    }

                    if (json && json.ok) {
                        form.remove();
                        out.innerHTML = ''
                            + '<p class="lab-note">✍️ Signature enregistrée. Merci, <strong>' + (fd.get('nom') || '') + '</strong> !</p>'
                            + '<p><a href="' + certificateUrl + '" class="button" target="_blank" rel="noopener">'
                            + '📜 Télécharger mon certificat de réussite'
                            + '</a></p>';
                    } else {
                        msg = (json && (json.msg || json.raw)) ? (json.msg || json.raw) : 'Erreur inconnue';
                        out.innerHTML = '<p class="lab-note danger">Erreur : ' + msg + '</p>';
                    }
                })
                .catch(function(error) {
                    if (out) {
                        out.innerHTML = '<p class="lab-note danger">Erreur réseau : ' + (error && error.message ? error.message : '') + '</p>';
                    }
                })
                .finally(function() {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = btn.dataset._old || 'Signer';
                    }
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initGateGames();
        initSignForm();
    });
})();
