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
                return { ok: false, msg: 'Reponse non JSON', raw: text };
            }
        });
    }

    function escapeHTML(value) {
        return String(value || '').replace(/[&<>"']/g, function(char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }

    function b64(value) {
        return btoa(unescape(encodeURIComponent(value)));
    }

    function parseGateData(root) {
        try {
            return JSON.parse(root.getAttribute('data-enigmes') || '[]');
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
        var cooldownTimer = null;
        var busy = false;

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
        current = parseInt(root.dataset.current || root.dataset.progress, 10) || 0;

        function find(selector) {
            return root.querySelector(selector);
        }

        function setCooldown(seconds) {
            var button = find('#ouinpo-submit');
            var msg = find('#ouinpo-msg');
            var remaining = parseInt(seconds, 10) || 0;

            if (cooldownTimer) {
                window.clearInterval(cooldownTimer);
                cooldownTimer = null;
            }
            if (!button || remaining <= 0) {
                if (button) {
                    button.disabled = busy;
                }
                return;
            }

            button.disabled = true;
            function tick() {
                if (remaining <= 0) {
                    window.clearInterval(cooldownTimer);
                    cooldownTimer = null;
                    button.disabled = busy;
                    if (msg && !busy) {
                        msg.innerHTML = '';
                    }
                    return;
                }
                if (msg) {
                    msg.innerHTML = '<p class="lab-note">Tu pourras reessayer dans ' + remaining + ' s.</p>';
                }
                remaining -= 1;
            }
            tick();
            cooldownTimer = window.setInterval(tick, 1000);
        }

        function render(index) {
            var slot = find('#ouinpo-question');
            var item;
            var submitButton;

            if (!slot) {
                return;
            }
            if (index >= data.length) {
                slot.innerHTML = '<p><em>Aucune autre enigme pour l\'instant.</em></p>';
                return;
            }

            item = data[index];
            slot.innerHTML = ''
                + '<div class="eldritch">'
                + '<h3>Enigme ' + (index + 1) + ' / ' + data.length + ' - <small>' + escapeHTML(item.theme || item.title || '') + '</small></h3>'
                + '<p>' + escapeHTML(item.prompt) + '</p>'
                + (item.help ? '<p class="lab-note">' + escapeHTML(item.help) + '</p>' : '')
                + '<input id="ouinpo-answer" placeholder="Ta reponse" autocomplete="off">'
                + '<button id="ouinpo-submit" type="button">Valider</button>'
                + '<div id="ouinpo-msg" class="ouinpo-gate-msg"></div>'
                + '</div>';

            submitButton = find('#ouinpo-submit');
            if (submitButton) {
                submitButton.addEventListener('click', function() {
                    var answer = find('#ouinpo-answer');
                    submit(index, item.id, answer ? (answer.value || '') : '');
                });
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
                    .then(function(response) { return response.text(); })
                    .then(function(html) { container.innerHTML = html; });
            } else if (reveal === 'link') {
                container.innerHTML = '<p><a class="button" href="' + escapeHTML(plink) + '">Acceder a la page secrete</a></p>';
            } else if (reveal === 'redirect') {
                container.innerHTML = '<p>Redirection en cours... Si rien ne se passe, <a href="' + escapeHTML(plink) + '">clique ici</a>.</p>';
                window.location.href = root.dataset.redirectUrl || plink;
            }
        }

        function submit(index, questionId, answer) {
            var formData = new FormData();
            var button = find('#ouinpo-submit');
            var msg = find('#ouinpo-msg');

            if (busy) {
                return;
            }
            busy = true;
            if (button) {
                button.disabled = true;
            }
            if (msg) {
                msg.innerHTML = '<p class="lab-note">Validation en cours...</p>';
            }

            formData.append('action', 'ouinpo_check');
            formData.append('index', index);
            formData.append('question_id', questionId || '');
            formData.append('payload', b64(answer));
            formData.append('page', page);
            formData.append('nonce', nonce);

            fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(parseJSONSmart)
                .then(function(json) {
                    var count = find('#ouinpo-count');
                    var feedback = json.feedback || json.msg || '';

                    if (!msg) {
                        return;
                    }
                    if (json.ok) {
                        if (count) {
                            count.textContent = json.progress;
                        }
                        msg.innerHTML = '<p class="lab-note">' + escapeHTML(feedback || 'Bravo, enigme resolue !') + '</p>';
                        current = index + 1;
                        if (json.progress >= needed) {
                            afterCompleted();
                        } else {
                            window.setTimeout(function() { render(current); }, 450);
                        }
                    } else if (json.error === 'cooldown') {
                        setCooldown(json.retry_after || 1);
                    } else {
                        msg.innerHTML = '<p class="lab-note danger">' + escapeHTML(feedback || 'Mauvaise reponse.') + '</p>';
                        setCooldown(json.retry_after || 0);
                    }
                })
                .catch(function(error) {
                    if (msg) {
                        msg.innerHTML = '<p class="lab-note danger">Erreur reseau.' + escapeHTML(error && error.message ? (' ' + error.message) : '') + '</p>';
                    }
                })
                .finally(function() {
                    busy = false;
                    if (!cooldownTimer && button) {
                        button.disabled = false;
                    }
                });
        }

        render(current);
        if (parseInt((find('#ouinpo-count') || {}).textContent, 10) >= needed && reveal !== 'embed') {
            afterCompleted();
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
                btn.textContent = '...';
            }
            if (out) {
                out.innerHTML = '<p class="lab-note">Envoi en cours...</p>';
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
                            + '<p class="lab-note">Signature enregistree. Merci, <strong>' + escapeHTML(fd.get('nom') || '') + '</strong> !</p>'
                            + '<p><a href="' + escapeHTML(certificateUrl) + '" class="button" target="_blank" rel="noopener">'
                            + 'Telecharger mon certificat de reussite'
                            + '</a></p>';
                    } else {
                        msg = (json && (json.msg || json.raw)) ? (json.msg || json.raw) : 'Erreur inconnue';
                        out.innerHTML = '<p class="lab-note danger">Erreur : ' + escapeHTML(msg) + '</p>';
                    }
                })
                .catch(function(error) {
                    if (out) {
                        out.innerHTML = '<p class="lab-note danger">Erreur reseau : ' + escapeHTML(error && error.message ? error.message : '') + '</p>';
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
