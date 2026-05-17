# Acces publics et prives

OuInPo Suite separe les usages publics, eleves connectes et enseignants.

- Public : pages contenant des shortcodes publics, selon les reglages d'acces.
- Eleve connecte : progression personnelle, badges, depots et parcours si les modules correspondants sont actifs.
- Enseignant : administration des exercices, classes, competences, devoirs et suivis.
- Administrateur : reglages du plugin, modules, IA et diagnostic.

Sur une installation neuve, les indices, solutions, sujets pratiques et fichiers pratiques ne doivent etre publics que si l'enseignant l'active volontairement.

Ne publiez pas de page contenant des donnees eleves sans verifier les droits et le shortcode utilise.

## Points de controle avant diffusion

| Fonctionnalite | Visiteur | Eleve connecte | Enseignant | Administrateur |
|---|---|---|---|---|
| Liste des exercices | Selon reglage | Oui | Oui | Oui |
| Indices, solutions et fichiers pratiques | Selon reglage | Selon reglage | Oui | Oui |
| Progression personnelle | Non | Oui | Selon capacite | Oui |
| Donnees de classe | Non | Non | Selon capacite | Oui |
| Import pedagogique | Non | Non | Selon capacite | Oui |
| Reglages IA | Non | Non | Non | Oui |
| Diagnostic | Non | Non | Selon capacite | Oui |

Ce tableau decrit l'intention generale du plugin. Il ne remplace pas une verification locale des pages publiees, des shortcodes, des roles WordPress et des extensions tierces actives sur le site.
