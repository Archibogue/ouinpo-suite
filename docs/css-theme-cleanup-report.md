# Rapport de nettoyage CSS thematique

Date : 2026-06-14

## Perimetre

Analyse limitee aux fichiers de theme decoupes :

- `assets/css/themes/ouinpo/foundation.css`
- `assets/css/themes/ouinpo/content.css`
- `assets/css/themes/ouinpo/components.css`
- `assets/css/themes/ouinpo/legacy-overrides.css`
- `assets/css/themes/ouinpo/modules/*.css`
- memes fichiers pour `bsio`

`neutral.css` et `dist/` sont exclus de ce lot.

## Doublons exacts entre fichiers CSS

Un seul doublon strict entre fichiers a ete detecte :

- `assets/css/themes/ouinpo/modules/written.css`
- `assets/css/themes/bsio/modules/written.css`

Regle identique :

```css
.ouinpo-written-page .ouinpo-panel-title,
.ouinpo-written-subject .ouinpo-panel-title,
.ouinpo-written-exercise-front > h3,
.ouinpo-written-files h3,
.ouinpo-written-subject .ouinpo-practical-files h3,
.ouinpo-written-report-panel > h3,
.ouinpo-written-question-title {
  color: var(--written-primary-dark);
}
```

Decision : conserve. Les deux fichiers appartiennent a des themes mutuellement exclusifs, donc ce doublon n'est pas une surcharge inutile au runtime.

Doublons stricts internes encore presents dans `assets/css/themes/ouinpo/legacy-overrides.css`, laisses volontairement :

- `.ouinpo-practical-statement h3::before`
- `.ouinpo-practical-files h3::before`
- `.ouinpo-practical-calls h3::before`
- `.ouinpo-practical-progress h3::before`

Decision : conserves. Ces regles portent des pseudo-elements visibles et apparaissent dans des surcouches pratiques successives ; leur retrait n'est pas considere comme absolument certain sans controle visuel.

## Selecteurs presents dans plusieurs couches

Analyse des couches `content.css`, `components.css`, `modules/*.css` et `legacy-overrides.css` :

- recouvrements detectes : 0

Les fichiers `content.css`, `components.css`, `modules/exercises.css` et `modules/practical.css` sont encore des couches reservees. Les vraies regles historiques restent dans `legacy-overrides.css`, sauf le module `written.css` deja separe.

## Regles de legacy-overrides.css candidates a un fichier plus specifique

Ces familles de regles semblent pouvoir rejoindre une couche plus precise a terme, mais pas dans cette passe, car `legacy-overrides.css` est charge comme surcharge finale :

- contenu / composants : `.ouinpo-content`, `.ouinpo-box`, `.ouinpo-eldritch`, `.ouinpo-endquote`, `.ouinpo-site-map`, tableaux pedagogiques.
- exercices : `.ouinpo-exo`, `#ouinpo-exo-list`, `.ouinpo-exercise-*`, `.ouinpo-exo-filters`, `.ouinpo-revision-band`, `.ouinpo-ia-notice`.
- competences / suivi : `.ouinpo-competences`, `.ouinpo-me-*`, `.ouinpo-ds-*`, `.ouinpo-badges-*`, `.ouinpo-palmares-*`.
- sujets pratiques : `.ouinpo-practical-*`.
- flashcards : `.ouinpo-fc-*`.
- SegFault : `.sf-*`, `.segfault-*`.
- recherche textuelle : classes du module RechText.
- projets, submissions et titres : familles module specifiques deja isolees par handles, mais encore conservees en surcharge finale quand elles proviennent du CSS prod historique.

Decision : aucun deplacement effectue. Deplacer ces blocs vers `modules/*.css` modifierait l'ordre relatif, puisque les modules de theme sont charges avant `legacy-overrides.css`.

## Regles a conserver en surcharge finale

Doivent rester dans `legacy-overrides.css` tant qu'une verification visuelle n'a pas prouve une equivalence stricte :

- correctifs marques comme devant rester apres les blocs complets de production ;
- surcharges avec `!important` destinees a neutraliser le theme WordPress ou des styles de formulaires ;
- correctifs de visibilite et de superposition SegFault ;
- correctifs de tableaux, palmares, badges et vues eleves qui compensent des styles plus anciens ;
- blocs de compatibilite issus de la production quand ils redefinissent des variables, couleurs, bordures ou ombres apres le CSS module.

## Conflits de cascade a surveiller

- `legacy-overrides.css` est la couche finale : tout deplacement vers `foundation.css`, `content.css`, `components.css` ou `modules/*.css` peut changer le rendu.
- Les modules `written`, `practical` et `exercises` doivent rester separes. `assets/css/front/exercises.css` ne doit pas recuperer de regles `.ouinpo-written-*`.
- Les deux themes `ouinpo` et `bsio` peuvent contenir des regles identiques dans leurs modules respectifs sans que ce soit un doublon runtime.
- Les chaines `content:` doivent rester en UTF-8 declare ou en echappements CSS Unicode pour eviter le retour des caracteres mojibake.

## Nettoyages effectues

- Doublons stricts supprimes : voir la section "Nettoyage doublons stricts".
- Regles deplacees depuis `legacy-overrides.css` : aucune.
- Corrections sures : les valeurs `content:` mojibake de `assets/css/themes/ouinpo/legacy-overrides.css` ont ete remplacees par des echappements CSS Unicode equivalents.

## Nettoyage doublons stricts

Suppressions effectuees dans `assets/css/themes/ouinpo/legacy-overrides.css` uniquement. Aucune regle n'a ete deplacee, et l'occurrence conservee reste dans le meme fichier.

| Fichier concerne | Selecteur | Justification | Regle conservee |
| --- | --- | --- | --- |
| `assets/css/themes/ouinpo/legacy-overrides.css` | `.ouinpo-ia-notice` | Occurrence ancienne strictement identique a une occurrence plus tardive ; meme selecteur, memes proprietes, memes valeurs. | `assets/css/themes/ouinpo/legacy-overrides.css` |
| `assets/css/themes/ouinpo/legacy-overrides.css` | `.ouinpo-ia-notice p` | Deux occurrences anciennes strictement identiques supprimees ; une occurrence identique reste plus bas. | `assets/css/themes/ouinpo/legacy-overrides.css` |
| `assets/css/themes/ouinpo/legacy-overrides.css` | `.ouinpo-ia-notice a` | Occurrence ancienne strictement identique a une occurrence plus tardive. | `assets/css/themes/ouinpo/legacy-overrides.css` |
| `assets/css/themes/ouinpo/legacy-overrides.css` | `.ouinpo-practical-files-list a:hover` | Occurrence ancienne strictement identique a une occurrence plus tardive. | `assets/css/themes/ouinpo/legacy-overrides.css` |
| `assets/css/themes/ouinpo/legacy-overrides.css` | `.ouinpo-practical-call-status` | Occurrence ancienne strictement identique a une occurrence plus tardive. | `assets/css/themes/ouinpo/legacy-overrides.css` |
| `assets/css/themes/ouinpo/legacy-overrides.css` | `@media (max-width: 800px) .ouinpo-practical-call-head, .ouinpo-practical-progress-item` | Occurrence ancienne dans le meme contexte media, strictement identique a une occurrence plus tardive. | `assets/css/themes/ouinpo/legacy-overrides.css` |
| `assets/css/themes/ouinpo/legacy-overrides.css` | `.ouinpo-practical-statement-content li` | Occurrence ancienne strictement identique a une occurrence plus tardive. | `assets/css/themes/ouinpo/legacy-overrides.css` |
| `assets/css/themes/ouinpo/legacy-overrides.css` | `@media (max-width: 640px) .ouinpo-fc-toolbar select` | Occurrence ancienne dans le meme contexte media, strictement identique a une occurrence plus tardive. | `assets/css/themes/ouinpo/legacy-overrides.css` |

Doublons volontairement laisses :

- doublon inter-theme `assets/css/themes/ouinpo/modules/written.css` / `assets/css/themes/bsio/modules/written.css`, car les themes sont mutuellement exclusifs ;
- doublons de pseudo-elements pratiques listes plus haut, car ils portent des marqueurs visuels ;
- regles proches mais non identiques des surcouches finales, notamment les variantes `.ouinpo-ia-notice` finales.
