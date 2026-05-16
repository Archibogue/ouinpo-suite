# Securite

OuInPo Suite `0.5.1-beta` est une beta technique partageable pour test encadre. Elle n'est pas une version stable destinee a une diffusion large sans validation sur le site cible.

## Versions supportees

| Version | Support |
|---|---|
| `0.5.1-beta` | Corrections de securite pendant la phase de test |
| `< 0.5.1-beta` | Non supporte pour nouveaux tests |

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

## Donnees eleves

Avant tout test avec des enseignants volontaires, utilisez des comptes de test ou des donnees minimales. Si des donnees reelles sont necessaires, elles doivent rester sur l'installation WordPress de l'etablissement ou du testeur, jamais dans le depot GitHub.

## Signalement

Pour signaler une faille, contactez le mainteneur du depot GitHub `Archibogue/ouinpo-suite` en prive lorsque c'est possible. Ne publiez pas de preuve d'exploitation contenant des donnees personnelles ou des secrets.
