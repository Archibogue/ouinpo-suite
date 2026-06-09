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

    function initHintRows() {
        var addBtn = document.getElementById('ouin-add-hint');
        var tbody;
        var idx;

        if (!addBtn) {
            return;
        }

        tbody = document.querySelector(addBtn.getAttribute('data-hints-target') || '');
        if (!tbody) {
            return;
        }

        idx = parseInt(addBtn.getAttribute('data-next-index') || '4', 10);
        if (isNaN(idx) || idx < 1) {
            idx = 4;
        }

        addBtn.addEventListener('click', function() {
            var tpl = ''
                + '<tr>'
                + '<th scope="row"><label for="hint_${i}">Indice ${i}</label></th>'
                + '<td>'
                + '<p><label>Ordre <input type="number" min="1" name="hint_orders[${i}]" value="${i}" class="small-text"></label></p>'
                + '<textarea id="hint_${i}" name="hints[${i}]" rows="6" class="ouinpo-admin-full-width"></textarea>'
                + '<p class="description">Tu peux saisir du HTML simple (bold, listes, liens).</p>'
                + '</td>'
                + '</tr>';

            tbody.insertAdjacentHTML('beforeend', tpl.replaceAll('${i}', idx));
            idx += 1;
            addBtn.setAttribute('data-next-index', String(idx));
        });
    }

    function clearWrittenClone(root) {
        root.querySelectorAll('input, textarea, select').forEach(function(field) {
            var type = (field.getAttribute('type') || '').toLowerCase();

            if (type === 'hidden' && /\[id\]$/.test(field.name || '')) {
                field.value = '0';
                return;
            }

            if (type === 'checkbox') {
                field.checked = field.name.indexOf('[is_active]') !== -1;
                return;
            }

            if (field.tagName === 'SELECT' && field.multiple) {
                Array.prototype.forEach.call(field.options, function(option) {
                    option.selected = false;
                });
                return;
            }

            if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
                return;
            }

            if (type !== 'number') {
                field.value = '';
            }
        });
    }

    function replaceWrittenIndex(root, pattern, replacement) {
        root.querySelectorAll('[name]').forEach(function(field) {
            field.name = field.name.replace(pattern, replacement);
        });
    }

    function initWrittenSubjectsBuilder() {
        var root = document.getElementById('ouinpo-written-exercises');
        var addExercise = document.getElementById('ouinpo-add-written-exercise');

        if (!root || !addExercise) {
            return;
        }

        addExercise.addEventListener('click', function() {
            var blocks = root.querySelectorAll('.ouinpo-written-exercise');
            var source = blocks[blocks.length - 1];
            var next = blocks.length;
            var clone;

            if (!source) {
                return;
            }

            clone = source.cloneNode(true);
            replaceWrittenIndex(clone, /written_exercises\[\d+\]/g, 'written_exercises[' + next + ']');
            clearWrittenClone(clone);
            clone.querySelectorAll('input[name$="[exercise_order]"]').forEach(function(field) {
                field.value = String(next + 1);
            });
            root.appendChild(clone);
        });

        root.addEventListener('click', function(event) {
            var questionBtn = event.target.closest('.ouinpo-add-written-question');
            var hintBtn = event.target.closest('.ouinpo-add-written-hint');
            var exercise;
            var questionsRoot;
            var questions;
            var question;
            var hintsRoot;
            var hints;
            var clone;
            var exerciseIndex;
            var questionIndex;
            var next;

            if (questionBtn) {
                exercise = questionBtn.closest('.ouinpo-written-exercise');
                questionsRoot = exercise ? exercise.querySelector('.ouinpo-written-questions') : null;
                questions = questionsRoot ? questionsRoot.querySelectorAll('.ouinpo-written-question') : [];
                question = questions[questions.length - 1];

                if (!exercise || !questionsRoot || !question) {
                    return;
                }

                exerciseIndex = Array.prototype.indexOf.call(root.querySelectorAll('.ouinpo-written-exercise'), exercise);
                next = questions.length;
                clone = question.cloneNode(true);
                replaceWrittenIndex(
                    clone,
                    /written_exercises\[\d+\]\[questions\]\[\d+\]/g,
                    'written_exercises[' + exerciseIndex + '][questions][' + next + ']'
                );
                clearWrittenClone(clone);
                clone.querySelectorAll('input[name$="[question_order]"]').forEach(function(field) {
                    field.value = String(next + 1);
                });
                questionsRoot.appendChild(clone);
                return;
            }

            if (hintBtn) {
                question = hintBtn.closest('.ouinpo-written-question');
                exercise = hintBtn.closest('.ouinpo-written-exercise');
                hintsRoot = question ? question.querySelector('.ouinpo-written-hints') : null;
                hints = hintsRoot ? hintsRoot.querySelectorAll('.ouinpo-written-hint') : [];

                if (!question || !exercise || !hintsRoot || !hints.length) {
                    return;
                }

                exerciseIndex = Array.prototype.indexOf.call(root.querySelectorAll('.ouinpo-written-exercise'), exercise);
                questionIndex = Array.prototype.indexOf.call(exercise.querySelectorAll('.ouinpo-written-question'), question);
                next = hints.length;
                clone = hints[hints.length - 1].cloneNode(true);
                replaceWrittenIndex(
                    clone,
                    /written_exercises\[\d+\]\[questions\]\[\d+\]\[hints\]\[\d+\]/g,
                    'written_exercises[' + exerciseIndex + '][questions][' + questionIndex + '][hints][' + next + ']'
                );
                clearWrittenClone(clone);
                clone.querySelectorAll('input[name$="[hint_order]"]').forEach(function(field) {
                    field.value = String(next + 1);
                });
                clone.querySelectorAll('input[name$="[title]"]').forEach(function(field) {
                    field.value = 'Aide IA';
                });
                hintsRoot.appendChild(clone);
            }
        });
    }

    function utf8ToBase64Url(value) {
        var binary = '';
        var bytes;
        var i;
        var chunk;

        if (window.TextEncoder) {
            bytes = new TextEncoder().encode(String(value || ''));

            for (i = 0; i < bytes.length; i += 8192) {
                chunk = bytes.slice(i, i + 8192);
                binary += String.fromCharCode.apply(null, chunk);
            }
        } else {
            binary = unescape(encodeURIComponent(String(value || '')));
        }

        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    }

    function addEncodedField(form, sourceName, sourceValue) {
        var key = utf8ToBase64Url(sourceName);
        var input = document.createElement('input');

        input.type = 'hidden';
        input.name = 'ouinpo_written_encoded[' + key + ']';
        input.value = utf8ToBase64Url(sourceValue);

        form.appendChild(input);
    }

    function initWrittenSubjectEncodedSubmit() {
        var form = document.querySelector('form[data-ouinpo-written-encoded-form="1"]');

        if (!form || form.dataset.ouinpoWrittenEncodedBooted === '1') {
            return;
        }

        form.dataset.ouinpoWrittenEncodedBooted = '1';

        form.addEventListener('submit', function() {
            if (window.tinyMCE && typeof window.tinyMCE.triggerSave === 'function') {
                window.tinyMCE.triggerSave();
            }

            form.querySelectorAll('input[name^="ouinpo_written_encoded["]').forEach(function(field) {
                field.remove();
            });

            form.querySelectorAll('textarea[name="statement"], textarea[name^="written_exercises["]').forEach(function(textarea) {
                var name = textarea.getAttribute('name') || '';
                var value = textarea.value || '';

                if (!name) {
                    return;
                }

                addEncodedField(form, name, value);
                textarea.value = '';
            });
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
        initHintRows();
        initSolutionRows();
        initWrittenSubjectsBuilder();
        initWrittenSubjectEncodedSubmit();
        initPathTemplateToggle();
    });
})();
