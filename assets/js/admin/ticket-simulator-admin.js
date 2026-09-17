(() => {
  "use strict";
  const ui = window.OuinpoTicketUI;
  if (!ui) return;
  const { api, el, button, field, select, error, Desk, statuses } = ui;
  const resources = {
    application: "Application",
    source: "Fichier source",
    script: "Script",
    database: "Base de données",
    sql_table: "Table SQL",
    server: "Serveur",
    workstation: "Poste utilisateur",
    network_device: "Équipement réseau",
    network_service: "Service réseau",
    configuration: "Configuration",
    log: "Log",
    documentation: "Documentation",
    procedure: "Procédure",
    attachment: "Pièce jointe textuelle",
  };
  const actionTypes = {
    take: "Prise en charge",
    question: "Question utilisateur",
    consult: "Consultation",
    test: "Test simulé",
    diagnostic: "Diagnostic",
    technical: "Action technique / correction",
    communication: "Communication",
    specialist: "Avis spécialiste",
    transfer: "Transfert temporaire",
    escalate: "Escalade",
    reassign: "Réaffectation définitive",
    reply: "Réponse préparée",
    resolve: "Résolution",
    close: "Clôture",
  };
  const fields = {
    nature: "Nature de la demande (incident, service, evolution)",
    priority_justification: "Justification de priorité",
    service: "Service",
    location: "Localisation",
    fictional_date: "Date fictive",
    category: "Catégorie",
    subcategory: "Sous-catégorie",
    impact: "Impact",
    urgency: "Urgence",
    priority: "Priorité",
    it_service: "Service informatique",
    assignee: "Assignation fictive",
    sla: "SLA fictif",
    application: "Application",
  };
  const transitions = {
    new: ["accepted"],
    accepted: [
      "diagnosing",
      "waiting_user",
      "waiting_specialist",
      "escalated",
      "resolving",
    ],
    diagnosing: [
      "waiting_user",
      "waiting_specialist",
      "escalated",
      "resolving",
      "resolved",
    ],
    waiting_user: ["diagnosing"],
    waiting_specialist: ["diagnosing"],
    escalated: ["diagnosing"],
    resolving: ["diagnosing", "waiting_specialist", "resolved"],
    resolved: ["closed", "reopened"],
    closed: [],
    reopened: ["diagnosing", "resolving", "waiting_specialist", "resolved"],
  };
  let counter = Date.now();
  const id = (prefix) => prefix + "_" + ++counter;
  const newAction = (type = "consult") => ({
    id: id("action"),
    label: "Nouvelle action",
    type,
    description: "",
    result: "",
    requires: [],
    states: [],
    reveal: [],
    variants: [],
    repeatable: type === "test" || type === "resolve",
    cost: 1,
    score: 0,
  });
  const newTicket = () => ({
    id: id("INC"),
    title: "Nouveau ticket",
    description: "Décrivez la demande initiale.",
    requester_id: "user_1",
    fields: { category: "À qualifier", priority: "À qualifier" },
    expected: {},
    resources: [],
    visible_resources: [],
    transitions: structuredClone(transitions),
    actions: [
      { ...newAction("take"), label: "Prendre en charge", states: ["new"] },
      {
        ...newAction("diagnostic"),
        label: "Commencer le diagnostic",
        states: ["accepted"],
        to_status: "diagnosing",
      },
      {
        ...newAction("resolve"),
        label: "Résoudre avec un compte rendu",
        states: ["diagnosing", "resolving", "reopened"],
      },
      { ...newAction("close"), label: "Clôturer", states: ["resolved"] },
    ],
    resolution_requires: [],
    bad_resolution: "accept",
    expected_solution: "",
  });
  class Admin {
    constructor(root) {
      this.root = root;
      this.manage = root.dataset.canManage === "1";
      this.home();
    }
    async home() {
      try {
        this.root.replaceChildren(el("h1", window.OuinpoTicketing.name));
        if (this.manage) {
          const toolbar = el("div");
          toolbar.append(
            button(
              "Créer un scénario",
              () =>
                this.edit({
                  id: 0,
                  status: "draft",
                  revision: 0,
                  definition: {
                    format_version: 1,
                    title: "Nouveau scénario",
                    description: "",
                    users: [{ id: "user_1", label: "Demandeur", service: "" }],
                    specialists: [],
                    resources: [],
                    tickets: [newTicket()],
                  },
                }),
              this.root,
            ),
          );
          toolbar.append(
            button(
              "Préparer le scénario de démonstration",
              async () =>
                this.edit({
                  id: 0,
                  status: "draft",
                  revision: 0,
                  definition: await api("/demo"),
                }),
              this.root,
            ),
          );
          const label = el("label", "Importer un scénario JSON");
          const input = el("input");
          input.type = "file";
          input.accept = ".json,application/json";
          input.addEventListener("change", async () => {
            try {
              const f = input.files[0];
              if (!f) return;
              if (f.size > 1000000) throw new Error("Fichier limité à 1 Mo.");
              const d = JSON.parse(await f.text());
              if (
                d.format_version !== 1 ||
                !Array.isArray(d.tickets) ||
                !Array.isArray(d.resources) ||
                !Array.isArray(d.users) ||
                !Array.isArray(d.specialists)
              )
                throw new Error("Format de scénario invalide.");
              this.edit({ id: 0, status: "draft", revision: 0, definition: d });
            } catch (e) {
              error(this.root, e);
            }
          });
          label.append(input);
          toolbar.append(label);
          this.root.append(toolbar);
          this.root.append(el("h2", "Mes scénarios"));
          const scenarios = await api("/scenarios");
          scenarios.forEach((s) => {
            const row = el("div", undefined, "ouinpo-ticket-admin-row");
            row.append(
              button(
                `${s.title} · ${s.status} · v${s.revision}`,
                async () => this.edit(await api("/scenarios/" + s.id)),
                this.root,
              ),
              button(
                "Supprimer",
                () => this.deleteScenario(s),
                this.root,
                "ouinpo-ticket-delete",
              ),
            );
            this.root.append(row);
          });
        }
        this.root.append(el("h2", "Tentatives et observation"));
        if (window.OuinpoTicketing?.canDeleteAllAttempts) {
          this.root.append(
            button(
              "Supprimer toutes les tentatives",
              async () => {
                if (
                  !window.confirm(
                    "Supprimer définitivement TOUTES les tentatives PataDesk de TOUS les élèves, y compris les archives, notes, codes et historiques ? Les scénarios et affectations seront conservés. Cette suppression est irréversible.",
                  )
                )
                  return;
                const result = await api("/attempts", "DELETE", {
                  confirm_delete_all: true,
                });
                await this.home();
                const notice = el(
                  "p",
                  `${result.counts.attempts} tentative(s) supprimée(s). La numérotation affichée repart à 1. Les scénarios et affectations sont conservés. Les élèves peuvent recommencer depuis leurs affectations.`,
                );
                notice.setAttribute("role", "status");
                this.root.prepend(notice);
              },
              this.root,
              "ouinpo-ticket-delete",
            ),
          );
        }
        const attempts = await api("/attempts");
        const filter = field(
          this.root,
          "Filtrer par étudiant, scénario ou tentative",
        );
        const list = el("div");
        this.root.append(list);
        const draw = () => {
          list.replaceChildren();
          attempts
            .filter((a) =>
              `${a.student_id} ${a.scenario_id} ${a.number ?? a.id}`.includes(
                filter.value,
              ),
            )
            .forEach((a) => {
              const row = el("div", "", "ouinpo-ticket-admin-row");
              row.append(
                el(
                  "span",
                  `Étudiant #${a.student_id} · scénario #${a.scenario_id} · tentative #${a.number ?? a.id} · ${a.status}`,
                ),
                button(
                  "Observer",
                  async () =>
                    new Desk(this.root, await api("/attempts/" + a.id), () =>
                      this.home(),
                    ),
                  this.root,
                ),
              );
              if (a.status !== "archived" && this.manage) {
                row.append(
                  button(
                    "Archiver",
                    async () => {
                      await api(`/attempts/${a.id}/archive`, "POST", {});
                      await this.home();
                    },
                    this.root,
                  ),
                  button(
                    "Archiver et recommencer",
                    async () => {
                      await api(`/attempts/${a.id}/reset`, "POST", {});
                      await this.home();
                    },
                    this.root,
                  ),
                );
              }
              if (window.OuinpoTicketing?.canDeleteAllAttempts) {
                row.append(
                  button(
                    "Supprimer les tentatives de cet élève",
                    async () => {
                      if (
                        !window.confirm(
                          `Supprimer définitivement toutes les tentatives de l’élève #${a.student_id}, dans tous les scénarios, y compris ses archives, notes, codes et historiques ? Ses affectations et le travail des autres élèves seront conservés.`,
                        )
                      )
                        return;
                      const result = await api(
                        `/students/${a.student_id}/attempts`,
                        "DELETE",
                        { confirm_delete_student: true },
                      );
                      await this.home();
                      const notice = el(
                        "p",
                        `${result.counts.attempts} tentative(s) supprimée(s) pour l’élève #${a.student_id}. Ses affectations sont conservées.`,
                      );
                      notice.setAttribute("role", "status");
                      this.root.prepend(notice);
                    },
                    this.root,
                    "ouinpo-ticket-delete",
                  ),
                );
              }
              list.append(row);
            });
        };
        filter.addEventListener("input", draw);
        draw();
      } catch (e) {
        error(this.root, e);
      }
    }
    edit(record) {
      this.record = record;
      this.drawEditor();
    }
    async deleteScenario(scenario) {
      const title = scenario.title || scenario.definition.title;
      if (
        !window.confirm(
          `Supprimer définitivement « ${title} » ?\n\nToutes ses affectations, ses tentatives (y compris archivées), les tickets, le code enregistré et les historiques seront supprimés. Cette action est irréversible.`,
        )
      )
        return;
      await api(`/scenarios/${scenario.id}`, "DELETE", {
        revision: Number(scenario.revision),
        confirm_delete: true,
      });
      await this.home();
      const notice = el(
        "p",
        "Scénario, affectations et tentatives supprimés.",
        "ouinpo-ticket-notice",
      );
      notice.setAttribute("role", "status");
      this.root.prepend(notice);
    }
    async saveRecord(status = this.record.status) {
      const record = this.record;
      this.record = await api(
        record.id ? "/scenarios/" + record.id : "/scenarios",
        record.id ? "PATCH" : "POST",
        {
          definition: record.definition,
          status,
          revision: Number(record.revision),
        },
      );
      this.drawEditor();
      const message =
        this.record.status === "published"
          ? "Scénario enregistré et publié : les étudiants affectés peuvent maintenant l’ouvrir."
          : "Scénario enregistré. Il reste invisible aux étudiants tant qu’il n’est pas publié.";
      const notice = el("p", message, "ouinpo-ticket-notice");
      notice.setAttribute("role", "status");
      this.root.prepend(notice);
    }
    text(parent, obj, key, label, area = false) {
      const n = field(parent, label, obj[key] ?? "", area);
      n.addEventListener("input", () => {
        obj[key] = n.value;
      });
      return n;
    }
    choice(parent, obj, key, label, options) {
      return select(parent, label, options, obj[key] ?? "", (v) => {
        obj[key] = v;
      });
    }
    check(parent, obj, key, label) {
      const wrap = el("label", "", "ouinpo-ticket-checkbox");
      const n = el("input");
      n.type = "checkbox";
      n.checked = !!obj[key];
      n.addEventListener("change", () => {
        obj[key] = n.checked;
      });
      wrap.append(n, document.createTextNode(label));
      parent.append(wrap);
    }
    refs(parent, obj, key, label, items) {
      const box = el("fieldset");
      box.append(el("legend", label));
      obj[key] ??= [];
      items.forEach((item) => {
        const wrap = el("label", "", "ouinpo-ticket-checkbox");
        const n = el("input");
        n.type = "checkbox";
        n.checked = obj[key].includes(item.id);
        n.addEventListener("change", () => {
          obj[key] = n.checked
            ? [...new Set([...obj[key], item.id])]
            : obj[key].filter((x) => x !== item.id);
        });
        wrap.append(
          n,
          document.createTextNode(
            `${item.label || item.title || item.id} (${item.id})`,
          ),
        );
        box.append(wrap);
      });
      if (!items.length)
        box.append(el("small", "Créez d’abord les éléments correspondants."));
      parent.append(box);
    }
    section(parent, title) {
      const d = el("details");
      d.append(el("summary", title));
      parent.append(d);
      return d;
    }
    collection(parent, title, items, create, render) {
      const box = this.section(parent, title);
      items.forEach((item, i) => {
        const row = this.section(
          box,
          `${i + 1}. ${item.label || item.title || item.id || "Élément"}`,
        );
        render(row, item);
        row.append(
          button(
            "Retirer cet élément",
            () => {
              items.splice(i, 1);
              this.drawEditor();
            },
            this.root,
          ),
        );
      });
      box.append(
        button(
          "Ajouter",
          () => {
            items.push(create());
            this.drawEditor();
          },
          this.root,
        ),
      );
      return box;
    }
    drawEditor() {
      const detailPath = (d) => {
        const path = [];
        for (
          let node = d;
          node && node !== this.root;
          node = node.parentElement
        ) {
          if (node.tagName === "DETAILS")
            path.unshift(node.firstElementChild.textContent);
        }
        return path.join("/");
      };
      const open = new Set(
        [...this.root.querySelectorAll("details[open]")].map(detailPath),
      );
      const root = this.root;
      const r = this.record;
      const s = r.definition;
      root.replaceChildren(el("h1", "Éditeur de scénario"));
      root.append(
        el(
          "p",
          "Les modifications du modèle ne changent pas les tentatives déjà commencées. Les identifiants servent aux liens entre éléments ; conservez-les après avoir configuré ces liens.",
        ),
      );
      const toolbar = el("div", "", "ouinpo-ticket-admin-toolbar");
      toolbar.append(
        button("Retour à la liste", () => this.home(), root),
        button("Enregistrer le scénario", () => this.saveRecord(), root),
      );
      toolbar.append(
        button(
          "Exporter le JSON",
          () => {
            const blob = new Blob([JSON.stringify(s, null, 2)], {
              type: "application/json",
            });
            const url = URL.createObjectURL(blob);
            const a = el("a");
            a.href = url;
            a.download = "patadesk-scenario.json";
            a.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
          },
          root,
        ),
      );
      if (r.id)
        toolbar.append(
          button(
            "Supprimer le scénario",
            () => this.deleteScenario(r),
            root,
            "ouinpo-ticket-delete",
          ),
        );
      root.append(toolbar);
      this.text(root, s, "title", "Titre");
      this.text(root, s, "description", "Contexte", true);
      s.completion_status ??= "resolved";
      this.choice(
        root,
        s,
        "completion_status",
        "Parcours terminé lorsque tous les tickets sont…",
        [
          ["resolved", "Résolus (ou clôturés)"],
          ["closed", "Clôturés"],
        ],
      );
      this.choice(root, r, "status", "Publication", [
        ["draft", "Brouillon"],
        ["published", "Publié — peut être affecté"],
        ["archived", "Archivé — aucun nouveau démarrage"],
      ]);
      this.collection(
        root,
        "Demandeurs fictifs",
        s.users,
        () => ({ id: id("user"), label: "Nouveau demandeur", service: "" }),
        (p, x) => {
          this.text(p, x, "id", "Identifiant");
          this.text(p, x, "label", "Nom");
          this.text(p, x, "service", "Service");
        },
      );
      this.collection(
        root,
        "Spécialistes",
        s.specialists,
        () => ({
          id: id("specialist"),
          label: "Nouveau spécialiste",
          service: "",
        }),
        (p, x) => {
          this.text(p, x, "id", "Identifiant");
          this.text(p, x, "label", "Nom / fonction");
          this.text(p, x, "service", "Service");
        },
      );
      this.collection(
        root,
        "Ressources pédagogiques",
        s.resources,
        () => ({
          id: id("resource"),
          label: "Nouvelle ressource",
          type: "source",
          filename: "exemple.txt",
          language: "text",
          content: "",
        }),
        (p, x) => {
          this.text(p, x, "id", "Identifiant");
          this.text(p, x, "label", "Libellé");
          this.choice(p, x, "type", "Type", Object.entries(resources));
          this.text(p, x, "filename", "Nom du fichier affiché");
          this.text(p, x, "language", "Langage");
          this.text(
            p,
            x,
            "content",
            "Contenu pédagogique / code / log",
            true,
          ).maxLength = 100000;
          this.check(
            p,
            x,
            "editable",
            "Autoriser la modification de cet extrait par l’élève",
          );
          const expectedCode = this.text(
            p,
            x,
            "expected_content",
            "Extrait corrigé attendu — privé, pour les tests de code",
            true,
          );
          expectedCode.maxLength = 100000;
          p.append(
            el(
              "p",
              "La console modifie uniquement cet extrait. La comparaison ignore les fins de ligne et les espaces de fin de ligne ; elle n’exécute pas le code.",
            ),
          );
        },
      );
      this.collection(
        root,
        "Tickets",
        s.tickets,
        () => ({ ...newTicket(), requester_id: s.users[0]?.id || "" }),
        (p, t) => {
          this.text(p, t, "id", "Identifiant du ticket");
          this.text(p, t, "title", "Titre");
          this.text(p, t, "description", "Demande initiale", true);
          t.intake_mode ??= "prepared";
          t.ai_dialogue ??= {
            enabled: false,
            requester_context: "",
            specialists: [],
          };
          t.ai_dialogue.specialists ??= [];
          const dialogue = this.section(
            p,
            "Interlocuteurs IA — option facultative",
          );
          this.check(
            dialogue,
            t.ai_dialogue,
            "enabled",
            "Autoriser le dialogue libre avec les interlocuteurs configurés",
          );
          this.text(
            dialogue,
            t.ai_dialogue,
            "requester_context",
            "Faits connus du demandeur (vide : dialogue demandeur désactivé)",
            true,
          ).maxLength = 6000;
          this.collection(
            dialogue,
            "Spécialistes disponibles par IA",
            t.ai_dialogue.specialists,
            () => ({ specialist_id: s.specialists[0]?.id || "", context: "" }),
            (box, contact) => {
              this.choice(
                box,
                contact,
                "specialist_id",
                "Spécialiste",
                s.specialists.map((person) => [person.id, person.label]),
              );
              this.text(
                box,
                contact,
                "context",
                "Faits que ce spécialiste peut communiquer à l’élève",
                true,
              ).maxLength = 6000;
            },
          );
          dialogue.append(
            el(
              "p",
              "Chaque interlocuteur reçoit seulement ses faits et son propre historique IA. Tout ce qui est écrit ici peut être communiqué à l’élève : ne pas copier les corrigés ou critères privés. Les actions prédéfinies restent nécessaires pour les validations et les ressources à débloquer.",
            ),
          );
          this.choice(p, t, "intake_mode", "Point de départ de l’élève", [
            ["prepared", "Ticket déjà préparé"],
            ["from_request", "Créer un ticket à partir d’une demande"],
          ]);
          this.text(
            p,
            t,
            "raw_request",
            "Message utilisateur brut (obligatoire pour le mode création)",
            true,
          );
          p.append(
            el(
              "p",
              "En mode création, le titre, la description et le demandeur configurés restent des repères pour l’enseignant. L’élève rédige sa propre fiche avant les actions de diagnostic. Ses questions libres ne génèrent pas de réponses automatiques.",
            ),
          );
          this.choice(
            p,
            t,
            "requester_id",
            "Demandeur",
            s.users.map((u) => [u.id, u.label]),
          );
          const visible = this.section(p, "Informations initiales visibles");
          Object.entries(fields).forEach(([k, l]) => {
            if (k === "nature") {
              this.choice(visible, t.fields, k, "Nature de la demande", [
                ["", "Non renseignée"],
                ["À qualifier", "À qualifier"],
                ["incident", "Incident"],
                ["service", "Assistance / service"],
                ["evolution", "Évolution"],
              ]);
            } else {
              this.text(
                visible,
                t.fields,
                k,
                l,
                k === "priority_justification",
              );
            }
          });
          const pedagogy = this.section(p, "Accompagnement pédagogique");
          this.check(
            pedagogy,
            t,
            "guided",
            "Mode guidé : bloquer la résolution si la qualification ou les traces exigées sont incomplètes",
          );
          this.refs(
            pedagogy,
            t,
            "qualification_required",
            "Champs à renseigner avant résolution (mode guidé)",
            [
              { id: "nature", label: "Nature de la demande" },
              { id: "impact", label: "Impact" },
              { id: "urgency", label: "Urgence" },
              { id: "priority", label: "Priorité" },
              {
                id: "priority_justification",
                label: "Justification de priorité",
              },
            ],
          );
          pedagogy.append(
            el(
              "p",
              "Un champ rempli n’est pas nécessairement pertinent. L’appréciation des textes appartient au professeur ; SegFault peut proposer un avis distinct dans le bilan.",
            ),
          );
          t.requester_validation ??= {
            enabled: false,
            replies: [
              {
                outcome: "confirmed",
                message: "Je confirme que ma demande est satisfaite.",
              },
            ],
          };
          this.check(
            pedagogy,
            t.requester_validation,
            "enabled",
            "Exiger la validation simulée du demandeur avant clôture (procédure de ce scénario)",
          );
          t.requester_validation.replies ??= [
            {
              outcome: "confirmed",
              message: "Je confirme que ma demande est satisfaite.",
            },
          ];
          this.collection(
            pedagogy,
            "Réponses successives du demandeur — privées avant réception",
            t.requester_validation.replies,
            () => ({
              outcome: "confirmed",
              message: "Je confirme que ma demande est satisfaite.",
            }),
            (box, reply) => {
              this.choice(box, reply, "outcome", "Décision", [
                ["confirmed", "Confirmation — autoriser la clôture"],
                ["persists", "Le problème persiste — rouvrir"],
              ]);
              this.text(box, reply, "message", "Réponse simulée", true);
            },
          );
          pedagogy.append(
            el(
              "p",
              "Les réponses sont utilisées dans l’ordre, une par résolution. Terminer par une confirmation. Un retour négatif exige une transition resolved → reopened, une nouvelle résolution répétable depuis reopened et des tests relançables. Les réponses futures restent cachées à l’élève.",
            ),
          );
          const expected = this.section(p, "Qualification attendue — privée");
          expected.append(
            el("p", "Les champs laissés vides ne sont pas évalués."),
          );
          Object.entries(fields).forEach(([k, l]) => {
            const n = this.text(expected, t.expected, k, l);
            n.addEventListener("input", () => {
              if (!n.value) delete t.expected[k];
            });
          });
          this.text(
            expected,
            t,
            "expected_solution",
            "Solution attendue et critères de correction",
            true,
          );
          this.refs(
            p,
            t,
            "resources",
            "Ressources associées au ticket",
            s.resources,
          );
          this.refs(
            p,
            t,
            "visible_resources",
            "Ressources visibles dès le début",
            s.resources,
          );
          const tr = this.section(p, "Transitions autorisées");
          Object.entries(statuses).forEach(([key, label]) =>
            this.refs(
              tr,
              t.transitions,
              key,
              "Depuis « " + label + " »",
              Object.entries(statuses)
                .filter(([k]) => k !== key)
                .map(([id, label]) => ({ id, label })),
            ),
          );
          this.collection(
            p,
            "Actions et tests",
            t.actions,
            () => newAction(),
            (ap, a) => {
              this.text(ap, a, "id", "Identifiant");
              this.text(ap, a, "label", "Libellé");
              this.choice(ap, a, "type", "Type", Object.entries(actionTypes));
              this.text(ap, a, "description", "Description visible", true);
              this.text(
                ap,
                a,
                "result",
                "Réponse préparée / sortie par défaut",
                true,
              );
              this.choice(ap, a, "specialist_id", "Spécialiste", [
                ["", "Aucun"],
                ...s.specialists.map((x) => [x.id, x.label]),
              ]);
              this.choice(ap, a, "to_status", "État après l’action", [
                ["", "Conserver"],
                ...Object.entries(statuses),
              ]);
              this.choice(
                ap,
                a,
                "return_status",
                "État après réception de la réponse",
                [["", "Diagnostic par défaut"], ...Object.entries(statuses)],
              );
              this.check(ap, a, "requires_message", "Exiger un message écrit");
              this.check(ap, a, "repeatable", "Action répétable");
              this.choice(
                ap,
                a,
                "code_resource",
                "Pour un test : vérifier l’extrait enregistré",
                [
                  ["", "Aucun — réponses conditionnelles classiques"],
                  ...s.resources
                    .filter((x) => x.editable && t.resources.includes(x.id))
                    .map((x) => [x.id, x.label]),
                ],
              );
              this.text(
                ap,
                a,
                "success_result",
                "Sortie du test si l’extrait correspond à la correction",
                true,
              );
              for (const [k, l] of [
                ["cost", "Coût fictif en minutes"],
                ["score", "Appréciation numérique professeur (facultative)"],
              ]) {
                const input = field(ap, l, a[k] ?? 0);
                input.type = "number";
                input.step = "1";
                input.min = k === "cost" ? "0" : "-10000";
                input.max = "10000";
                input.addEventListener("input", () => {
                  a[k] = Number(input.value);
                });
              }
              this.refs(
                ap,
                a,
                "states",
                "États dans lesquels cette action est proposée (vide = tous)",
                Object.entries(statuses).map(([id, label]) => ({ id, label })),
              );
              this.refs(
                ap,
                a,
                "requires",
                "Actions prérequises (toutes)",
                t.actions.filter((x) => x !== a),
              );
              this.refs(
                ap,
                a,
                "reveal",
                "Ressources révélées",
                s.resources.filter((x) => t.resources.includes(x.id)),
              );
              a.variants ??= [];
              this.collection(
                ap,
                "Réponses conditionnelles — première correspondance retenue",
                a.variants,
                () => ({ requires: [], result: "", outcome: "info" }),
                (vp, v) => {
                  this.refs(
                    vp,
                    v,
                    "requires",
                    "Actions déjà réalisées",
                    t.actions,
                  );
                  this.text(vp, v, "result", "Réponse / sortie du test", true);
                  this.choice(vp, v, "outcome", "Type de résultat", [
                    ["info", "Information"],
                    ["success", "Succès"],
                    ["failure", "Échec"],
                  ]);
                },
              );
            },
          );
          this.refs(
            p,
            t,
            "resolution_requires",
            "Traces attendues pour une résolution correcte",
            t.actions.filter((a) => !["resolve", "close"].includes(a.type)),
          );
          this.refs(
            p,
            t,
            "resolution_tests",
            "Tests devant avoir réussi pour valider la résolution",
            t.actions.filter((a) => a.type === "test"),
          );
          this.choice(
            p,
            t,
            "bad_resolution",
            "Si les traces attendues manquent",
            [
              ["accept", "Accepter et laisser le professeur évaluer"],
              ["reopen", "Réouvrir le ticket"],
            ],
          );
          this.text(
            p,
            t,
            "bad_resolution_message",
            "Réponse négative / nouveau symptôme",
            true,
          );
        },
      );
      if (r.id) {
        const a = this.section(root, "Affectations");
        a.append(
          button(
            "Gérer les affectations enregistrées",
            () => this.assignments(r.id, a),
            root,
          ),
        );
      }
      root.append(
        el(
          "p",
          "Pour les élèves : ajouter [ouinpo_ticket_simulator] à une page, ou utiliser la création de pages de la Suite.",
        ),
      );
      root.querySelectorAll("details").forEach((d) => {
        if (open.has(detailPath(d))) d.open = true;
      });
    }
    async assignments(id, container) {
      const [targets, current, savedScenario] = await Promise.all([
        api("/targets"),
        api(`/scenarios/${id}/assignments`),
        api(`/scenarios/${id}`),
      ]);
      container.replaceChildren(el("summary", "Affectations"));
      if (savedScenario.status !== "published") {
        const notice = el("div", undefined, "ouinpo-ticket-notice");
        notice.append(
          el(
            "p",
            savedScenario.status === "draft"
              ? "Ce scénario est enregistré en brouillon. Les affectations sont conservées, mais les étudiants ne le voient pas encore."
              : "Ce scénario est archivé. Les affectations sont conservées, mais aucun nouveau démarrage n’est possible.",
          ),
        );
        notice.append(
          button(
            "Publier et enregistrer le scénario",
            () => this.saveRecord("published"),
            this.root,
          ),
        );
        container.append(notice);
      } else {
        container.append(
          el(
            "p",
            "Scénario publié : il est visible par les étudiants affectés.",
          ),
        );
      }
      const search = field(container, "Rechercher un étudiant");
      const list = el("div");
      container.append(list);
      const draw = (items) => {
        list.replaceChildren();
        items.forEach((target) => {
          const exists = current.find(
            (a) => a.target_type === target.type && a.target_id === target.id,
          );
          const row = el("div", "", "ouinpo-ticket-admin-row");
          row.append(
            el("span", target.label),
            button(
              exists && Number(exists.active)
                ? "Retirer l’affectation"
                : "Affecter",
              async () => {
                await api(`/scenarios/${id}/assignments`, "POST", {
                  type: target.type,
                  target: target.id,
                  active: !(exists && Number(exists.active)),
                });
                await this.assignments(id, container);
              },
              this.root,
            ),
          );
          list.append(row);
        });
      };
      container.append(
        button(
          "Rechercher",
          async () =>
            draw(
              await api("/targets?search=" + encodeURIComponent(search.value)),
            ),
          this.root,
        ),
      );
      draw(targets);
      const known = new Set(targets.map((t) => t.type + ":" + t.id));
      current
        .filter(
          (a) =>
            !known.has(a.target_type + ":" + a.target_id) && Number(a.active),
        )
        .forEach((a) =>
          container.append(
            button(
              `Retirer ${a.target_type} ${a.target_id}`,
              async () => {
                await api(`/scenarios/${id}/assignments`, "POST", {
                  type: a.target_type,
                  target: a.target_id,
                  active: false,
                });
                await this.assignments(id, container);
              },
              this.root,
            ),
          ),
        );
    }
  }
  document
    .querySelectorAll("[data-ticket-admin]")
    .forEach((root) => new Admin(root));
})();
