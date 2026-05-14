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

    function normalizeTitle(text) {
        return (text || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function sectionForTitle(title) {
        if (title.indexOf('architecture ia') !== -1 || title.indexOf('reglages ia generaux') !== -1) {
            return 'overview';
        }

        if (title.indexOf('moteur principal') !== -1 || title.indexOf('secours connecte') !== -1) {
            return 'providers';
        }

        if (title.indexOf('prompts') !== -1) {
            return 'prompts';
        }

        if (title.indexOf('acces publics') !== -1) {
            return 'public';
        }

        if (title.indexOf('information rgpd') !== -1 || title.indexOf('acces, memoire') !== -1) {
            return 'privacy';
        }

        if (title.indexOf('rag') !== -1 || title.indexOf('indexation') !== -1) {
            return 'rag';
        }

        return 'overview';
    }

    function initSettingsTabs() {
        var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-ouinpo-sf-tab]'));
        var table = document.querySelector('.ouinpo-sf-settings-table');

        if (!tabs.length || !table) {
            return;
        }

        var rows = Array.prototype.slice.call(table.querySelectorAll('tr'));
        var currentSection = 'overview';

        rows.forEach(function(row) {
            var title = row.querySelector('h2');

            if (title) {
                currentSection = sectionForTitle(normalizeTitle(title.textContent));
                row.classList.add('ouinpo-sf-section-heading');
            }

            row.setAttribute('data-ouinpo-sf-section', currentSection);
        });

        function showSection(section) {
            rows.forEach(function(row) {
                row.hidden = row.getAttribute('data-ouinpo-sf-section') !== section;
            });

            tabs.forEach(function(tab) {
                var active = tab.getAttribute('data-ouinpo-sf-tab') === section;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            try {
                window.localStorage.setItem('ouinpo_sf_settings_section', section);
            } catch (e) {}
        }

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                showSection(tab.getAttribute('data-ouinpo-sf-tab') || 'overview');
            });
        });

        var initial = 'overview';
        try {
            initial = window.localStorage.getItem('ouinpo_sf_settings_section') || initial;
        } catch (e) {}

        if (!tabs.some(function(tab) { return tab.getAttribute('data-ouinpo-sf-tab') === initial; })) {
            initial = 'overview';
        }

        showSection(initial);
    }

    document.addEventListener('DOMContentLoaded', function() {
        initConfirmations();
        initSettingsTabs();
    });
})();
