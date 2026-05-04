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

        document.querySelectorAll('a[data-confirm]').forEach(function(link) {
            link.addEventListener('click', function(event) {
                var message = link.getAttribute('data-confirm') || '';

                if (message && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }

    function initCheckAll() {
        document.querySelectorAll('[data-check-all-target]').forEach(function(master) {
            master.addEventListener('change', function() {
                var target = master.getAttribute('data-check-all-target') || '';
                var items;
                var i;

                if (!target) {
                    return;
                }

                items = document.querySelectorAll(target);

                for (i = 0; i < items.length; i++) {
                    items[i].checked = master.checked;
                }
            });
        });
    }

    function initSolutionRows() {
        var addBtn = document.getElementById('ouin-add-solution');
        var tbody;
        var idx;

        if (!addBtn) {
            return;
        }

        tbody = document.querySelector(addBtn.getAttribute('data-solutions-target') || '');

        if (!tbody) {
            return;
        }

        idx = parseInt(addBtn.getAttribute('data-next-index') || '0', 10);

        if (isNaN(idx)) {
            idx = 0;
        }

        addBtn.addEventListener('click', function() {
            var tpl = ''
                + '<tr>'
                + '<td><input type="number" min="1" name="solutions[${i}][solution_order]" value="${i+1}" class="ouinpo-admin-input-order"></td>'
                + '<td><input type="text" name="solutions[${i}][title]" class="regular-text" value="Soluce"></td>'
                + '<td class="ouinpo-admin-cell-centered"><input type="checkbox" name="solutions[${i}][is_official]" value="1" checked></td>'
                + '<td>'
                + '<textarea name="solutions[${i}][content]" rows="6" class="ouinpo-admin-full-width"></textarea>'
                + '<input type="hidden" name="solutions[${i}][id]" value="0">'
                + '</td>'
                + '</tr>';

            tbody.insertAdjacentHTML('beforeend', tpl.replaceAll('${i}', idx++));
            addBtn.setAttribute('data-next-index', String(idx));
        });
    }

    function initPathTemplateToggle() {
        var checkbox = document.getElementById('ouinpo-is-template');
        var rows = document.querySelectorAll('.ouinpo-targets-row');
        var templateRows = document.querySelectorAll('.ouinpo-template-meta-row');

        function refreshTargetsVisibility() {
            if (!checkbox) {
                return;
            }

            rows.forEach(function(row) {
                row.style.display = checkbox.checked ? 'none' : '';
            });

            templateRows.forEach(function(row) {
                row.style.display = checkbox.checked ? '' : 'none';
            });
        }

        if (checkbox) {
            checkbox.addEventListener('change', refreshTargetsVisibility);
            refreshTargetsVisibility();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        initConfirmations();
        initCheckAll();
        initSolutionRows();
        initPathTemplateToggle();
    });
})();
