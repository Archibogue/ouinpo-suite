# Modèle d'import JSON PataDesk

Dupliquer **modele-scenario.json**, adapter son contenu, puis importer la copie
dans **OuInPo Suite → PataDesk → Importer un scénario JSON**.

Le modèle contient un scénario jouable : un ticket, un demandeur, un spécialiste,
une application fictive, un log et un fichier PHP modifiable avec sa correction.
Il est au format PataDesk `format_version: 1`, distinct des packs généraux OuInPo.

Le modèle utilise le parcours guidé jusqu'à la **clôture**, avec qualification
argumentée, création de fiche depuis un message brut, dialogue IA avec le demandeur
et le DBA, puis confirmation simulée du demandeur. Les options suivantes restent
facultatives pour conserver les imports existants.

## Options pédagogiques B1.2

### Partir d'un message brut (facultatif)

Sur un ticket, ajouter :

```json
"intake_mode": "from_request",
"raw_request": "Bonjour, mon export ne fonctionne plus depuis hier. Camille, comptabilité."
```

Sans `intake_mode`, ou avec `"prepared"`, le ticket préparé reste affiché comme
avant. En mode `from_request`, le message brut est obligatoire. Conserver aussi
les champs habituels `title`, `description` et `requester_id` pour la configuration
enseignante et les échanges simulés ; ils ne préremplissent pas la fiche élève.
L'élève rédige sa fiche et ses questions avant le traitement. Les questions libres
ne déclenchent pas de réponse automatique. Le message et les versions de la fiche
sont conservés dans l'historique et le bilan.

### Dialogue IA avec le demandeur et les spécialistes

Chaque ticket peut déclarer :

```json
"ai_dialogue": {
  "enabled": true,
  "requester_context": "Camille travaille en comptabilité. L’export échoue depuis hier. Les autres écrans fonctionnent.",
  "specialists": [
    {
      "specialist_id": "dba",
      "context": "Une migration a eu lieu hier. La base est accessible. Demander à l’élève le message exact du journal avant de proposer une piste."
    }
  ]
}
```

- `enabled` vaut `false` si l'option est absente. Elle fonctionne avec les deux
  modes de création de ticket.
- `requester_context` vide désactive uniquement le dialogue avec le demandeur.
- `specialist_id` doit désigner un spécialiste de la liste racine, sans doublon.
  Prévoir au plus 20 spécialistes IA par ticket.
- Chaque contexte est limité à 6 000 octets. **Tout son contenu peut être révélé
  par l'interlocuteur à l'élève** : y placer les faits communicables, jamais le
  corrigé complet ni les critères privés d'évaluation.
- Chaque interlocuteur reçoit uniquement son contexte, la question et jusqu'à
  huit échanges récents avec lui. Les contextes et historiques des autres
  interlocuteurs, les corrections et le snapshot complet ne sont pas transmis.
- Activer également l'IA globale et son usage chat (`ouinpo_ai_usage_chat_rag`)
  dans les réglages du site. Les quotas étudiant s'appliquent dans un compteur
  dédié aux dialogues PataDesk.
- Les échanges IA sont historisés et exportés. Ils ne débloquent pas les ressources
  et ne remplissent pas les prérequis d'actions : les actions prédéfinies restent
  nécessaires pour les tests, les interventions et la validation de clôture.
- Une indisponibilité IA affiche un message sans enregistrer de réponse fictive.
  L'élève peut réessayer ou continuer avec les actions prédéfinies.

### Qualification et fin du parcours (paramètres)

À la racine : `"completion_status": "closed"` exige la clôture de tous les tickets.
`"resolved"` (valeur par défaut si absente) termine le parcours dès qu'ils sont
tous résolus ou clôturés. « Terminé » ne signifie pas « compétence acquise ».

Sur chaque ticket, exemple :

```json
"guided": true,
"qualification_required": ["nature", "impact", "urgency", "priority", "priority_justification"],
"requester_validation": {
  "enabled": true,
  "replies": [
    {"outcome": "persists", "message": "Le problème persiste. Merci de reprendre les vérifications."},
    {"outcome": "confirmed", "message": "Je confirme que ma demande est satisfaite."}
  ]
}
```

- `guided` vaut `false` par défaut. À `true`, les champs sélectionnés doivent
  être renseignés (ni vides ni « À qualifier »), les actions de
  `resolution_requires` effectuées et les tests de `resolution_tests` réussis
  avant résolution. Une réponse libre remplie n'est pas notée automatiquement.
- `qualification_required` vaut `[]` par défaut : choisir seulement les étapes
  souhaitées. `nature` accepte `incident`, `service` (assistance/service),
  `evolution` ou les valeurs initiales `""` / `"À qualifier"`.
  La nature se stocke dans `fields.nature`, séparément de `fields.category`.
- `fields.priority_justification` contient le raisonnement de l'élève ; ce champ
  est distinct de `priority`. SegFault peut apprécier ce texte dans le bilan,
  sans accès aux valeurs `expected` ni aux corrigés professeur.
- La validation est désactivée par défaut. Si activée, définir de 1 à 10 réponses,
  utilisées dans l'ordre, une par nouvelle résolution. La dernière doit confirmer
  et sera réutilisée si nécessaire. Les messages futurs sont privés.
- `persists` rouvre le ticket et invalide les tests exigés. Prévoir
  `resolved → reopened`, `reopened → resolved`, une action `resolve` répétable
  accessible depuis `reopened`, et des tests relançables. `confirmed` autorise
  l'action `close` depuis `resolved`, avec la transition `resolved → closed`.
- Le bouton `__validate_requester` est créé automatiquement ; cet identifiant
  et `__reply` sont réservés. Ne pas ajouter ces actions dans le JSON.
- La confirmation est la procédure choisie par l'enseignant pour ce scénario,
  pas une exigence universelle du BTS. Pour travailler seulement la résolution,
  désactiver cette option et choisir `completion_status: "resolved"`.

Le compte rendu garde cinq champs obligatoires : `cause` (cause ou analyse adaptée
à la demande), `solution` (actions réalisées), `tests`, `result`, `message`.
Le bilan sépare fin du parcours, contrôles de présence/traces, appréciation IA
des textes et évaluation pédagogique finale du professeur.

## Importer et affecter

1. Installer la version du module comprenant la console de correction.
2. Importer le fichier JSON : le scénario apparaît dans l'éditeur, en brouillon.
3. Vérifier les champs, sélectionner **Publié** et **Enregistrer le scénario**.
4. Ouvrir **Affectations** et choisir les étudiants, classes ou sous-groupes.
5. L'élève accède au scénario depuis `[ouinpo_ticket_simulator]`.

La publication et les affectations se font dans WordPress : elles ne sont pas
incluses dans le JSON. Les identifiants des demandeurs et spécialistes désignent
des personnes fictives, pas des comptes WordPress.

## Champs à personnaliser

| Emplacement | Usage |
| --- | --- |
| `title`, `description` | Nom et contexte de l'atelier |
| `users` | Demandeurs fictifs : `id`, `label`, `service` |
| `specialists` | Spécialistes : `id`, `label`, `service` |
| `resources` | Objets techniques et extraits pédagogiques |
| `tickets` | Un ou plusieurs tickets du scénario |
| `tickets[].requester_id` | Identifiant d'un élément de `users` |
| `tickets[].fields` | Informations initialement visibles par l'élève |
| `tickets[].expected` | Qualification attendue, privée ; supprimer les champs non évalués |
| `tickets[].resources` | Identifiants des ressources associées au ticket |
| `tickets[].visible_resources` | Sous-ensemble visible dès le début |
| `tickets[].transitions` | États accessibles depuis chaque état |
| `tickets[].actions` | Actions disponibles et résultats préparés |
| `tickets[].resolution_requires` | Identifiants d'actions attendues avant résolution |
| `tickets[].resolution_tests` | Identifiants de tests devant avoir réussi |
| `tickets[].expected_solution` | Consignes privées pour la correction professeur |
| `tickets[].bad_resolution` | `reopen` pour rouvrir si les traces manquent, ou `accept` |
| `tickets[].bad_resolution_message` | Réponse du demandeur lors de la réouverture |

## Code modifiable

Pour la ressource `code` :

- `content` : extrait initial contenant le défaut.
- `editable: true` : autorise la console de correction après révélation.
- `expected_content` : extrait corrigé complet, réservé au professeur.
- `filename` et `language` : indications d'affichage ; aucun chemin serveur n'est lu.

Pour l'action de test `verify` :

- `type: "test"`, `repeatable: true`.
- `code_resource: "code"` : ressource à vérifier.
- `requires: ["code"]` : l'action de consultation `code` doit avoir été effectuée.
- `result` : sortie en cas d'échec.
- `success_result` : sortie si le code correspond à la correction attendue.

Le même identifiant `code` est utilisé ici pour une ressource et une action :
ce sont deux collections distinctes. `code_resource` pointe vers une ressource ;
`requires` pointe toujours vers des actions du même ticket.

Le moteur compare les textes, en ignorant les fins de ligne et espaces finaux.
Il n'exécute pas le programme et ne reconnaît pas toutes les corrections
sémantiquement équivalentes. Une nouvelle modification invalide les tests
précédents associés à cet extrait.

## Actions et conditions

- `states` : états où l'action est proposée ; tableau vide = tous, sous réserve des transitions.
- `requires` : actions déjà effectuées, toutes nécessaires.
- `reveal` : ressources révélées après l'action, ou à réception de la réponse.
- `to_status` : état après l'action.
- `return_status` : état après réception d'une réponse utilisateur ou spécialiste.
- `specialist_id` : identifiant du spécialiste destinataire.
- `requires_message` : oblige à rédiger un texte ; toujours obligatoire pour une demande spécialiste.
- `cost` : minutes fictives ; `score` : points facultatifs privés, attribués une seule fois.

Pour une réponse conditionnelle classique, sans `code_resource`, ajouter :

```json
"variants": [
  {
    "requires": ["logs"],
    "result": "Vous disposez maintenant du journal de l'incident.",
    "outcome": "info"
  }
]
```

La première variante dont les prérequis sont remplis est retenue, sinon `result`.
`outcome` accepte `info`, `success` ou `failure`. Les tests avec `code_resource`
utilisent la comparaison de code à la place des variantes.

États possibles : `new`, `accepted`, `diagnosing`, `waiting_user`,
`waiting_specialist`, `escalated`, `resolving`, `resolved`, `closed`, `reopened`.

Types d'action courants : `take`, `question`, `consult`, `test`, `diagnostic`,
`technical`, `communication`, `specialist`, `transfer`, `escalate`, `reassign`,
`resolve`, `close`. Le bouton de réception de réponse est créé par le moteur :
ne pas ajouter d'action nommée `__reply`.

## Ajouter un ticket

Dupliquer un objet de `tickets`, changer son `id` (par exemple `INC-0002`), son
contenu et ses références. Les identifiants d'action doivent être uniques dans
chaque ticket ; ceux des ressources sont uniques dans le scénario. Une ressource
peut être associée à plusieurs tickets, avec des copies de travail indépendantes.

## Précautions de format

- JSON UTF-8 strict, sans commentaires ni virgule après le dernier élément.
- Dans les chaînes, écrire les retours à la ligne `\n` et les guillemets `\"`.
- Utiliser des identifiants composés de lettres, chiffres, tirets ou underscores.
- Toute référence doit désigner un identifiant existant. Les cycles de prérequis sont refusés.
- Conserver les tableaux racine `users`, `specialists`, `resources`, `tickets`.
- Taille maximale du scénario : 1 Mo ; extrait : 100 Ko.

Le JSON contient les corrections professeur : le conserver côté enseignant,
sans le publier comme pièce jointe accessible aux élèves. Ce dossier est sous
`tools/`, exclu de l'archive du plugin par le script de distribution existant.

Une importation crée un nouveau modèle ; elle ne remplace pas les copies déjà
figées dans les tentatives. Affecter le nouveau scénario pour un nouveau parcours.
