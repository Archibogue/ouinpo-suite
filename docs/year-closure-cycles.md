# Cloture annuelle et cycles pedagogiques

Ce document decrit le socle introduit en `0.7.4-beta` pour preparer une cloture annuelle scolaire non destructive.

## Concepts

- Annee scolaire : cadre administratif, par exemple `2025-2026`.
- Classe : groupe annuel rattache a une annee scolaire.
- Niveau : etape pedagogique, par exemple Seconde SNT, Premiere NSI, Terminale NSI, BTS SLAM 1 ou BTS SLAM 2.
- Cycle : parcours pedagogique pouvant couvrir plusieurs annees.
- Cohorte : groupe d'eleves suivant un cycle donne sur une periode.
- Alumni : ancien eleve qui peut encore pratiquer, mais qui n'a plus de suivi pedagogique actif.

## Regle centrale

La cloture annuelle ne doit jamais supprimer automatiquement les projets, livrables, preuves, journaux ou elements de portfolio d'un eleve qui reste dans le meme cycle.

La conservation ne depend donc pas seulement de l'ancienne annee scolaire. Elle depend de la transition :

- meme cycle : conserver les donnees a portee cycle ;
- sortie de cycle : verrouiller, exporter ou archiver selon la politique ;
- sortie complete : convertir en `ouinpo_alumni` et desactiver le suivi pedagogique.

## Cas BTS1 vers BTS2

`BTS SLAM 1` vers `BTS SLAM 2` doit etre configure comme le meme cycle, par exemple `BTS SIO SLAM`.

L'eleve reste `ouinpo_student`. Il garde acces a ses projets, livrables, preuves, journal de bord, competences projet et exports portfolio. La progression annuelle d'exercices pourra etre reinitialisee dans une branche future, mais la memoire projet et portfolio n'est pas purgee.

## Cas Premiere vers Terminale

`Premiere NSI` vers `Terminale NSI` conserve les donnees utiles si les deux niveaux sont rattaches au meme cycle. Les projets actifs peuvent etre reportes, et les projets utiles au portfolio restent accessibles au minimum en lecture.

## Cas Terminale ou BTS2 vers alumni

Un niveau terminal sans niveau suivant propose la conversion alumni. Le role `ouinpo_alumni` permet de pratiquer des exercices et, selon les politiques, de consulter ou exporter des archives de portfolio. Il ne donne pas le droit de stocker du suivi pedagogique actif.

## Donnees conservees

Les domaines de cycle prevus sont notamment :

- projets ;
- taches projet ;
- journal projet ;
- livrables ;
- preuves ;
- liens competences projet ;
- portfolio ;
- badges projet.

## Cohortes

Les tables `ouinpo_cycle_cohorts` et `ouinpo_cycle_members` sont creees pour preparer le suivi pluriannuel par cycle.

Limite de `0.7.4-beta` : l'assistant de cloture ne cree pas encore automatiquement les cohortes ni les appartenances `cycle_members`. La cloture s'appuie pour l'instant sur les niveaux, les cycles, les transitions, les classes cibles et les membres de projets. L'exploitation complete des cohortes est planifiee pour une branche ulterieure, sans migration destructive.

## Donnees annuelles a reinitialiser plus tard

Les domaines annuels peuvent etre remis a zero ou archives lors d'une branche future :

- statuts d'exercices ;
- indices et solutions reveles ;
- reponses d'entrainement ;
- competences annuelles ;
- badges annuels ;
- flashcards ;
- donnees brutes IA ou OCR selon politique.

## Purges RGPD prevues

Cette branche ne supprime aucune donnee. Les purges RGPD sont uniquement journalisees avec `action=purge` et `status=planned`.

Message attendu : `Non execute dans cette version : purge RGPD a implementer apres validation.`

## Limites de cette branche

- Pas de suppression destructive.
- Pas de purge de fichiers.
- Pas de suppression automatique de projets ou portfolio.
- Cohortes et `cycle_members` prepares en base, mais pas encore alimentes automatiquement par l'executeur.
- Pas de refonte front massive.
- Les politiques sont preparees et visibles, mais l'execution des purges RGPD reste a implementer dans une branche dediee.
