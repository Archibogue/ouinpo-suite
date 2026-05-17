# Roles et droits

OuInPo Suite ajoute des capacites dediees plutot que de donner tous les droits WordPress aux enseignants.

Roles principaux :

- `administrator` : tous les droits OuInPo.
- `ouinpo_teacher` : gestion pedagogique selon les capacites installees.
- `ouinpo_student` : lecture, progression et depot de travaux si active.

Capacites importantes :

- `ouinpo_manage_settings` : reglages.
- `ouinpo_manage_exercises` : exercices et contenus.
- `ouinpo_manage_classes` : classes et eleves.
- `ouinpo_manage_assessments` : devoirs et evaluations.
- `ouinpo_manage_ai` : reglages IA et parcours.
- `ouinpo_view_student_data` : consultation des donnees eleves.

Les roles historiques `prof` et `eleve` peuvent recevoir les capacites utiles pour compatibilite. Les eleves ne doivent pas recevoir `upload_files` pour les depots OuInPo.

## Points de verification

- Verifier quels modules sont actifs avant de distribuer des liens aux eleves.
- Verifier les pages publiques et les shortcodes presents.
- Verifier les options d'acces public des exercices, solutions, sujets pratiques et IA.
- Ne pas donner les capacites d'administration globale a un compte qui ne doit gerer que des contenus pedagogiques.
- Tester avec un compte enseignant et un compte eleve distincts avant une utilisation en classe.
