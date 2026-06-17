# Roles et droits

OuInPo Suite ajoute des capacites dediees plutot que de donner tous les droits WordPress aux enseignants.

Roles principaux :

- `administrator` : tous les droits OuInPo.
- `ouinpo_teacher` : gestion pedagogique selon les capacites installees.
- `ouinpo_student` : lecture, progression et depot de travaux si active.
- `ouinpo_alumni` : ancien eleve, pratique autorisee sans stockage de suivi pedagogique actif.

Capacites importantes :

- `ouinpo_manage_settings` : reglages.
- `ouinpo_manage_exercises` : exercices et contenus.
- `ouinpo_manage_classes` : classes et eleves.
- `ouinpo_manage_assessments` : devoirs et evaluations.
- `ouinpo_manage_ai` : reglages IA et parcours.
- `ouinpo_view_student_data` : consultation des donnees eleves.
- `ouinpo_practice_exercises` : pratiquer les exercices.
- `ouinpo_track_learning_data` : autorise le stockage du suivi pedagogique actif.
- `ouinpo_view_own_learning_data` : consulter ses propres donnees pedagogiques actives.
- `ouinpo_portfolio_view_own_archive` : consulter ses archives portfolio.
- `ouinpo_portfolio_export_own` : exporter ses archives portfolio.

Le role `ouinpo_alumni` ne recoit pas `ouinpo_track_learning_data`, `ouinpo_projects_edit_own_tasks`, `ouinpo_projects_comment`, `ouinpo_submit_work`, `ouinpo_upload_submission` ni `ouinpo_projects_ai_student_use`.

Les roles historiques `prof` et `eleve` peuvent recevoir les capacites utiles pour compatibilite. Les eleves ne doivent pas recevoir `upload_files` pour les depots OuInPo.

## Points de verification

- Verifier quels modules sont actifs avant de distribuer des liens aux eleves.
- Verifier les pages publiques et les shortcodes presents.
- Verifier les options d'acces public des exercices, solutions, sujets pratiques et IA.
- Ne pas donner les capacites d'administration globale a un compte qui ne doit gerer que des contenus pedagogiques.
- Tester avec un compte enseignant et un compte eleve distincts avant une utilisation en classe.
