(function() {
    function initAssessmentBuilder() {
        const builder = document.querySelector('.ouinpo-builder-layout');
        const form = document.getElementById('ouinpo-builder-create-form');

        if (!builder || !form) {
            return;
        }

        const countEl = document.getElementById('ouinpo-builder-count');
        const minutesEl = document.getElementById('ouinpo-builder-minutes');
        const pointsEl = document.getElementById('ouinpo-builder-points');
        const warningEl = document.getElementById('ouinpo-builder-warning');
        const targetEl = document.getElementById('target-minutes');

        function numberValue(input) {
            const n = parseFloat(String(input.value || '').replace(',', '.'));
            return Number.isFinite(n) ? n : 0;
        }

        function update() {
            let count = 0;
            let minutes = 0;
            let points = 0;

            form.querySelectorAll('.ouinpo-builder-exo').forEach(function(card) {
                const check = card.querySelector('.ouinpo-builder-check');
                const pointInput = card.querySelector('.ouinpo-builder-points');
                const selected = check && check.checked;

                card.classList.toggle('is-selected', selected);

                if (selected) {
                    count += 1;
                    minutes += parseInt(card.dataset.minutes || '0', 10) || 0;
                    points += pointInput ? numberValue(pointInput) : 0;
                }
            });

            countEl.textContent = String(count);
            minutesEl.textContent = String(minutes);
            pointsEl.textContent = String(Math.round(points * 100) / 100).replace('.', ',');

            const target = parseInt(targetEl.value || '0', 10) || 0;

            if (target > 0 && count > 0) {
                const diff = minutes - target;

                if (diff > 15) {
                    warningEl.style.display = 'block';
                    warningEl.textContent = 'Attention : la sélection dépasse la durée cible d’environ ' + diff + ' minutes.';
                } else if (diff < -20) {
                    warningEl.style.display = 'block';
                    warningEl.textContent = 'La sélection est nettement sous la durée cible.';
                } else {
                    warningEl.style.display = 'none';
                    warningEl.textContent = '';
                }
            } else {
                warningEl.style.display = 'none';
                warningEl.textContent = '';
            }
        }

        form.addEventListener('change', update);
        form.addEventListener('input', update);

        form.addEventListener('submit', function(e) {
            if (form.querySelectorAll('.ouinpo-builder-check:checked').length === 0) {
                e.preventDefault();
                alert('Sélectionne au moins un exercice.');
            }
        });

        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAssessmentBuilder);
    } else {
        initAssessmentBuilder();
    }
})();
