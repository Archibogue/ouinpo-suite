# Packs pedagogiques OuInPo Suite

Le dossier `packs/` contient les packs JSON importables depuis **OuInPo Suite > Reglages > Import pedagogique**.

## Pack recommande pour tester

Importer en priorite :

```text
packs/ouinpo-pack-nsi-complet.json
```

Ce pack autonome permet de tester rapidement une installation 0.5.1-beta. Il contient :

- 4 niveaux : Seconde, Premiere, Terminale, Transversal ;
- 3 difficultes : debutant, confirme, expert ;
- 10 domaines NSI/SNT ;
- 21 competences pedagogiques prudentes ;
- 17 exercices, dont 2 sujets pratiques ;
- indices et solutions ;
- 20 flashcards ;
- aucune donnee eleve, aucun compte, aucune cle API.

## Import et doublons

Les contenus sont relies par des slugs stables, jamais par les identifiants numeriques de la base.

L'importeur met a jour les niveaux, domaines, difficultes, competences, exercices, decks et cartes lorsqu'il retrouve les memes slugs ou les memes cles stables. Un second import du meme pack doit donc servir de mise a jour plutot que creer volontairement des doublons.

Les donnees eleves, progressions, soumissions et signatures ne sont pas importees ni supprimees par les packs.

## Schema

Le fichier `ouinpo-pack.schema.json` decrit le format accepte par l'importeur 0.5.1-beta :

- metadata du pack ;
- niveaux ;
- difficultes ;
- domaines ;
- competences ;
- exercices ;
- indices ;
- solutions ;
- metadata de sujet pratique ;
- appels de sujets pratiques ;
- flashcards ;
- champ `badges` reserve pour evolution.

Les badges ne sont pas encore crees depuis les packs dans cette version. Ils restent geres par le module Exercices et ses reglages.

## Prudence

Un pack partage avec des collegues ne doit jamais contenir :

- comptes eleves ;
- groupes ou classes reels ;
- resultats d'eleves ;
- historiques de revision ;
- devoirs rendus ;
- logs ;
- cles API ;
- exports WordPress complets ;
- dumps SQL.
