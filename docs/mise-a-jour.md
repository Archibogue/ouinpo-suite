# Mise a jour

Avant une mise a jour :

1. Sauvegarder la base WordPress et les fichiers du site.
2. Noter les modules OuInPo actifs.
3. Verifier qu'aucune cle API n'est stockee dans un fichier du theme ou du plugin.
4. Installer le nouveau zip depuis WordPress ou remplacer le dossier du plugin.
5. Reactiver le plugin si necessaire.
6. Ouvrir **OuInPo Suite > Reglages > Diagnostic**.

Pour une installation existante, les acces publics deja ouverts avant `0.5.0` peuvent etre conserves par migration douce. Sur une installation neuve, les acces publics sensibles sont fermes par defaut.

Apres mise a jour, tester au minimum les pages d'exercices, de progression, de flashcards et les modules optionnels utilises par la classe.

## Verification apres mise a jour

- Les tables principales sont presentes dans le diagnostic.
- Les pages publiques existent encore et contiennent les shortcodes attendus.
- Les roles `ouinpo_teacher` et `ouinpo_student` existent, ainsi que les roles historiques necessaires aux sites existants.
- Les options IA restent desactivees ou configurees selon le choix local.
- Les cles API ne sont pas exportees dans un zip, un dump ou une capture partagee.
- Les donnees eleves restent sur l'installation WordPress de l'etablissement ou du testeur.

Un retour arriere fiable necessite generalement une restauration de la sauvegarde fichiers et base. Le plugin ne promet pas de downgrade automatique.
