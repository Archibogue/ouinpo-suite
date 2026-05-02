<?php
// Module interne OuInPo Suite : Recherche textuelle.

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('ouinpo_recherche_textuelle', function ($atts) {
    $atts = shortcode_atts([
        'texte' => 'ABAAABCD',
        'motif' => 'ABC',
        'titre' => 'Simulation interactive — recherche textuelle',
    ], $atts, 'ouinpo_recherche_textuelle');

    static $instance = 0;
    $instance++;

    $id = 'ouinpo-rt-' . $instance . '-' . wp_rand(1000, 9999);

    ob_start();
    ?>
    <div class="ouinpo-rt" id="<?php echo esc_attr($id); ?>"
         data-initial-text="<?php echo esc_attr($atts['texte']); ?>"
         data-initial-pattern="<?php echo esc_attr($atts['motif']); ?>">

        <div class="ouinpo-rt__header">
            <h3 class="ouinpo-rt__title"><?php echo esc_html($atts['titre']); ?></h3>
            <p class="ouinpo-rt__subtitle">Compare pas à pas l’algorithme naïf et Boyer-Moore version <em>bad character</em>.</p>
        </div>

        <div class="ouinpo-rt__controls">
            <div class="ouinpo-rt__field">
                <label for="<?php echo esc_attr($id); ?>-text">Texte</label>
                <input id="<?php echo esc_attr($id); ?>-text" type="text" class="ouinpo-rt__input js-rt-text" value="<?php echo esc_attr($atts['texte']); ?>" />
            </div>
            <div class="ouinpo-rt__field">
                <label for="<?php echo esc_attr($id); ?>-pattern">Motif</label>
                <input id="<?php echo esc_attr($id); ?>-pattern" type="text" class="ouinpo-rt__input js-rt-pattern" value="<?php echo esc_attr($atts['motif']); ?>" />
            </div>
            <div class="ouinpo-rt__field ouinpo-rt__field--small">
                <label for="<?php echo esc_attr($id); ?>-speed">Vitesse</label>
                <select id="<?php echo esc_attr($id); ?>-speed" class="ouinpo-rt__input js-rt-speed">
                    <option value="1200">Lente</option>
                    <option value="700" selected>Moyenne</option>
                    <option value="350">Rapide</option>
                </select>
            </div>
        </div>

        <div class="ouinpo-rt__buttons">
            <button type="button" class="js-rt-build">Relancer la simulation</button>
            <button type="button" class="js-rt-prev">◀ Étape précédente</button>
            <button type="button" class="js-rt-next">Étape suivante ▶</button>
            <button type="button" class="js-rt-play">Lecture automatique</button>
            <button type="button" class="js-rt-reset">Réinitialiser</button>
        </div>

        <div class="ouinpo-rt__status js-rt-global-status"></div>

        <div class="ouinpo-rt__panels">
            <section class="ouinpo-rt__panel">
                <h4>Algorithme naïf</h4>
                <div class="ouinpo-rt__stats js-rt-naive-stats"></div>
                <div class="ouinpo-rt__viz js-rt-naive-viz"></div>
                <div class="ouinpo-rt__explain js-rt-naive-explain"></div>
            </section>

            <section class="ouinpo-rt__panel">
                <h4>Boyer-Moore — bad character</h4>
                <div class="ouinpo-rt__stats js-rt-bm-stats"></div>
                <div class="ouinpo-rt__viz js-rt-bm-viz"></div>
                <div class="ouinpo-rt__explain js-rt-bm-explain"></div>
                <div class="ouinpo-rt__table-wrap">
                    <h5>Table bad character</h5>
                    <div class="js-rt-bm-table"></div>
                </div>
            </section>
        </div>
    </div>
    <?php

    static $assets_printed = false;
    if (!$assets_printed) {
        $assets_printed = true;
        ?>
        <style>
            .ouinpo-rt {
                margin: 2rem 0;
                padding: 1.25rem;
                border: 1px solid rgba(201, 167, 92, 0.35);
                border-radius: 14px;
                background: rgba(20, 20, 22, 0.92);
                color: #f3ead7;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            }
            .ouinpo-rt__title {
                margin: 0 0 0.35rem;
                color: #f0d9a1;
            }
            .ouinpo-rt__subtitle {
                margin: 0 0 1rem;
                opacity: 0.9;
            }
            .ouinpo-rt__controls,
            .ouinpo-rt__buttons,
            .ouinpo-rt__panels {
                display: grid;
                gap: 0.9rem;
            }
            .ouinpo-rt__controls {
                grid-template-columns: 1fr 1fr 160px;
                margin-bottom: 1rem;
            }
            .ouinpo-rt__buttons {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                margin-bottom: 1rem;
            }
            .ouinpo-rt__field label {
                display: block;
                font-weight: 700;
                margin-bottom: 0.35rem;
                color: #f0d9a1;
            }
            .ouinpo-rt__input,
            .ouinpo-rt button {
                width: 100%;
                box-sizing: border-box;
                border-radius: 10px;
                border: 1px solid rgba(201, 167, 92, 0.4);
                background: rgba(255,255,255,0.06);
                color: #f7f2e8;
                padding: 0.7rem 0.8rem;
                font-size: 0.95rem;
            }
            .ouinpo-rt button {
                cursor: pointer;
                font-weight: 700;
                transition: transform 0.15s ease, background 0.15s ease;
            }
            .ouinpo-rt button:hover {
                transform: translateY(-1px);
                background: rgba(201, 167, 92, 0.12);
            }
            .ouinpo-rt__status,
            .ouinpo-rt__stats,
            .ouinpo-rt__explain,
            .ouinpo-rt__table-wrap {
                margin-top: 0.75rem;
                padding: 0.8rem;
                border-radius: 10px;
                background: rgba(255,255,255,0.05);
            }
            .ouinpo-rt__status {
                margin-bottom: 1rem;
                border-left: 4px solid #c9a75c;
            }
            .ouinpo-rt__panels {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: start;
            }
            .ouinpo-rt__panel {
                min-width: 0;
                padding: 1rem;
                border-radius: 12px;
                background: rgba(255,255,255,0.04);
                border: 1px solid rgba(255,255,255,0.08);
            }
            .ouinpo-rt__panel h4,
            .ouinpo-rt__panel h5 {
                margin-top: 0;
                color: #f0d9a1;
            }
            .ouinpo-rt__viz {
                overflow-x: auto;
                padding-bottom: 0.5rem;
            }
            .ouinpo-rt__row {
                display: flex;
                gap: 0.2rem;
                margin: 0.3rem 0;
                min-height: 2.3rem;
                align-items: center;
            }
            .ouinpo-rt__label {
                min-width: 72px;
                font-weight: 700;
                color: #f0d9a1;
            }
            .ouinpo-rt__cell {
                width: 2rem;
                height: 2rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                font-size: 0.95rem;
                border: 1px solid rgba(255,255,255,0.14);
                background: rgba(255,255,255,0.04);
                flex: 0 0 2rem;
            }
            .ouinpo-rt__cell--empty {
                visibility: hidden;
            }
            .ouinpo-rt__cell--match {
                background: rgba(48, 144, 81, 0.28);
                border-color: rgba(84, 196, 121, 0.8);
            }
            .ouinpo-rt__cell--mismatch {
                background: rgba(153, 49, 49, 0.28);
                border-color: rgba(233, 108, 108, 0.85);
            }
            .ouinpo-rt__cell--active {
                outline: 2px solid #f0d9a1;
                outline-offset: 1px;
            }
            .ouinpo-rt__cell--found {
                background: rgba(102, 84, 190, 0.35);
                border-color: rgba(171, 150, 255, 0.9);
            }
            .ouinpo-rt__meta {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 0.5rem;
            }
            .ouinpo-rt__badge {
                padding: 0.45rem 0.6rem;
                border-radius: 8px;
                background: rgba(255,255,255,0.04);
                border: 1px solid rgba(255,255,255,0.08);
            }
            .ouinpo-rt__bc-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.95rem;
            }
            .ouinpo-rt__bc-table th,
            .ouinpo-rt__bc-table td {
                border: 1px solid rgba(255,255,255,0.12);
                padding: 0.45rem 0.55rem;
                text-align: center;
            }
            .ouinpo-rt__bc-table th {
                color: #f0d9a1;
            }
            @media (max-width: 900px) {
                .ouinpo-rt__controls,
                .ouinpo-rt__panels {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <script>
            (function() {
                function sanitizeInput(str) {
                    return String(str || '').replace(/\s+/g, ' ').trim();
                }

                function escapeHtml(str) {
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                function buildBadCharTable(pattern) {
                    const table = {};
                    for (let i = 0; i < pattern.length; i++) {
                        table[pattern[i]] = i;
                    }
                    return table;
                }

                function simulateNaive(text, pattern) {
                    const frames = [];
                    const occurrences = [];
                    let comparisons = 0;
                    let alignments = 0;
                    const n = text.length;
                    const m = pattern.length;

                    if (!m || m > n) {
                        frames.push({
                            shift: 0,
                            comparedTextIndex: null,
                            comparedPatternIndex: null,
                            matchedPairs: [],
                            mismatchedPair: null,
                            occurrences: [],
                            comparisons: 0,
                            alignments: 0,
                            done: true,
                            explanation: !m
                                ? 'Le motif est vide. Une simulation intéressante exige au moins un caractère.'
                                : 'Le motif est plus long que le texte. Aucune occurrence possible.'
                        });
                        return frames;
                    }

                    for (let shift = 0; shift <= n - m; shift++) {
                        alignments++;
                        const matchedPairs = [];
                        let mismatch = false;

                        for (let j = 0; j < m; j++) {
                            comparisons++;
                            const pair = { textIndex: shift + j, patternIndex: j };
                            const isMatch = text[shift + j] === pattern[j];

                            frames.push({
                                shift,
                                comparedTextIndex: shift + j,
                                comparedPatternIndex: j,
                                matchedPairs: matchedPairs.slice(),
                                mismatchedPair: isMatch ? null : pair,
                                occurrences: occurrences.slice(),
                                comparisons,
                                alignments,
                                done: false,
                                explanation: isMatch
                                    ? 'Comparaison réussie à gauche vers la droite : on poursuit.'
                                    : 'Échec de comparaison : l’algorithme naïf décale le motif d’un seul cran.'
                            });

                            if (isMatch) {
                                matchedPairs.push(pair);
                            } else {
                                mismatch = true;
                                break;
                            }
                        }

                        if (!mismatch) {
                            occurrences.push(shift);
                            frames.push({
                                shift,
                                comparedTextIndex: null,
                                comparedPatternIndex: null,
                                matchedPairs: matchedPairs.slice(),
                                mismatchedPair: null,
                                occurrences: occurrences.slice(),
                                comparisons,
                                alignments,
                                done: false,
                                foundRange: { start: shift, end: shift + m - 1 },
                                explanation: 'Motif trouvé. L’algorithme naïf continuera au cran suivant pour chercher d’autres occurrences.'
                            });
                        }
                    }

                    frames.push({
                        shift: Math.max(0, n - m),
                        comparedTextIndex: null,
                        comparedPatternIndex: null,
                        matchedPairs: [],
                        mismatchedPair: null,
                        occurrences: occurrences.slice(),
                        comparisons,
                        alignments,
                        done: true,
                        explanation: occurrences.length
                            ? 'Simulation terminée.'
                            : 'Simulation terminée. Aucune occurrence trouvée.'
                    });

                    return frames;
                }

                function simulateBoyerMooreBadCharacter(text, pattern) {
                    const frames = [];
                    const occurrences = [];
                    let comparisons = 0;
                    let alignments = 0;
                    const n = text.length;
                    const m = pattern.length;
                    const badChar = buildBadCharTable(pattern);

                    if (!m || m > n) {
                        frames.push({
                            shift: 0,
                            comparedTextIndex: null,
                            comparedPatternIndex: null,
                            matchedPairs: [],
                            mismatchedPair: null,
                            occurrences: [],
                            comparisons: 0,
                            alignments: 0,
                            done: true,
                            badChar,
                            explanation: !m
                                ? 'Le motif est vide. Une simulation intéressante exige au moins un caractère.'
                                : 'Le motif est plus long que le texte. Aucune occurrence possible.'
                        });
                        return { frames, badChar };
                    }

                    let shift = 0;
                    while (shift <= n - m) {
                        alignments++;
                        let j = m - 1;
                        const matchedPairs = [];

                        while (j >= 0) {
                            comparisons++;
                            const textIndex = shift + j;
                            const pair = { textIndex, patternIndex: j };
                            const isMatch = pattern[j] === text[textIndex];

                            frames.push({
                                shift,
                                comparedTextIndex: textIndex,
                                comparedPatternIndex: j,
                                matchedPairs: matchedPairs.slice(),
                                mismatchedPair: isMatch ? null : pair,
                                occurrences: occurrences.slice(),
                                comparisons,
                                alignments,
                                done: false,
                                badChar,
                                explanation: isMatch
                                    ? 'Comparaison de droite à gauche réussie : Boyer-Moore continue vers la gauche.'
                                    : 'Échec de comparaison : on utilise la table bad character pour choisir le décalage.'
                            });

                            if (isMatch) {
                                matchedPairs.push(pair);
                                j--;
                            } else {
                                break;
                            }
                        }

                        if (j < 0) {
                            occurrences.push(shift);
                            let nextShift = 1;
                            if (shift + m < n) {
                                const nextChar = text[shift + m];
                                nextShift = Math.max(1, m - (badChar[nextChar] ?? -1) - 1);
                            }

                            frames.push({
                                shift,
                                comparedTextIndex: null,
                                comparedPatternIndex: null,
                                matchedPairs: matchedPairs.slice(),
                                mismatchedPair: null,
                                occurrences: occurrences.slice(),
                                comparisons,
                                alignments,
                                done: false,
                                badChar,
                                foundRange: { start: shift, end: shift + m - 1 },
                                explanation: 'Motif trouvé. Boyer-Moore décale ensuite le motif pour poursuivre la recherche.'
                            });

                            shift += nextShift;
                        } else {
                            const badCharSeen = text[shift + j];
                            const last = badChar[badCharSeen] ?? -1;
                            const delta = Math.max(1, j - last);

                            frames.push({
                                shift,
                                comparedTextIndex: shift + j,
                                comparedPatternIndex: j,
                                matchedPairs: matchedPairs.slice(),
                                mismatchedPair: { textIndex: shift + j, patternIndex: j },
                                occurrences: occurrences.slice(),
                                comparisons,
                                alignments,
                                done: false,
                                badChar,
                                explanation: 'Le caractère « ' + badCharSeen + ' » apparaît en dernière position ' + last + ' dans le motif. Décalage choisi : ' + delta + '. '
                            });

                            shift += delta;
                        }
                    }

                    frames.push({
                        shift: Math.max(0, Math.min(shift, n - m)),
                        comparedTextIndex: null,
                        comparedPatternIndex: null,
                        matchedPairs: [],
                        mismatchedPair: null,
                        occurrences: occurrences.slice(),
                        comparisons,
                        alignments,
                        done: true,
                        badChar,
                        explanation: occurrences.length
                            ? 'Simulation terminée.'
                            : 'Simulation terminée. Aucune occurrence trouvée.'
                    });

                    return { frames, badChar };
                }

                function buildRow(label, chars, options) {
                    const html = chars.map(function(cell) {
                        const value = cell.empty ? '&nbsp;' : escapeHtml(cell.char);
                        const classes = ['ouinpo-rt__cell'];
                        if (cell.empty) classes.push('ouinpo-rt__cell--empty');
                        if (cell.match) classes.push('ouinpo-rt__cell--match');
                        if (cell.mismatch) classes.push('ouinpo-rt__cell--mismatch');
                        if (cell.active) classes.push('ouinpo-rt__cell--active');
                        if (cell.found) classes.push('ouinpo-rt__cell--found');
                        return '<span class="' + classes.join(' ') + '">' + value + '</span>';
                    }).join('');
                    return '<div class="ouinpo-rt__row"><div class="ouinpo-rt__label">' + escapeHtml(label) + '</div><div>' + html + '</div></div>';
                }

                function makeCellsText(text, frame) {
                    return Array.from(text).map(function(ch, idx) {
                        const cell = { char: ch, empty: false, match: false, mismatch: false, active: false, found: false };
                        if (frame.foundRange && idx >= frame.foundRange.start && idx <= frame.foundRange.end) {
                            cell.found = true;
                        }
                        if (frame.comparedTextIndex === idx) {
                            cell.active = true;
                        }
                        if ((frame.matchedPairs || []).some(function(p) { return p.textIndex === idx; })) {
                            cell.match = true;
                        }
                        if (frame.mismatchedPair && frame.mismatchedPair.textIndex === idx) {
                            cell.mismatch = true;
                        }
                        return cell;
                    });
                }

                function makeCellsPattern(textLength, pattern, frame) {
                    const cells = [];
                    for (let i = 0; i < frame.shift; i++) {
                        cells.push({ char: '', empty: true });
                    }
                    for (let j = 0; j < pattern.length; j++) {
                        const globalIndex = frame.shift + j;
                        const cell = { char: pattern[j], empty: false, match: false, mismatch: false, active: false, found: false };
                        if (frame.foundRange && globalIndex >= frame.foundRange.start && globalIndex <= frame.foundRange.end) {
                            cell.found = true;
                        }
                        if (frame.comparedPatternIndex === j) {
                            cell.active = true;
                        }
                        if ((frame.matchedPairs || []).some(function(p) { return p.patternIndex === j; })) {
                            cell.match = true;
                        }
                        if (frame.mismatchedPair && frame.mismatchedPair.patternIndex === j) {
                            cell.mismatch = true;
                        }
                        cells.push(cell);
                    }
                    while (cells.length < textLength) {
                        cells.push({ char: '', empty: true });
                    }
                    return cells;
                }

                function renderBadCharTable(table) {
                    const keys = Object.keys(table);
                    if (!keys.length) {
                        return '<p>Table vide.</p>';
                    }
                    const head = keys.map(function(k) {
                        return '<th>' + escapeHtml(k) + '</th>';
                    }).join('');
                    const vals = keys.map(function(k) {
                        return '<td>' + escapeHtml(table[k]) + '</td>';
                    }).join('');
                    return '<table class="ouinpo-rt__bc-table"><thead><tr>' + head + '</tr></thead><tbody><tr>' + vals + '</tr></tbody></table>';
                }

                function renderPanel(vizEl, statsEl, explainEl, frame, text, pattern, label) {
                    const textCells = makeCellsText(text, frame);
                    const patternCells = makeCellsPattern(text.length, pattern, frame);
                    vizEl.innerHTML =
                        buildRow('Texte', textCells) +
                        buildRow('Motif', patternCells);

                        statsEl.innerHTML =
                            '<div class="ouinpo-rt__meta">' +
                                '<div class="ouinpo-rt__badge"><strong>Comparaisons</strong><br>' + frame.comparisons + '</div>' +
                                '<div class="ouinpo-rt__badge"><strong>Alignements</strong><br>' + frame.alignments + '</div>' +
                                '<div class="ouinpo-rt__badge"><strong>Nombre d’occurrences</strong><br>' + frame.occurrences.length + '</div>' +
                                '<div class="ouinpo-rt__badge"><strong>Positions trouvées</strong><br>' + (frame.occurrences.length ? frame.occurrences.join(', ') : 'aucune') + '</div>' +
                            '</div>';

                    explainEl.innerHTML = '<strong>' + escapeHtml(label) + '</strong> — ' + escapeHtml(frame.explanation || '');
                }

                function initWidget(root) {
                    const textInput = root.querySelector('.js-rt-text');
                    const patternInput = root.querySelector('.js-rt-pattern');
                    const speedInput = root.querySelector('.js-rt-speed');
                    const buildBtn = root.querySelector('.js-rt-build');
                    const prevBtn = root.querySelector('.js-rt-prev');
                    const nextBtn = root.querySelector('.js-rt-next');
                    const playBtn = root.querySelector('.js-rt-play');
                    const resetBtn = root.querySelector('.js-rt-reset');

                    const globalStatus = root.querySelector('.js-rt-global-status');
                    const naiveViz = root.querySelector('.js-rt-naive-viz');
                    const naiveStats = root.querySelector('.js-rt-naive-stats');
                    const naiveExplain = root.querySelector('.js-rt-naive-explain');
                    const bmViz = root.querySelector('.js-rt-bm-viz');
                    const bmStats = root.querySelector('.js-rt-bm-stats');
                    const bmExplain = root.querySelector('.js-rt-bm-explain');
                    const bmTable = root.querySelector('.js-rt-bm-table');

                    let state = null;
                    let timer = null;
                    let currentStep = 0;

                    function stopAuto() {
                        if (timer) {
                            clearInterval(timer);
                            timer = null;
                            playBtn.textContent = 'Lecture automatique';
                        }
                    }

                    function getFrame(frames, step) {
                        return frames[Math.min(step, frames.length - 1)];
                    }

                    function render() {
                        if (!state) return;
                        const naiveFrame = getFrame(state.naiveFrames, currentStep);
                        const bmFrame = getFrame(state.bmFrames, currentStep);
                        const maxSteps = Math.max(state.naiveFrames.length, state.bmFrames.length);

                        globalStatus.innerHTML =
                            '<strong>Étape globale</strong> ' + (currentStep + 1) + ' / ' + maxSteps +
                            ' — texte de longueur ' + state.text.length + ', motif de longueur ' + state.pattern.length + '.';

                        renderPanel(naiveViz, naiveStats, naiveExplain, naiveFrame, state.text, state.pattern, 'Naïf');
                        renderPanel(bmViz, bmStats, bmExplain, bmFrame, state.text, state.pattern, 'Boyer-Moore');
                        bmTable.innerHTML = renderBadCharTable(state.badCharTable);
                    }

                    function buildSimulation() {
                        stopAuto();
                        const text = sanitizeInput(textInput.value);
                        const pattern = sanitizeInput(patternInput.value);

                        const naiveFrames = simulateNaive(text, pattern);
                        const bm = simulateBoyerMooreBadCharacter(text, pattern);

                        state = {
                            text,
                            pattern,
                            naiveFrames,
                            bmFrames: bm.frames,
                            badCharTable: bm.badChar
                        };
                        currentStep = 0;
                        render();
                    }

                    buildBtn.addEventListener('click', buildSimulation);
                    prevBtn.addEventListener('click', function() {
                        stopAuto();
                        currentStep = Math.max(0, currentStep - 1);
                        render();
                    });
                    nextBtn.addEventListener('click', function() {
                        stopAuto();
                        const maxIndex = Math.max(state.naiveFrames.length, state.bmFrames.length) - 1;
                        currentStep = Math.min(maxIndex, currentStep + 1);
                        render();
                    });
                    resetBtn.addEventListener('click', function() {
                        stopAuto();
                        textInput.value = root.getAttribute('data-initial-text') || '';
                        patternInput.value = root.getAttribute('data-initial-pattern') || '';
                        buildSimulation();
                    });
                    playBtn.addEventListener('click', function() {
                        if (!state) buildSimulation();
                        if (timer) {
                            stopAuto();
                            return;
                        }
                        const delay = parseInt(speedInput.value, 10) || 700;
                        playBtn.textContent = 'Pause';
                        timer = setInterval(function() {
                            const maxIndex = Math.max(state.naiveFrames.length, state.bmFrames.length) - 1;
                            if (currentStep >= maxIndex) {
                                stopAuto();
                                return;
                            }
                            currentStep += 1;
                            render();
                        }, delay);
                    });

                    buildSimulation();
                }

                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.ouinpo-rt').forEach(initWidget);
                });
            })();
        </script>
        <?php
    }

    return ob_get_clean();
});
