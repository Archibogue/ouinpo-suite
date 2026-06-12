# Securite

OuInPo Suite `0.7.1-beta` est une beta technique partageable pour test encadre. Elle n'est pas une version stable destinee a une diffusion large sans validation sur le site cible.

## Versions supportees

| Version | Support |
|---|---|
| `0.7.1-beta` | Corrections de securite pendant la phase de test |
| `< 0.7.1-beta` | Non supporte pour nouveaux tests |

## Donnees a ne jamais publier

Ne publiez jamais dans le depot, un pack pedagogique, une issue ou une capture partagee :

- noms, prenoms, comptes, emails ou resultats d'eleves ;
- copies, depots, notes, historiques ou traces individuelles ;
- dumps SQL, exports WordPress complets, logs serveur ;
- fichiers `.env`, `wp-config.php`, `secrets.php`, `auth.json` ;
- cles API IA, tokens GitHub, tokens Slack ou secrets similaires.

## Cles API et IA

Les fonctions IA sont optionnelles et doivent rester desactivees si aucune cle ou aucun fournisseur n'est configure. Les cles API se configurent dans WordPress, pas dans les fichiers du plugin ni dans les packs.

Les reponses et corrections IA doivent toujours etre relues par l'enseignant. Elles peuvent contenir des erreurs, des oublis ou des formulations inappropriees.

Ne transmettez pas de donnees personnelles dans les prompts IA : noms, prenoms, emails, notes nominatives, difficultes individuelles, informations de sante ou elements permettant d'identifier un eleve.

## Donnees eleves

Avant tout test avec des enseignants volontaires, utilisez des comptes de test ou des donnees minimales. Si des donnees reelles sont necessaires, elles doivent rester sur l'installation WordPress de l'etablissement ou du testeur, jamais dans le depot GitHub.

Selon les modules actives, l'installation peut stocker des comptes WordPress, groupes, progressions, reponses, badges, depots, signatures Gate, adresses IP et user-agents. Ces donnees doivent etre traitees comme des donnees pedagogiques sensibles.

## Bonnes pratiques administrateur

- Installer d'abord sur un site de test ou une copie du site cible.
- Sauvegarder les fichiers et la base avant une mise a jour.
- Verifier les pages publiques et les shortcodes apres activation.
- Verifier les roles et capacites avant de creer des comptes eleves.
- Laisser l'IA desactivee tant qu'aucun cadre local n'est valide.
- Ne jamais distribuer de zip contenant un export SQL, un export WordPress personnel, des logs ou des secrets.

## Signalement

Pour signaler une faille, contactez le mainteneur du depot GitHub `Archibogue/ouinpo-suite` en prive lorsque c'est possible. Ne publiez pas de preuve d'exploitation contenant des donnees personnelles ou des secrets.
