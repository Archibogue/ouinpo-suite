(function() {
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function initConfirmations() {
        document.querySelectorAll('form[data-confirm]').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                const message = form.getAttribute('data-confirm') || '';

                if (message && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }

    function initSubmitOnChange() {
        document.querySelectorAll('[data-submit-on-change]').forEach(function(element) {
            element.addEventListener('change', function() {
                const resetTarget = element.getAttribute('data-reset-target');

                if (resetTarget) {
                    const target = document.querySelector(resetTarget);

                    if (target) {
                        target.value = '';
                    }
                }

                if (element.form) {
                    element.form.submit();
                }
            });
        });
    }

    function initCheckAll() {
        const master = document.getElementById('ouin-check-all');

        if (!master) {
            return;
        }

        master.addEventListener('change', function() {
            document.querySelectorAll('input[name="user_ids[]"]').forEach(function(checkbox) {
                checkbox.checked = master.checked;
            });
        });
    }

    function initBadgeMediaPicker() {
        const input = document.getElementById('ouin-badge-image');
        const preview = document.getElementById('ouin-badge-preview');
        const mediaButton = document.getElementById('ouin-badge-media');
        const clearButton = document.getElementById('ouin-badge-image-clear');

        if (!input || !preview) {
            return;
        }

        let frame = null;

        function refreshPreview() {
            const url = (input.value || '').trim();

            if (url) {
                preview.innerHTML = '<img src="' + escapeHtml(url) + '" alt="Aperçu du badge">';
            } else {
                preview.innerHTML = '<em>Aucune image</em>';
            }
        }

        if (mediaButton) {
            mediaButton.addEventListener('click', function(event) {
                event.preventDefault();

                if (!window.wp || !window.wp.media) {
                    return;
                }

                if (frame) {
                    frame.open();
                    return;
                }

                frame = window.wp.media({
                    title: 'Choisir une image de badge',
                    button: { text: 'Utiliser cette image' },
                    library: { type: 'image' },
                    multiple: false
                });

                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();

                    if (attachment && attachment.url) {
                        input.value = attachment.url;
                        refreshPreview();
                    }
                });

                frame.open();
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', function(event) {
                event.preventDefault();
                input.value = '';
                refreshPreview();
            });
        }

        input.addEventListener('input', refreshPreview);
        input.addEventListener('change', refreshPreview);
        refreshPreview();
    }

    document.addEventListener('DOMContentLoaded', function() {
        initConfirmations();
        initSubmitOnChange();
        initCheckAll();
        initBadgeMediaPicker();
    });
})();
