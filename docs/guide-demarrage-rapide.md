# Guide de demarrage rapide

Ce guide vise une installation de test encadree de OuInPo Suite `0.6.0-beta`.

1. Installer WordPress 6.4 ou plus avec PHP 8.1 ou plus.
2. Installer le zip du plugin depuis **Extensions > Ajouter une extension > Televerser une extension**.
3. Activer le plugin.
4. Ouvrir **OuInPo Suite > Reglages > Diagnostic** et verifier que les tables sont presentes.
5. Ouvrir **OuInPo Suite > Reglages > Import pedagogique** et importer un pack du dossier `packs/`.
6. Creer les pages depuis **Pages & shortcodes**.
7. Tester avec un compte enseignant puis un compte eleve distinct.

Statut : beta technique partageable pour test encadre, non stable pour diffusion large.

Les fonctions IA sont optionnelles. Ne renseignez une cle API que si vous voulez tester explicitement ces fonctions.

## Verification conseillee

- Les pages publiques existent et contiennent les shortcodes attendus.
- Les exercices, flashcards et sujets pratiques importes s'affichent sans erreur.
- Les vues enseignant et eleve sont testees avec deux comptes distincts.
- Les modules non utilises restent desactives.
- Les reglages IA restent desactives si aucun fournisseur n'est valide localement.
- Le diagnostic ne signale pas de cle API, chemin local ou donnee sensible a ne pas exporter.

## En cas de probleme

1. Verifier **OuInPo Suite > Reglages > Pages & shortcodes**.
2. Verifier **OuInPo Suite > Reglages > Diagnostic**.
3. Reimporter le pack de test seulement si les contenus pedagogiques semblent incomplets.
4. Reproduire avec un compte de test, pas avec des donnees eleves reelles.
5. Signaler le probleme avec la version du plugin, WordPress, PHP, les modules actifs, le message exact et les etapes de reproduction.
