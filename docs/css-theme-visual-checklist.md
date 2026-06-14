# Checklist visuelle CSS themes

Objectif : verifier manuellement que les themes `neutral`, `ouinpo` et `bsio` chargent les bons handles apres le decoupage CSS, sans modifier le rendu attendu.

| Page | Shortcode concerne | Handle public attendu | Handle theme attendu | Mode neutral | Mode ouinpo | Mode bsio | Observations |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Banque d'exercices | `[ouinpo_exercises]` | `ouinpo-exo-css` | `ouinpo-theme-exercises-css` | A verifier | A verifier | A verifier |  |
| Detail exercice | `[ouinpo_exercise]` / lien detail exercice | `ouinpo-exo-css` | `ouinpo-theme-exercises-css` | A verifier | A verifier | A verifier |  |
| Sujet pratique | `[ouinpo_practical_subject]` / lien sujet pratique | `ouinpo-practical-css` | `ouinpo-theme-practical-css` | A verifier | A verifier | A verifier |  |
| Liste des sujets ecrits | `[ouinpo_written_subjects]` | `ouinpo-written-css` | `ouinpo-theme-written-css` | A verifier | A verifier | A verifier |  |
| Detail sujet ecrit | `[ouinpo_written_subject]` / lien sujet ecrit | `ouinpo-written-css` | `ouinpo-theme-written-css` | A verifier | A verifier | A verifier |  |
| Sujet ecrit avec PDF | `[ouinpo_written_subject]` avec fichier PDF | `ouinpo-written-css` | `ouinpo-theme-written-css` | A verifier | A verifier | A verifier |  |
| Sujet ecrit avec zone de reponse | `[ouinpo_written_subject]` avec reponse active | `ouinpo-written-css` | `ouinpo-theme-written-css` | A verifier | A verifier | A verifier |  |
| Sujet ecrit avec aide IA | `[ouinpo_written_subject]` avec aide IA active | `ouinpo-written-css` | `ouinpo-theme-written-css` | A verifier | A verifier | A verifier |  |
| Suivi professeur / competences | Shortcodes de suivi competences | `ouinpo-teacher-css` | `ouinpo-theme-teacher-css` | A verifier | A verifier | A verifier |  |
| Flashcards | `[ouinpo_flashcards]` | `ouinpo-flashcards` | `ouinpo-theme-flashcards-css` | A verifier | A verifier | A verifier |  |
| SegFault | Shortcodes / widget SegFault | `ouinpo-sf` | `ouinpo-theme-segfault-css` | A verifier | A verifier | A verifier |  |
| Projects | `[ouinpo_my_projects]` et vues projet | `ouinpo-projects` | `ouinpo-theme-projects-css` | A verifier | A verifier | A verifier |  |
| Depots / ressources | Shortcodes Submissions | `ouinpo-submissions` | `ouinpo-theme-submissions-css` | A verifier | A verifier | A verifier |  |
| Titres / badges | `[ouinpo_title_selector]` et pages badges | `ouinpo-titles-css` | `ouinpo-theme-titles-css` | A verifier | A verifier | A verifier |  |

Rappel : `ouinpo-theme-css` est le handle global. En mode `neutral`, il pointe vers `assets/css/themes/neutral.css`. En modes `ouinpo` et `bsio`, il orchestre les couches decoupees du theme actif ; les handles de modules sont charges seulement par les shortcodes ou modules concernes.

