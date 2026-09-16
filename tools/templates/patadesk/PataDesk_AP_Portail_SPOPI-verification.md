# Vérification du scénario SPOPI

## Correction

Le fichier `PataDesk_AP_Portail_SPOPI-corrige.json` conserve les paramètres du
scénario fourni. La seule modification de données est l'ajout de `resolving`
aux transitions de `waiting_user` du ticket **P03 — Mot de passe oublié après un
congé**. L'action `verify` (« Faire confirmer la reconnexion ») prévoit ce retour.
Les nouvelles options pédagogiques du module ne sont pas activées dans cette copie.

## Parcours contrôlés

| Ticket | Cas | Résultat |
| --- | --- | --- |
| T01 | Lien Centre de services | Résolution et clôture accessibles |
| T02 | Mise en évidence du rappel | Résolution et clôture accessibles |
| T03 | Bouton des horaires | Résolution et clôture accessibles |
| T04 | Conseil remplaçant les horaires | Résolution et clôture accessibles |
| T05 | Fausse annonce de création de demande | Résolution et clôture accessibles |
| E06 | Réinitialiser le conseil | Deux corrections et deux tests validés, clôture accessible |
| E07 | Petits écrans | Résolution et clôture accessibles |
| P01 | Imprimante | Remplissage, page de test, résolution et clôture accessibles |
| P02 | Wi-Fi | Escalade, réponse spécialiste, vérification et clôture accessibles |
| P03 | Mot de passe | Identité, procédure, réinitialisation, confirmation et clôture accessibles après correction |

Exécution : `php tools/check-patadesk-spopi.php` — **112 assertions réussies**.
Les 111 actions déclarées ont été exécutées au moins une fois, sur le parcours
principal ou une branche indépendante. Chaque ticket a aussi été testé avec une
résolution prématurée provoquant une réouverture. Les huit extraits modifiables
ont été contrôlés avec leur code initial, leur correction et une modification
invalidant le test précédemment réussi.

Cette vérification porte sur le moteur de simulation et la validité JSON. Elle
n'exécute pas les programmes des élèves, ne reproduit pas toutes les permutations
possibles d'actions et ne remplace pas la recette du site WordPress.

## Points pédagogiques conservés

- Les actions nommées « Ajouter une note technique » sont de type `communication`
  dans le JSON : leur texte apparaît comme message au demandeur. Pour une note
  interne, utiliser l'onglet **Notes** de PataDesk.
- P01 et P02 exigent que l'action `verify` soit effectuée, mais ne déclarent pas
  `resolution_tests`. Dans ce scénario, leurs prérequis conduisent à une variante
  de succès. Si leurs résultats deviennent conditionnels, ajouter
  `"resolution_tests": ["verify"]` pour exiger explicitement un succès.
- Les anciennes catégories « Demande de service » sont conservées. Leur séparation
  entre nature et catégorie technique relève d'une migration pédagogique distincte.

## Utilisation

Importer la copie corrigée comme nouveau scénario, publier et affecter. Les
tentatives déjà commencées gardent leur copie d'origine. « Archiver et recommencer »
réutilise cette ancienne copie et ne corrige donc pas P03 dans ces tentatives.

Le JSON contient les corrections destinées au professeur : le conserver dans
l'espace enseignant, pas comme ressource publique pour les élèves.
