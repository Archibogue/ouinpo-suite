# Guide des shortcodes — OuInPo Suite

Ce guide recense les shortcodes fournis par OuInPo Suite et propose une structure de pages minimale pour une installation WordPress neuve.

## 1. Principe général

OuInPo Suite repose sur des pages WordPress contenant des shortcodes.

Deux types de pages sont particulièrement importantes :

- les pages de liste, par exemple la liste des exercices ;
- les pages de détail, par exemple l’affichage d’un exercice précis.

La liste envoie vers la page de détail en ajoutant un paramètre dans l’URL.

Exemple avec permaliens classiques :

```text
/exercice/?exo=12
```

Exemple avec permaliens simples WordPress :

```text
/?page_id=34&exo=12
```

Sur une installation WordPress vierge, il est fréquent que les permaliens soient en mode simple. Il faut donc veiller à préserver `page_id` dans les liens générés.

## 2. Pages recommandées

### Page “Exercices”

Titre conseillé :

```text
Exercices
```

Slug conseillé :

```text
exercices
```

Contenu :

```text
[ouinpo_exercises]
```

Variante explicite avec page de détail :

```text
[ouinpo_exercises page="/exercice/"]
```

Si WordPress utilise les permaliens simples, on peut utiliser l’URL de la page de détail :

```text
[ouinpo_exercises page="/?page_id=34"]
```

où `34` est l’identifiant de la page “Exercice”.

### Page “Exercice”

Titre conseillé :

```text
Exercice
```

Slug conseillé :

```text
exercice
```

Contenu :

```text
[ouinpo_exercise]
```

Cette page lit automatiquement l’identifiant passé dans l’URL :

```text
?exo=12
```

### Page “Sujets pratiques”

Titre conseillé :

```text
Sujets pratiques
```

Slug conseillé :

```text
sujets-pratiques
```

Contenu :

```text
[ouinpo_practical_subjects]
```

Variante explicite avec page de détail :

```text
[ouinpo_practical_subjects page="/epreuve-pratique-sujet/"]
```

Avec permaliens simples :

```text
[ouinpo_practical_subjects page="/?page_id=35"]
```

### Page “Sujet pratique”

Titre conseillé :

```text
Sujet pratique
```

Slug conseillé :

```text
epreuve-pratique-sujet
```

Contenu :

```text
[ouinpo_practical_subject]
```

Cette page lit automatiquement l’identifiant passé dans l’URL :

```text
?practical=12
```

ou :

```text
?subject=12
```

### Page “Flashcards”

Titre conseillé :

```text
Flashcards
```

Slug conseillé :

```text
flashcards
```

Contenu :

```text
[ouinpo_flashcards]
```

### Page “Mes compétences”

Titre conseillé :

```text
Mes compétences
```

Slug conseillé :

```text
mes-competences
```

Contenu :

```text
[ouinpo_competences_progress]
```

### Page “Mes badges”

Titre conseillé :

```text
Mes badges
```

Slug conseillé :

```text
mes-badges
```

Contenu :

```text
[ouinpo_student_badges]
```

### Page “Palmarès des badges”

Titre conseillé :

```text
Palmarès des badges
```

Slug conseillé :

```text
palmares-badges
```

Contenu :

```text
[ouinpo_badges_palmares]
```

### Page “Carte du site”

Titre conseillé :

```text
Carte du site
```

Slug conseillé :

```text
carte-du-site
```

Contenu :

```text
[ouinpo_site_map]
```

### Page “SegFault”

Titre conseillé :

```text
SegFault
```

Slug conseillé :

```text
segfault
```

Contenu :

```text
[segfault_chat]
```

### Page “Mon parcours”

Titre conseillé :

```text
Mon parcours
```

Slug conseillé :

```text
mon-parcours
```

Contenu :

```text
[segfault_parcours]
```

### Page “Mes parcours”

Titre conseillé :

```text
Mes parcours
```

Slug conseillé :

```text
mes-parcours
```

Contenu :

```text
[segfault_mes_parcours]
```

### Page “Dépôt élève”

Titre conseillé :

```text
Dépôt élève
```

Slug conseillé :

```text
depot-eleve
```

Contenu :

```text
[ouinpo_upload]
```

On peut ajouter la liste des dépôts de l’élève sur la même page ou sur une autre page :

```text
[ouinpo_my_submissions]
```

### Page “Ressources”

Titre conseillé :

```text
Ressources
```

Slug conseillé :

```text
ressources
```

Contenu :

```text
[ouinpo_resources]
```

## 3. Shortcodes du module Exercices

### `[ouinpo_exercises]`

Affiche la liste des exercices.

Aliases :

```text
[ouinpo_exercises]
[ouinpo-exercises]
```

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `page` | URL ou chemin | Page de détail des exercices |
| `lvl` | `seconde`, `premiere`, `terminale` | Niveau affiché par défaut |
| `exam_only` | `0` ou `1` | Limiter aux exercices de type bac |

Exemples :

```text
[ouinpo_exercises]
```

```text
[ouinpo_exercises page="/exercice/"]
```

```text
[ouinpo_exercises page="/?page_id=34"]
```

```text
[ouinpo_exercises lvl="premiere"]
```

```text
[ouinpo_exercises lvl="terminale" exam_only="1"]
```

### `[ouinpo_exercise]`

Affiche un exercice précis.

Aliases :

```text
[ouinpo_exercise]
[ouinpo-exercise]
```

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `id` | identifiant numérique | Afficher un exercice précis |
| `slug` | slug d’exercice | Afficher un exercice précis par slug |
| `auto` | `1` ou `0` | Lire automatiquement `?exo=` dans l’URL |

Exemples :

```text
[ouinpo_exercise]
```

```text
[ouinpo_exercise id="12"]
```

```text
[ouinpo_exercise slug="demo-plus-grand-grimoire-tableau"]
```

## 4. Shortcodes des sujets pratiques

### `[ouinpo_practical_subjects]`

Affiche la liste des sujets pratiques.

Aliases :

```text
[ouinpo_practical_subjects]
[ouinpo-practical-subjects]
```

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `page` | URL ou chemin | Page de détail du sujet pratique |
| `lvl` | `seconde`, `premiere`, `terminale` | Niveau affiché |
| `source_type` | `annale`, `inspired`, `type_bac`, `classic` | Origine du sujet |
| `theme_bac` | thème bac | Filtrage par thème |

Exemples :

```text
[ouinpo_practical_subjects]
```

```text
[ouinpo_practical_subjects page="/epreuve-pratique-sujet/"]
```

```text
[ouinpo_practical_subjects page="/?page_id=35"]
```

```text
[ouinpo_practical_subjects lvl="terminale" source_type="type_bac"]
```

### `[ouinpo_practical_subject]`

Affiche un sujet pratique précis.

Aliases :

```text
[ouinpo_practical_subject]
[ouinpo-practical-subject]
```

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `id` | identifiant numérique | Afficher un sujet précis |
| `slug` | slug d’exercice | Afficher un sujet par slug |
| `auto` | `1` ou `0` | Lire automatiquement `?practical=` ou `?subject=` |

Exemples :

```text
[ouinpo_practical_subject]
```

```text
[ouinpo_practical_subject id="12"]
```

```text
[ouinpo_practical_subject slug="demo-sujet-pratique-somme-pairs"]
```

## 5. Shortcodes du module Flashcards

### `[ouinpo_flashcards]`

Affiche l’application de révision par cartes.

Aliases :

```text
[ouinpo_flashcards]
[ouinpo-flashcards]
```

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `deck` | slug de paquet | Limiter à un paquet |
| `track` | `SNT` ou `NSI` | Limiter à une voie |
| `level` | `Seconde`, `Première`, `Terminale`, `Transversal` | Limiter à un niveau |
| `domain` | slug de domaine | Limiter à un domaine |
| `title` | texte | Titre affiché |

Exemples :

```text
[ouinpo_flashcards]
```

```text
[ouinpo_flashcards deck="demo-nsi-premiere-algorithmique"]
```

```text
[ouinpo_flashcards track="NSI" level="Première" title="Réviser l’algorithmique"]
```

## 6. Shortcodes de progression et badges

### `[ouinpo_competences_progress]`

Affiche la progression de l’élève connecté.

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `year` | `active` ou identifiant | Année scolaire |
| `group` | `auto` ou identifiant | Groupe concerné |
| `detail` | `1` ou `0` | Afficher le détail |

Exemples :

```text
[ouinpo_competences_progress]
```

```text
[ouinpo_competences_progress detail="0"]
```

### `[ouinpo_competences_prof]`

Affiche une interface front de suivi des compétences pour les profils autorisés.

Exemple :

```text
[ouinpo_competences_prof]
```

### `[ouinpo_student_badges]`

Affiche les badges de l’élève connecté.

Exemple :

```text
[ouinpo_student_badges]
```

### `[ouinpo_badges_palmares]`

Affiche le palmarès des badges.

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `year` | `active` ou slug/identifiant selon configuration | Année scolaire |

Exemples :

```text
[ouinpo_badges_palmares]
```

```text
[ouinpo_badges_palmares year="active"]
```

## 7. Shortcodes de révision intégrée

### `[ouinpo_revision_band]`

Affiche un bandeau de révision lié au contenu courant.

Aliases :

```text
[ouinpo_revision_band]
[ouinpo-revision-band]
```

Exemple :

```text
[ouinpo_revision_band]
```

## 8. Carte du site

### `[ouinpo_site_map]`

Affiche une carte dynamique du site.

Aliases :

```text
[ouinpo_site_map]
[ouinpo-site-map]
```

Attributs principaux :

| Attribut | Valeur par défaut | Rôle |
|---|---|---|
| `main_menu` | `principal` | Menu principal |
| `seconde_menu` | `seconde` | Menu Seconde |
| `premiere_menu` | `premiere` | Menu Première |
| `terminale_menu` | `terminale` | Menu Terminale |
| `histoire_menu` | `histoire` | Menu Histoire |
| `outils_menu` | `outils` | Menu Outils |
| `layout` | `list` | `list` ou `table` |
| `show_intro` | `1` | Afficher l’introduction |
| `show_title` | `1` | Afficher le titre |
| `cards` | `0` | Présentation en cartes |
| `only` | vide | Limiter à certains slugs |
| `exclude_slugs` | liste interne | Slugs exclus |
| `add_exclude_slugs` | vide | Ajouter des slugs exclus |
| `add_exclude_titles` | vide | Ajouter des titres exclus |

Exemples :

```text
[ouinpo_site_map]
```

```text
[ouinpo_site_map layout="table"]
```

```text
[ouinpo_site_map cards="1" show_intro="0"]
```

## 9. Shortcodes SegFault

### `[segfault_chat]`

Affiche le chat SegFault.

Exemple :

```text
[segfault_chat]
```

### `[segfault_parcours]`

Affiche un parcours conseillé pour l’élève connecté.

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `limit` | nombre de 1 à 12 | Nombre maximal d’éléments proposés |

Exemples :

```text
[segfault_parcours]
```

```text
[segfault_parcours limit="8"]
```

### `[segfault_mes_parcours]`

Affiche les parcours assignés à l’élève connecté.

Exemple :

```text
[segfault_mes_parcours]
```

## 10. Shortcodes Dépôts et ressources

### `[ouinpo_upload]`

Affiche le formulaire de dépôt élève.

Exemple :

```text
[ouinpo_upload]
```

### `[ouinpo_my_submissions]`

Affiche les dépôts de l’utilisateur connecté.

Exemple :

```text
[ouinpo_my_submissions]
```

### `[ouinpo_resources]`

Affiche les ressources publiées par le professeur.

Exemple :

```text
[ouinpo_resources]
```

### `[ouinpo_class_field]`

Affiche un champ de sélection de classe.

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `multiple` | `yes` ou `no` | Autoriser plusieurs classes |
| `label` | texte | Libellé du champ |
| `required` | `yes` ou `no` | Champ obligatoire |

Exemple :

```text
[ouinpo_class_field multiple="no" label="Classe" required="yes"]
```

## 11. Shortcodes Gate

### `[ouinpo_gate]`

Affiche un parcours Gate.

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `page` | slug | Page ou progression suivie |
| `needed` | nombre | Nombre d’éléments nécessaires |
| `reveal` | `embed` ou autre valeur prévue par le module | Mode de révélation |

Exemple :

```text
[ouinpo_gate page="sample-page" needed="42" reveal="embed"]
```

### `[ouinpo_signpad]`

Affiche le module de signature associé à Gate.

Attributs :

| Attribut | Valeur | Rôle |
|---|---|---|
| `page` | slug | Page liée |
| `needed` | nombre | Seuil attendu |
| `show_list` | `1` ou `0` | Afficher la liste |

Exemple :

```text
[ouinpo_signpad page="sample-page" needed="42" show_list="1"]
```

### `[ouinpo_hint]`

Affiche un indice ou un contenu conditionnel selon la logique Gate.

Exemple :

```text
[ouinpo_hint]Texte de l’indice[/ouinpo_hint]
```

## 12. Recherche textuelle

### `[ouinpo_recherche_textuelle]`

Affiche une simulation interactive de recherche textuelle.

Attributs :

| Attribut | Valeur par défaut | Rôle |
|---|---|---|
| `texte` | `ABAAABCD` | Texte dans lequel chercher |
| `motif` | `ABC` | Motif recherché |
| `titre` | `Simulation interactive — recherche textuelle` | Titre affiché |

Exemples :

```text
[ouinpo_recherche_textuelle]
```

```text
[ouinpo_recherche_textuelle texte="ABAAABCD" motif="ABC" titre="Recherche naïve"]
```

## 13. Sélecteur de titres

### `[ouinpo_title_selector]`

Shortcode interne lié au module de titres / badges.

Exemple :

```text
[ouinpo_title_selector]
```

## 14. Recommandations pour une installation neuve

Après installation du plugin :

1. créer les pages WordPress contenant les shortcodes ;
2. vérifier les slugs ou noter les `page_id` si les permaliens sont simples ;
3. aller dans `Réglages → Permaliens` et enregistrer une fois ;
4. importer le pack de démonstration ;
5. tester la liste des exercices ;
6. tester le clic vers un exercice ;
7. tester la liste des sujets pratiques ;
8. tester le clic vers un sujet pratique ;
9. tester les flashcards.

## 15. Checklist minimale des pages

| Page | Shortcode |
|---|---|
| Exercices | `[ouinpo_exercises]` |
| Exercice | `[ouinpo_exercise]` |
| Sujets pratiques | `[ouinpo_practical_subjects]` |
| Sujet pratique | `[ouinpo_practical_subject]` |
| Flashcards | `[ouinpo_flashcards]` |
| Mes compétences | `[ouinpo_competences_progress]` |
| Mes badges | `[ouinpo_student_badges]` |
| Palmarès des badges | `[ouinpo_badges_palmares]` |
| SegFault | `[segfault_chat]` |
| Mon parcours | `[segfault_parcours]` |
| Dépôt élève | `[ouinpo_upload]` |
| Mes dépôts | `[ouinpo_my_submissions]` |
| Ressources | `[ouinpo_resources]` |
| Carte du site | `[ouinpo_site_map]` |

## 16. Note sur les permaliens simples

Si les permaliens WordPress sont en mode simple, les pages utilisent `page_id`.

Dans ce cas, les shortcodes de liste peuvent recevoir explicitement l’URL de la page de détail :

```text
[ouinpo_exercises page="/?page_id=34"]
```

```text
[ouinpo_practical_subjects page="/?page_id=35"]
```

Le JavaScript doit ajouter les paramètres sans écraser la query string existante :

```text
?page_id=34&exo=12
```

et non :

```text
?exo=12
```

## 17. Shortcodes publics et shortcodes réservés

Certains shortcodes peuvent être affichés publiquement, par exemple :

- `[ouinpo_exercises]`
- `[ouinpo_exercise]`
- `[ouinpo_practical_subjects]`
- `[ouinpo_practical_subject]`
- `[ouinpo_site_map]`
- `[ouinpo_recherche_textuelle]`

D’autres nécessitent en pratique un utilisateur connecté ou un rôle particulier :

- `[ouinpo_competences_progress]`
- `[ouinpo_competences_prof]`
- `[ouinpo_student_badges]`
- `[segfault_parcours]`
- `[segfault_mes_parcours]`
- `[ouinpo_upload]`
- `[ouinpo_my_submissions]`

La logique exacte dépend des réglages du site, des rôles WordPress et des modules activés.
