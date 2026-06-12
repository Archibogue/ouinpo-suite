# Smoke test manuel 0.7.1

Checklist a executer apres les optimisations et refactorings Projects de la 0.7.x, avant partage plus large.

Conventions:

| Etat | Sens |
| --- | --- |
| OK | Fonctionnement attendu |
| KO | Blocage ou regression |
| Remarque | Ecart non bloquant, a noter |

## Commandes prealables

| Controle | OK | KO | Remarque |
| --- | --- | --- | --- |
| `git status --short --branch` indique une branche propre |  |  |  |
| `git diff --check` ne signale pas d'erreur |  |  |  |
| `php tools\verify-optimizations.php` passe |  |  |  |
| `php tools\verify-packs.php` passe |  |  |  |
| Lint PHP global hors `dist/` passe |  |  |  |
| Aucun fichier `dist/`, CSS ou JS non prevu n'est modifie |  |  |  |

Commande PowerShell possible pour le lint global:

```powershell
$files = Get-ChildItem -Path . -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch '\\dist\\' }
foreach ($file in $files) { php -l $file.FullName }
```

## Tests a faire sur site local

| Controle | OK | KO | Remarque |
| --- | --- | --- | --- |
| Installer ou mettre a jour le plugin sans erreur PHP visible |  |  |  |
| Activer le plugin depuis l'administration WordPress |  |  |  |
| Desactiver puis reactiver le plugin sans perte evidente d'acces admin |  |  |  |
| Ouvrir l'ecran admin OuInPo Suite |  |  |  |
| Ouvrir le diagnostic OuInPo Suite |  |  |  |
| Activer/desactiver les modules optionnels depuis les reglages |  |  |  |
| Verifier que seuls les modules autorises sont conserves |  |  |  |
| Verifier que le module obligatoire reste coherent apres sauvegarde |  |  |  |

## Modules generaux

| Module | Controle | OK | KO | Remarque |
| --- | --- | --- | --- | --- |
| Exercices | Ouvrir l'administration exercices sans erreur |  |  |  |
| Exercices | Creer ou ouvrir un exercice existant |  |  |  |
| Exercices | Verifier un shortcode public d'exercice |  |  |  |
| Exercices | Tester un flux IA si configure |  |  |  |
| Flashcards | Ouvrir l'administration flashcards si le module est actif |  |  |  |
| Flashcards | Verifier l'affichage front d'un jeu de cartes |  |  |  |
| Submissions | Ouvrir l'administration Submissions si le module est actif |  |  |  |
| Submissions | Tester un depot autorise et un fichier refuse |  |  |  |
| SegFault | Ouvrir les reglages SegFault si le module est actif |  |  |  |
| SegFault | Verifier qu'un appel IA/RAG echoue proprement si non configure |  |  |  |

## Projects

| Controle | OK | KO | Remarque |
| --- | --- | --- | --- |
| Activer le module Projects |  |  |  |
| Ouvrir l'administration Projects |  |  |  |
| Creer un projet avec un compte enseignant/admin |  |  |  |
| Modifier le projet cree |  |  |  |
| Ajouter un membre eleve |  |  |  |
| Retirer puis rajouter un membre eleve |  |  |  |
| Verifier l'affichage des projets cote eleve membre |  |  |  |
| Verifier qu'un eleve non membre ne voit pas le projet |  |  |  |
| Ouvrir le Kanban du projet |  |  |  |
| Creer une tache dans une colonne valide |  |  |  |
| Creer une tache sans colonne explicite et verifier le fallback |  |  |  |
| Modifier titre, description, priorite, date et statut d'une tache |  |  |  |
| Assigner une tache a un membre valide |  |  |  |
| Verifier qu'une assignation a un non-membre est refusee ou nettoyee |  |  |  |
| Deplacer une tache entre deux colonnes du meme projet |  |  |  |
| Verifier qu'une tache ne peut pas etre rattachee a une colonne d'un autre projet |  |  |  |
| Supprimer une tache et verifier qu'elle est archivee, pas supprimee physiquement |  |  |  |
| Ajouter, modifier et supprimer un item de checklist |  |  |  |
| Ajouter un commentaire de tache |  |  |  |
| Ajouter une entree de journal/log projet |  |  |  |
| Creer un livrable |  |  |  |
| Modifier un livrable |  |  |  |
| Valider ou rejeter un livrable avec un compte autorise |  |  |  |
| Creer une evidence texte/lien |  |  |  |
| Televerser une evidence fichier prive autorisee |  |  |  |
| Verifier qu'un fichier dangereux ou vide est refuse |  |  |  |
| Telecharger une evidence privee avec un membre autorise |  |  |  |
| Verifier qu'un non-membre ne telecharge pas l'evidence privee |  |  |  |
| Verifier qu'un mauvais `project_id` ou `evidence_id` ne donne pas acces au fichier |  |  |  |
| Tester l'assistant IA professeur Projects si configure |  |  |  |
| Appliquer une suggestion IA professeur de taches |  |  |  |
| Verifier que les taches IA sont placees dans la colonne attendue |  |  |  |
| Tester l'assistant IA eleve si active globalement et sur le projet |  |  |  |
| Verifier que l'IA eleve est refusee si desactivee |  |  |  |
| Archiver le projet |  |  |  |
| Verifier les restrictions sur un projet archive |  |  |  |

## Tests a faire sur site de preproduction

| Controle | OK | KO | Remarque |
| --- | --- | --- | --- |
| Installer la meme archive que celle destinee au partage |  |  |  |
| Activer le plugin sur une base contenant deja des donnees OuInPo |  |  |  |
| Verifier qu'aucune migration destructrice n'est executee |  |  |  |
| Verifier les modules actifs apres mise a jour |  |  |  |
| Verifier au moins un compte admin, enseignant et eleve |  |  |  |
| Verifier les pages publiques/shortcodes deja publies |  |  |  |
| Verifier les uploads prives avec les reglages serveur reels |  |  |  |
| Verifier les appels IA avec les cles de preproduction |  |  |  |
| Consulter les logs PHP/WordPress apres les tests |  |  |  |

## Criteres de blocage release

| Blocage | OK | KO | Remarque |
| --- | --- | --- | --- |
| Erreur fatale PHP a l'activation ou au chargement admin |  |  |  |
| Migration qui s'execute a chaque requete normale |  |  |  |
| Perte ou suppression non demandee de donnees existantes |  |  |  |
| Module inconnu conserve comme actif apres sauvegarde |  |  |  |
| Route REST Projects accessible sans permission attendue |  |  |  |
| Eleve non membre pouvant voir/modifier un projet prive |  |  |  |
| Evidence privee telechargeable sans autorisation |  |  |  |
| Upload dangereux accepte |  |  |  |
| Creation/modification/deplacement de tache cassee |  |  |  |
| Assistant IA retournant une erreur de parsing sur une reponse valide |  |  |  |
| Shortcode public majeur vide ou en erreur |  |  |  |
| Erreur JavaScript bloquante sur Kanban ou formulaires principaux |  |  |  |

## Notes de passage

| Date | Site | Version testee | Testeur | Remarques |
| --- | --- | --- | --- | --- |
|  |  |  |  |  |
