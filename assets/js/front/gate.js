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
        initSignForm();
    });
})();
