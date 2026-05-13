(function() {
    function slugify(value, separator) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, separator)
            .replace(new RegExp('^' + separator + '+|' + separator + '+$', 'g'), '');
    }

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

    function initBoCompetencySlugSync() {
        var domainChoice = document.getElementById('bo_domain_choice');
        var domainInput = document.getElementById('bo_domain');
        var domainSlugInput = document.getElementById('bo_domain_slug');
        var trackInput = document.getElementById('bo_track');
        var levelInput = document.getElementById('bo_level_id') || document.getElementById('bo_level');
        var legacyLevelInput = document.getElementById('bo_level');
        var summary = document.getElementById('bo_domain_summary');

        var competencyInput = document.getElementById('bo_competency');
        var slugInput = document.getElementById('bo_slug');

        function syncCompetencySlug() {
            var compPart;
            var domainPart;

            if (!slugInput || !competencyInput || !domainSlugInput) {
                return;
            }

            if (slugInput.dataset.manual === '1') {
                return;
            }

            compPart = slugify(competencyInput.value, '-');
            domainPart = slugify(domainSlugInput.value, '-');

            if (!compPart) {
                return;
            }

            slugInput.value = domainPart ? domainPart + '-' + compPart : compPart;
        }

        function syncDomain() {
            var option;
            var domain;
            var domainSlug;
            var track;
            var level;
            var levelId;

            if (!domainChoice) {
                return;
            }

            option = domainChoice.options[domainChoice.selectedIndex];

            if (!option || !option.value) {
                if (summary) {
                    summary.textContent = '';
                }

                return;
            }

            domain = option.dataset.domain || '';
            domainSlug = option.dataset.domainSlug || '';
            track = option.dataset.track || '';
            level = option.dataset.level || '';
            levelId = option.dataset.levelId || '';

            if (domainInput) {
                domainInput.value = domain;
            }

            if (domainSlugInput) {
                domainSlugInput.value = domainSlug;
            }

            if (trackInput) {
                trackInput.value = track;
            }

            if (levelInput) {
                levelInput.value = levelId || level;
            }

            if (legacyLevelInput && legacyLevelInput !== levelInput) {
                legacyLevelInput.value = level;
            }

            if (summary) {
                summary.textContent = 'Domaine sélectionné : ' + domain + ' — ' + track + ' / ' + level;
            }

            syncCompetencySlug();
        }

        if (domainChoice) {
            domainChoice.addEventListener('change', syncDomain);
            syncDomain();
        }

        if (competencyInput) {
            competencyInput.addEventListener('input', syncCompetencySlug);
        }

        if (slugInput) {
            slugInput.addEventListener('input', function() {
                slugInput.dataset.manual = '1';
            });

            if (!slugInput.value) {
                slugInput.dataset.manual = '0';
            }
        }
    }

    function initBoDomainSlugSync() {
        var nameInput = document.getElementById('bo_domain_name');
        var slugInput = document.getElementById('bo_domain_slug');

        if (!nameInput || !slugInput) {
            return;
        }

        nameInput.addEventListener('input', function() {
            if (slugInput.dataset.manual === '1') {
                return;
            }

            slugInput.value = slugify(nameInput.value, '-');
        });

        slugInput.addEventListener('input', function() {
            slugInput.dataset.manual = '1';
        });

        if (!slugInput.value) {
            slugInput.dataset.manual = '0';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        initConfirmations();
        initBoCompetencySlugSync();
        initBoDomainSlugSync();
    });
})();
