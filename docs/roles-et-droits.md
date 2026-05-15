# Roles et droits OuInPo Suite

Les droits reels dependent des modules actives, des options du plugin, des roles et capacites WordPress, ainsi que des reglages de visibilite des pages.

| Profil | Actions typiques |
|---|---|
| Administrateur WordPress | Installe le plugin, active les modules, configure les pages, configure l'IA, gere les reglages globaux, controle les roles et capacites, lance les diagnostics et les imports. |
| Enseignant OuInPo | Gere les exercices, competences, groupes/classes si le module le permet, consulte les soumissions, construit les evaluations, attribue ou consulte les badges selon ses capacites. Il ne configure pas forcement l'IA ni les reglages globaux. |
| Eleve OuInPo | Consulte les exercices autorises, repond aux exercices, suit sa progression, utilise les flashcards, accede eventuellement aux badges et au Gate. |
| Visiteur | Accede seulement aux pages publiques. L'acces IA public n'existe que si l'administrateur l'a explicitement active. Aucun suivi personnel n'est attendu hors comportement volontairement active. |

## Points de verification

- Verifier quels modules sont actifs avant de distribuer des liens aux eleves.
- Verifier les pages publiques et les shortcodes presents.
- Verifier les options d'acces public des exercices, solutions, sujets pratiques et IA.
- Verifier les roles `ouinpo_teacher` et `ouinpo_student`, ainsi que les roles historiques encore supportes pour compatibilite.
- Ne pas donner les capacites d'administration globale a un compte qui ne doit gerer que des contenus pedagogiques.
