<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

// Static, bundled teaching data. Direct web access returns no scenario content.
return json_decode(<<<'SCENARIO_JSON'
{
  "format_version": 1,
  "title": "Le mystérieux export de Madame Boulier",
  "description": "Vous assurez le support de KpiKoi. Qualifiez la demande, investiguez et documentez une résolution vérifiée.",
  "users": [
    {
      "id": "boulier",
      "label": "Madame Boulier",
      "service": "Comptabilité"
    }
  ],
  "specialists": [
    {
      "id": "dba",
      "label": "Alex Martin — DBA",
      "service": "Bases de données"
    },
    {
      "id": "support",
      "label": "Responsable support",
      "service": "DSI"
    }
  ],
  "resources": [
    {
      "id": "app",
      "label": "KpiKoi",
      "type": "application",
      "filename": "KpiKoi.txt",
      "language": "text",
      "content": "KpiKoi — version 2.3\nApplication de comptabilité.\nMise à jour : hier soir."
    },
    {
      "id": "capture",
      "label": "Capture transcrite de l’erreur",
      "type": "attachment",
      "filename": "capture-export.txt",
      "language": "text",
      "content": "KpiKoi > Export mensuel\nErreur 500 — impossible de générer le fichier."
    },
    {
      "id": "code",
      "label": "Service d’export",
      "type": "source",
      "filename": "ExportService.php",
      "language": "php",
      "content": "<?php\n// Extrait pédagogique fictif.\n$sql = \"\n    SELECT id, montant, date_creation\n    FROM operations\n    WHERE MONTH(date_creation) = :month\n\";\n$stmt = $pdo->prepare($sql);\n$stmt->execute(['month' => $month]);",
      "editable": true,
      "expected_content": "<?php\n// Extrait pédagogique fictif.\n$sql = \"\n    SELECT id, montant, created_at\n    FROM operations\n    WHERE MONTH(created_at) = :month\n\";\n$stmt = $pdo->prepare($sql);\n$stmt->execute(['month' => $month]);"
    },
    {
      "id": "schema",
      "label": "Schéma actuel",
      "type": "sql_table",
      "filename": "operations.sql",
      "language": "sql",
      "content": "CREATE TABLE operations (\n    id INTEGER PRIMARY KEY,\n    montant DECIMAL(10,2),\n    created_at DATETIME,\n    utilisateur_id INTEGER\n);"
    },
    {
      "id": "logs",
      "label": "Journal de l’export",
      "type": "log",
      "filename": "export.log",
      "language": "text",
      "content": "SQLSTATE[42S22]\nUnknown column 'date_creation'"
    }
  ],
  "tickets": [
    {
      "id": "INC-0001",
      "title": "KpiKoi — Export mensuel impossible",
      "description": "Depuis ce matin, l’export mensuel de KpiKoi ne fonctionne plus. J’en ai besoin pour la réunion de 14 h.",
      "requester_id": "boulier",
      "fields": {
        "service": "Comptabilité",
        "category": "Applicatif",
        "priority": "Haute",
        "application": "KpiKoi",
        "location": "Bâtiment A",
        "fictional_date": "Jeudi, 09:02",
        "sla": "Avant 14 h",
        "impact": "À qualifier",
        "urgency": "À qualifier"
      },
      "expected": {
        "category": "Applicatif",
        "priority": "Haute"
      },
      "resources": [
        "app",
        "capture",
        "code",
        "schema",
        "logs"
      ],
      "visible_resources": [
        "app"
      ],
      "transitions": {
        "new": [
          "accepted"
        ],
        "accepted": [
          "diagnosing"
        ],
        "diagnosing": [
          "waiting_user",
          "waiting_specialist",
          "escalated",
          "resolving",
          "resolved"
        ],
        "waiting_user": [
          "diagnosing"
        ],
        "waiting_specialist": [
          "diagnosing"
        ],
        "escalated": [
          "diagnosing"
        ],
        "resolving": [
          "diagnosing",
          "waiting_user",
          "waiting_specialist",
          "resolved"
        ],
        "resolved": [
          "closed",
          "reopened"
        ],
        "closed": [],
        "reopened": [
          "diagnosing",
          "waiting_user",
          "waiting_specialist",
          "escalated",
          "resolving",
          "resolved"
        ]
      },
      "actions": [
        {
          "id": "take",
          "label": "Prendre en charge",
          "type": "take",
          "description": "",
          "result": "",
          "requires": [],
          "states": [
            "new"
          ],
          "reveal": [],
          "variants": [],
          "cost": 1,
          "score": 0
        },
        {
          "id": "diagnose",
          "label": "Commencer le diagnostic",
          "type": "diagnostic",
          "description": "",
          "result": "",
          "requires": [],
          "states": [
            "accepted"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": 0,
          "to_status": "diagnosing"
        },
        {
          "id": "since",
          "label": "Depuis quand le problème existe-t-il ?",
          "type": "question",
          "description": "",
          "result": "Depuis la mise à jour faite hier soir.",
          "requires": [],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": 1,
          "to_status": "waiting_user",
          "return_status": "diagnosing"
        },
        {
          "id": "scope",
          "label": "Le reste de l’application fonctionne-t-il ?",
          "type": "question",
          "description": "",
          "result": "Oui. Je peux consulter les tableaux de bord. C’est uniquement l’export qui échoue.",
          "requires": [],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": 1,
          "to_status": "waiting_user",
          "return_status": "diagnosing"
        },
        {
          "id": "screenshot",
          "label": "Demander une capture d’écran",
          "type": "question",
          "description": "",
          "result": "Voici le message affiché lors de l’export.",
          "requires": [],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [
            "capture"
          ],
          "variants": [],
          "cost": 2,
          "score": 0,
          "to_status": "waiting_user",
          "return_status": "diagnosing"
        },
        {
          "id": "test_app",
          "label": "Tester l’application",
          "type": "test",
          "description": "",
          "result": "Connexion : OK\nAuthentification : OK\nDashboard : OK\nExport mensuel : ÉCHEC",
          "requires": [],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": 0,
          "repeatable": true
        },
        {
          "id": "logs",
          "label": "Consulter les logs",
          "type": "consult",
          "description": "",
          "result": "SQLSTATE[42S22]\nUnknown column 'date_creation'",
          "requires": [],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [
            "logs"
          ],
          "variants": [],
          "cost": 2,
          "score": 2
        },
        {
          "id": "code",
          "label": "Consulter ExportService.php",
          "type": "consult",
          "description": "",
          "result": "ExportService.php est disponible dans Ressources. Ouvrez sa console de correction pour modifier votre copie de l’extrait.",
          "requires": [
            "logs"
          ],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [
            "code"
          ],
          "variants": [],
          "cost": 2,
          "score": 2
        },
        {
          "id": "schema",
          "label": "Consulter le schéma actuel de la table",
          "type": "consult",
          "description": "",
          "result": "Le schéma actuel de operations est disponible dans les ressources.",
          "requires": [
            "logs"
          ],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [
            "schema"
          ],
          "variants": [],
          "cost": 2,
          "score": 2
        },
        {
          "id": "dba",
          "label": "Demander l’avis du DBA",
          "type": "specialist",
          "description": "",
          "result": "Le champ date_creation a été renommé created_at lors de la migration d’hier soir.",
          "requires": [
            "logs"
          ],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": 2,
          "specialist_id": "dba",
          "to_status": "waiting_specialist",
          "return_status": "diagnosing"
        },
        {
          "id": "transfer",
          "label": "Transférer temporairement au DBA",
          "type": "transfer",
          "description": "",
          "result": "J’ai confirmé la migration du schéma. Je vous rends le ticket pour adapter la requête.",
          "requires": [
            "logs"
          ],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": 0,
          "specialist_id": "dba",
          "to_status": "waiting_specialist",
          "return_status": "diagnosing"
        },
        {
          "id": "escalate",
          "label": "Escalader au responsable support",
          "type": "escalate",
          "description": "",
          "result": "L’incident est limité à l’export. Poursuivez le diagnostic applicatif.",
          "requires": [],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": -2,
          "specialist_id": "support",
          "to_status": "escalated",
          "return_status": "diagnosing"
        },
        {
          "id": "restart",
          "label": "Redémarrer le poste utilisateur",
          "type": "technical",
          "description": "",
          "result": "Le poste redémarre. L’export échoue toujours.",
          "requires": [],
          "states": [
            "diagnosing",
            "reopened"
          ],
          "reveal": [],
          "variants": [],
          "cost": 10,
          "score": -2
        },
        {
          "id": "verify",
          "label": "Tester l’export avec mon code",
          "type": "test",
          "description": "",
          "result": "ÉCHEC\nL’extrait enregistré ne correspond pas à la correction attendue. Vérifiez la requête et enregistrez vos modifications.",
          "requires": [
            "code"
          ],
          "states": [
            "resolving",
            "diagnosing",
            "reopened"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": 3,
          "repeatable": true,
          "code_resource": "code",
          "success_result": "Test export mensuel\n-------------------\n128 lignes exportées.\nFichier généré correctement.\n\nSUCCÈS"
        },
        {
          "id": "inform",
          "label": "Informer Madame Boulier de l’avancement",
          "type": "communication",
          "description": "",
          "result": "Votre message a été ajouté à la conversation.",
          "requires": [],
          "states": [
            "diagnosing",
            "resolving",
            "reopened"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": 0,
          "repeatable": true
        },
        {
          "id": "resolve",
          "label": "Résoudre et documenter",
          "type": "resolve",
          "description": "",
          "result": "",
          "requires": [],
          "states": [
            "diagnosing",
            "resolving",
            "reopened"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": 0,
          "repeatable": true
        },
        {
          "id": "close",
          "label": "Clôturer le ticket",
          "type": "close",
          "description": "",
          "result": "Ticket clôturé.",
          "requires": [],
          "states": [
            "resolved"
          ],
          "reveal": [],
          "variants": [],
          "cost": 2,
          "score": 0
        }
      ],
      "resolution_requires": [
        "code"
      ],
      "bad_resolution": "reopen",
      "bad_resolution_message": "Le problème persiste lors de l’export. Avez-vous corrigé la requête puis testé le résultat ?",
      "expected_solution": "Identifier le renommage de colonne lors de la migration ; remplacer les deux références date_creation par created_at ; vérifier l’export des 128 lignes ; expliquer la correction au demandeur.",
      "resolution_tests": [
        "verify"
      ]
    }
  ]
}
SCENARIO_JSON
, true);
