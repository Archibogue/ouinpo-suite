# Checklist de partage — OuInPo Suite

## 1. Sécurité et confidentialité

- [x] Aucune clé API n’est présente dans le code.
- [x] Aucun mot de passe n’est présent dans le code.
- [x] Aucun token secret n’est présent dans le code.
- [x] Aucun chemin absolu d’hébergement n’est présent dans le code.
- [x] Aucun identifiant PlanetHoster ou base de données n’est présent.
- [x] Aucune donnée élève n’est incluse.
- [x] Aucun résultat d’élève n’est inclus.
- [x] Aucun log de progression réel n’est inclus.
- [x] Aucun dump SQL de production n’est inclus.

## 2. Installation

- [x] Le plugin s’active sur un WordPress vierge.
- [x] Les tables nécessaires sont créées automatiquement.
- [x] Le préfixe WordPress n’est jamais supposé être `wp_`.
- [x] Les pages nécessaires peuvent être créées ou documentées.
- [x] La désactivation ne supprime pas les données.
- [ ] La désinstallation est prudente et documentée.

## 3. Configuration

- [x] L’IA est désactivée par défaut ou inutilisable sans clé.
- [x] Les clés API se configurent uniquement depuis l’administration.
- [x] Les modules peuvent être activés ou désactivés.
- [x] Les textes RGPD / IA sont configurables.
- [x] Les URL du site ne sont pas codées en dur.

## 4. Contenus pédagogiques

- [x] Le moteur du plugin est séparé des contenus.
- [x] Les compétences BO peuvent être importées.
- [x] Les exercices peuvent être importés séparément.
- [x] Les flashcards peuvent être importées séparément.
- [x] Les sujets pratiques peuvent être importés séparément.
- [x] Les licences des contenus sont précisées.

## 5. Maintenance

- [x] Les namespaces sont cohérents.
- [x] Les versions sont indiquées clairement.
- [x] Un changelog existe.
- [x] Un README d’installation existe.
- [x] Une page de diagnostic existe ou est prévue.

## 6. Paquet de diffusion

- [x] Le fichier `.distignore` existe.
- [x] Aucun dump SQL n’est présent dans l’archive.
- [x] Aucun export XML du site n’est présent dans l’archive.
- [x] Aucun fichier `.env` ou secret local n’est présent.
- [x] Aucune archive précédente n’est incluse.
- [x] Le zip final contient seulement le code du plugin, la documentation publique et les éventuels packs pédagogiques prévus.

## 7. Licence

- [x] Le fichier `LICENSE` existe.
- [x] La licence du code est indiquée.
- [x] Le fichier `CONTENT-LICENSE.md` existe.
- [x] La licence des contenus pédagogiques est indiquée.
- [x] Les données élèves sont explicitement exclues de toute distribution.
- [x] Les ressources externes ou officielles sont identifiées comme à vérifier.

## 8. Construction du paquet

- [x] Le dossier `tools/` existe.
- [x] Le script `tools/build-dist.ps1` existe.
- [x] Le script produit un zip dans `dist/`.
- [x] Le zip contient un dossier racine `ouinpo-suite/`.
- [x] Le zip ne contient aucun dump SQL.
- [x] Le zip ne contient aucun export XML.
- [x] Le zip ne contient aucun fichier de configuration local.
- [x] Le zip ne contient aucune archive précédente.
- [x] Le zip a été testé sur un WordPress vierge.

## 10. Packs pédagogiques

- [x] Le dossier `packs/` existe.
- [x] Le fichier `packs/README.md` existe.
- [x] Les packs sont au format JSON.
- [x] Aucun pack ne contient de données élèves.
- [x] Aucun pack ne contient de clés API.
- [x] Aucun pack ne contient de dump SQL.
- [x] Les contenus pédagogiques redistribués ont une licence claire.