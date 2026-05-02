# Licence des contenus pédagogiques OuInPo Suite

Ce document précise la licence applicable aux contenus pédagogiques redistribuables avec OuInPo Suite.

## 1. Licence générale des contenus originaux

Sauf mention contraire, les contenus pédagogiques originaux fournis dans les packs OuInPo Suite sont placés sous licence :

**Creative Commons Attribution — Pas d’utilisation commerciale — Partage dans les mêmes conditions 4.0 International**

Identifiant court :

```text
CC BY-NC-SA 4.0
```

URL de référence :

```text
https://creativecommons.org/licenses/by-nc-sa/4.0/
```

Cette licence autorise notamment le partage et l’adaptation des contenus, à condition :

- de créditer l’auteur ou la source ;
- de ne pas utiliser les contenus à des fins commerciales ;
- de repartager les adaptations sous la même licence ;
- de conserver les mentions de licence.

## 2. Contenus concernés

Cette licence concerne les contenus pédagogiques originaux créés pour OuInPo Suite, notamment :

- exercices originaux ;
- énoncés pédagogiques ;
- indices ;
- solutions ;
- flashcards ;
- textes d’accompagnement ;
- packs pédagogiques JSON ;
- exemples de sujets pratiques originaux.

## 3. Code du plugin

Le code source du plugin n’est pas couvert par ce document.

Le code source est couvert par la licence indiquée dans le fichier :

```text
LICENSE
```

## 4. Données élèves exclues

Aucune donnée élève n’est couverte par cette licence, car aucune donnée élève ne doit être redistribuée.

Sont explicitement exclus de toute distribution :

- comptes élèves ;
- noms, pseudos, adresses e-mail ou identifiants d’élèves ;
- classes et groupes réels ;
- résultats d’élèves ;
- progressions individuelles ;
- statuts d’exercices ;
- badges obtenus par des élèves ;
- historiques de flashcards ;
- dépôts d’élèves ;
- réponses données par des élèves ;
- logs ;
- traces d’usage ;
- tentatives IA ;
- exports WordPress de production ;
- dumps SQL de production.

## 5. Ressources officielles, sujets d’examen et contenus tiers

Les ressources officielles, sujets d’examen, extraits de programmes, documents institutionnels ou contenus provenant de tiers ne sont pas automatiquement placés sous la licence OuInPo.

Ils doivent être vérifiés séparément avant redistribution.

Cela concerne notamment :

- sujets officiels d’examen ;
- extraits de documents ministériels ;
- ressources Éduscol ;
- documents fournis par des tiers ;
- images, textes, polices, icônes ou médias externes.

Lorsqu’un pack contient ou référence une ressource tierce, il doit indiquer clairement :

- la source ;
- l’auteur ou l’organisme d’origine si connu ;
- la licence ou le statut de réutilisation ;
- les éventuelles restrictions.

## 6. Packs pédagogiques

Chaque pack JSON doit contenir un champ `license`.

Exemple :

```json
{
  "pack": {
    "slug": "ouinpo-pack-nsi-premiere",
    "title": "Pack NSI Première",
    "author": "OuInPo",
    "license": "CC BY-NC-SA 4.0",
    "created_at": "2026-05-01"
  }
}
```

Si un pack contient uniquement des contenus de test, utiliser par exemple :

```json
"license": "Contenu de test non destiné à la diffusion"
```

Si un pack contient des contenus mixtes, préciser les exceptions dans un champ `description` ou dans une documentation jointe.

## 7. Attribution conseillée

Attribution minimale recommandée :

```text
Contenus pédagogiques OuInPo — CC BY-NC-SA 4.0
https://www.ouinpo.org/
```

Pour une adaptation :

```text
Adapté à partir de contenus pédagogiques OuInPo — CC BY-NC-SA 4.0
https://www.ouinpo.org/
```

## 8. Absence de garantie

Les contenus sont fournis à des fins pédagogiques.

Ils peuvent être adaptés, corrigés ou complétés selon le contexte d’enseignement.

Aucune garantie n’est donnée concernant :

- l’absence d’erreur ;
- l’adéquation à une progression particulière ;
- la conformité à une version future des programmes ;
- la compatibilité avec tous les environnements WordPress ;
- les droits attachés aux ressources tierces référencées.

## 9. Recommandation avant redistribution

Avant de redistribuer un pack pédagogique, vérifier que :

- le champ `license` est renseigné ;
- aucun contenu élève n’est présent ;
- aucune clé API n’est présente ;
- aucun dump SQL n’est présent ;
- aucune ressource tierce non vérifiée n’est incluse ;
- les contenus officiels ou institutionnels sont clairement identifiés ;
- les contenus originaux sont bien redistribuables sous CC BY-NC-SA 4.0.
