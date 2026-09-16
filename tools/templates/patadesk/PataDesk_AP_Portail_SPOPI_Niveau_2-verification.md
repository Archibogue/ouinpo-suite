# Vérification SPOPI — Niveau 2

Fichier contrôlé : `PataDesk_AP_Portail_SPOPI_Niveau_2.json`, fourni par l'enseignant.
Aucune modification du JSON nécessaire pour les parcours testés.

## Résultats

| Ticket | Sujet | Actions contrôlées | Résultat |
| --- | --- | ---: | --- |
| N2-01 | Priorité critique et spécificité CSS | 12 | Parcours jusqu'à la clôture réussi |
| N2-02 | Formulaire HTML incohérent | 11 | Parcours jusqu'à la clôture réussi |
| N2-03 | Débordement tablette/mobile | 12 | Parcours jusqu'à la clôture réussi |
| N2-04 | Navigation clavier sur les cartes | 13 | Parcours jusqu'à la clôture réussi |

**56 assertions réussies, 4 tickets et 48 actions couvertes.** Vérifications avec
le moteur réel de PataDesk, sans écriture dans WordPress :

- validation JSON, références, prérequis et transitions de retour ;
- résolution prématurée suivie de réouverture ;
- échec des cinq extraits initiaux et réussite avec les corrections attendues ;
- invalidation des tests lors d'une nouvelle modification de code ;
- résolution documentée satisfaisant les traces attendues puis clôture ;
- exécution des actions alternatives et réception des réponses spécialistes.

Les corrections ont également été relues : exclusion des tickets critiques de
la règle CSS grise, association label/champ et groupe radio commun, bouton submit
et description obligatoire, adaptation de la grille et défilement interne des
journaux, liens natifs et focus visible pour les cartes.

Commande reproductible :

```powershell
php tools/check-patadesk-spopi.php "D:\Téléchargements\PataDesk_AP_Portail_SPOPI_Niveau_2.json"
```

## Points à connaître

- Les actions « Ajouter une note technique » sont de type `communication` :
  elles envoient un message au demandeur. Utiliser l'onglet **Notes** pour une
  note interne.
- Les tests comparent les extraits à une correction précise. Une autre correction
  HTML/CSS valide peut être refusée : ils ne prouvent ni le rendu responsive ni
  l'accessibilité réelle dans un navigateur.
- Le scénario ne configure pas les nouvelles options de parcours guidé ou de
  validation du demandeur. Par défaut, le parcours est terminé à la résolution ;
  la clôture reste accessible ensuite.
- Les tests couvrent un parcours principal et les actions alternatives, pas
  toutes les permutations possibles. Aucun rendu navigateur ni déploiement sur
  le site WordPress n'a été réalisé.

Le JSON original est utilisable sans la correction de transition appliquée au
ticket P03 du niveau 1 : aucun problème analogue n'a été détecté ici.
