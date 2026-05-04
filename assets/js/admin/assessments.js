(function() {
    function initConfirmations() {
        document.querySelectorAll('form[data-confirm]').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                var message = form.getAttribute('data-confirm') || '';

                if (message && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }

    function initPrintButtons() {
        document.querySelectorAll('[data-action="print"]').forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                window.print();
            });
        });
    }

    function initReloadOnChange() {
        document.querySelectorAll('[data-reload-url-base]').forEach(function(element) {
            element.addEventListener('change', function() {
                var baseUrl = element.getAttribute('data-reload-url-base') || '';
                var param = element.getAttribute('data-reload-param') || 'value';
                var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';

                if (!baseUrl) {
                    return;
                }

                window.location.href = baseUrl + separator + encodeURIComponent(param) + '=' + encodeURIComponent(element.value);
            });
        });
    }

    function initAbsentToggle() {
        document.querySelectorAll('.js-absent-toggle').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                var target = document.getElementById(checkbox.dataset.student || '');

                if (!target) {
                    return;
                }

                var disabled = checkbox.checked;
                target.classList.toggle('is-absent', disabled);

                target.querySelectorAll('select').forEach(function(select) {
                    select.disabled = disabled;
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initConfirmations();
        initPrintButtons();
        initReloadOnChange();
        initAbsentToggle();
    });
})();
