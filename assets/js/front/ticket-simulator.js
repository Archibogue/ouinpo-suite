/* PataDesk: vanilla JS, all teaching content is rendered as text. */
(() => {
  "use strict";
  const cfg = window.OuinpoTicketing;
  if (!cfg) return;
  const statuses = {
    new: "Nouveau",
    accepted: "Pris en charge",
    diagnosing: "En diagnostic",
    waiting_user: "En attente utilisateur",
    waiting_specialist: "En attente spécialiste",
    escalated: "Escaladé",
    resolving: "En cours de résolution",
    resolved: "Résolu",
    closed: "Clôturé",
    reopened: "Réouvert",
  };
  const types = {
    action: "Action",
    created: "Réception",
    status: "Statut",
    qualification: "Qualification",
    user_message: "Vous → demandeur",
    user_reply: "Demandeur",
    specialist_request: "Vous → spécialiste",
    specialist_reply: "Spécialiste",
    technical_note: "Note technique privée",
    test: "Test simulé",
    result: "Résultat",
    resource: "Consultation",
    code_edit: "Code modifié par l’élève",
    resolution: "Compte rendu",
    archived: "Archivage",
  };
  function el(tag, text, cls) {
    const n = document.createElement(tag);
    if (text !== undefined) n.textContent = text;
    if (cls) n.className = cls;
    return n;
  }
  async function api(path, method = "GET", body) {
    const res = await fetch(cfg.root + path, {
      method,
      credentials: "same-origin",
      headers: { "X-WP-Nonce": cfg.nonce, "Content-Type": "application/json" },
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || "Action impossible.");
    return data;
  }
  function error(root, e) {
    let box = root.querySelector(".ouinpo-ticket-error");
    if (!box) {
      box = el("p", "", "ouinpo-ticket-error");
      box.setAttribute("role", "alert");
      root.prepend(box);
    }
    box.textContent = e.message || String(e);
    box.scrollIntoView({ block: "nearest" });
  }
  function button(text, fn, root, cls = "") {
    const b = el("button", text, cls);
    b.type = "button";
    b.addEventListener("click", async () => {
      b.disabled = true;
      try {
        await fn();
      } catch (e) {
        error(root || b.parentNode, e);
      } finally {
        b.disabled = false;
      }
    });
    return b;
  }
  function field(parent, label, value = "", area = false) {
    const wrap = el("label", label);
    const input = el(area ? "textarea" : "input");
    input.value = value;
    if (area) input.rows = 4;
    else input.type = "text";
    input.maxLength = 10000;
    wrap.append(input);
    parent.append(wrap);
    return input;
  }
  function select(parent, label, options, value, change) {
    const wrap = el("label", label);
    const input = el("select");
    options.forEach(([id, title]) => {
      const opt = el("option", title);
      opt.value = id;
      input.append(opt);
    });
    input.value = value;
    if (change) input.addEventListener("change", () => change(input.value));
    wrap.append(input);
    parent.append(wrap);
    return input;
  }
  class Desk {
    constructor(root, attempt, back) {
      this.root = root;
      this.a = attempt;
      this.back = back;
      this.ticketId = attempt.tickets[0]?.id;
      this.tab = "Conversation";
      this.filters = {};
      this.eventsComplete = attempt.events.length < 500;
      this.render();
    }
    async refresh() {
      this.a = await api("/attempts/" + this.a.id);
      this.eventsComplete = this.a.events.length < 500;
      this.render();
    }
    async mutate(path, method, body) {
      const previous = this.a.events.at(-1)?.id || 0;
      const loadedEvents = this.a.events;
      this.a = await api(
        `/attempts/${this.a.id}/tickets/${this.ticketId}${path}`,
        method,
        { ...body, revision: Number(this.a.revision) },
      );
      if (loadedEvents.length >= 500) {
        const more = await api(
          `/attempts/${this.a.id}/events?after=${previous}`,
        );
        this.a.events = loadedEvents.concat(more);
        this.eventsComplete = more.length < 500;
      } else {
        this.eventsComplete = this.a.events.length < 500;
      }
      this.render();
      const results = this.a.events.filter(
        (e) =>
          Number(e.id) > Number(previous) &&
          ["result", "test", "user_reply", "specialist_reply"].includes(
            e.event_type,
          ),
      );
      if (results.length) {
        const output = el("div", "", "ouinpo-ticket-notice");
        output.setAttribute("role", "status");
        results.forEach((e) =>
          output.append(
            el("strong", types[e.event_type]),
            el("pre", e.payload.text),
          ),
        );
        this.root.querySelector(".ouinpo-ticket-panel").prepend(output);
      }
    }
    render() {
      const root = this.root;
      root.replaceChildren();
      const header = el("header", "", "ouinpo-ticket-header");
      header.append(
        el("h2", cfg.name),
        el(
          "span",
          this.a.teacher_view
            ? "Observation professeur"
            : "Centre de services — simulation",
        ),
      );
      header.append(
        button("Retour", this.back, root),
        button("Actualiser", () => this.refresh(), root),
      );
      root.append(header);
      if (!this.a.teacher_view) root.append(studentGuide());
      root.append(el("h3", this.a.title), el("p", this.a.description));
      if (this.a.read_only)
        root.append(
          el("p", "Consultation en lecture seule.", "ouinpo-ticket-notice"),
        );
      const stats = el("div", "", "ouinpo-ticket-stats");
      const counts = [
        ["Disponibles", (s) => s === "new"],
        [
          "En cours",
          (s) =>
            ["accepted", "diagnosing", "resolving", "reopened"].includes(s),
        ],
        ["En attente", (s) => s.startsWith("waiting")],
        ["Escaladés", (s) => s === "escalated"],
        ["Résolus", (s) => s === "resolved"],
        ["Clôturés", (s) => s === "closed"],
      ];
      counts.forEach(([label, test]) =>
        stats.append(
          el(
            "div",
            `${this.a.tickets.filter((t) => test(t.status)).length} · ${label}`,
          ),
        ),
      );
      root.append(stats);
      const layout = el("div", "", "ouinpo-ticket-layout");
      const queue = el("aside", "", "ouinpo-ticket-queue");
      const main = el("main", "", "ouinpo-ticket");
      layout.append(queue, main);
      root.append(layout);
      queue.append(el("h3", "File de tickets"));
      for (const [key, label] of [
        ["status", "Statut"],
        ["priority", "Priorité"],
        ["category", "Catégorie"],
        ["application", "Application / service"],
      ]) {
        const val = (t) =>
          key === "status"
            ? t.status
            : t.fields[key] ||
              (key === "application" ? t.fields.service : "") ||
              "";
        const options = [...new Set(this.a.tickets.map(val).filter(Boolean))];
        select(
          queue,
          label,
          [
            ["", "Tous"],
            ...options.map((v) => [v, key === "status" ? statuses[v] : v]),
          ],
          this.filters[key] || "",
          (v) => {
            this.filters[key] = v;
            this.render();
          },
        );
      }
      const tickets = this.a.tickets.filter((t) =>
        Object.entries(this.filters).every(
          ([k, v]) =>
            !v ||
            (k === "status"
              ? t.status
              : t.fields[k] ||
                (k === "application" ? t.fields.service : "") ||
                "") === v,
        ),
      );
      tickets.forEach((t) => {
        const b = button(
          `${t.id} · ${t.title}\n${statuses[t.status]} · ${t.fields.priority || "À qualifier"}${t.fields.sla ? " · SLA " + t.fields.sla : ""}`,
          () => {
            this.ticketId = t.id;
            this.render();
          },
          root,
          "ouinpo-ticket-card",
        );
        b.setAttribute("aria-pressed", String(t.id === this.ticketId));
        queue.append(b);
      });
      if (!tickets.length)
        queue.append(el("p", "Aucun ticket pour ces filtres."));
      const t = this.a.tickets.find((x) => x.id === this.ticketId);
      if (!t) return;
      main.append(
        el("span", `${t.id} · ${statuses[t.status]}`, "ouinpo-ticket-status"),
        el("h3", t.title),
        el("p", `${t.requester} — ${t.fields.service || ""}`),
        el("p", t.description),
      );
      const facts = el("dl", "", "ouinpo-ticket-facts");
      Object.entries(t.fields).forEach(([k, v]) => {
        facts.append(
          el(
            "dt",
            {
              priority: "Priorité",
              category: "Catégorie",
              application: "Application",
              sla: "SLA fictif",
              assignee: "Assignation",
              impact: "Impact",
              urgency: "Urgence",
              location: "Localisation",
              fictional_date: "Date fictive",
              it_service: "Service informatique",
              service: "Service",
              subcategory: "Sous-catégorie",
            }[k] || k,
          ),
          el("dd", v),
        );
      });
      main.append(facts);
      main.append(
        el(
          "p",
          `${t.done.length} action(s) distincte(s) · ${t.minutes} min fictives · Début : ${this.a.started_at} UTC${this.a.ended_at ? " · Fin : " + this.a.ended_at + " UTC" : ""}`,
        ),
      );
      if (!this.a.read_only) {
        const details = el("details");
        details.append(el("summary", "Qualifier / affecter le ticket"));
        const form = el("form");
        const inputs = {};
        for (const [k, l] of [
          ["category", "Catégorie"],
          ["subcategory", "Sous-catégorie"],
          ["impact", "Impact"],
          ["urgency", "Urgence"],
          ["priority", "Priorité"],
          ["it_service", "Service informatique"],
          ["assignee", "Technicien / groupe fictif"],
        ])
          inputs[k] = field(form, l, t.fields[k] || "");
        form.append(
          button(
            "Enregistrer la qualification",
            () =>
              this.mutate(
                "",
                "PATCH",
                Object.fromEntries(
                  Object.entries(inputs).map(([k, v]) => [k, v.value]),
                ),
              ),
            root,
          ),
        );
        details.append(form);
        main.append(details);
      }
      const nav = el("nav", "", "ouinpo-ticket-tabs");
      nav.setAttribute("aria-label", "Panneaux du ticket");
      [
        "Conversation",
        "Actions",
        "Tests",
        "Ressources",
        "Spécialistes",
        "Historique",
        "Notes",
      ].forEach((tab) => {
        const b = button(
          tab,
          () => {
            this.tab = tab;
            this.render();
          },
          root,
        );
        b.setAttribute("aria-pressed", String(this.tab === tab));
        nav.append(b);
      });
      main.append(nav);
      const panel = el("section", "", "ouinpo-ticket-panel");
      panel.append(el("h4", this.tab));
      main.append(panel);
      if (["Conversation", "Historique", "Notes"].includes(this.tab)) {
        const allowed =
          this.tab === "Conversation"
            ? [
                "user_message",
                "user_reply",
                "specialist_request",
                "specialist_reply",
              ]
            : this.tab === "Notes"
              ? ["technical_note"]
              : null;
        const events = this.a.events.filter(
          (e) =>
            e.ticket_key === t.id &&
            (!allowed || allowed.includes(e.event_type)),
        );
        if (!events.length)
          panel.append(
            el(
              "p",
              this.tab === "Conversation"
                ? t.description
                : "Aucune trace pour le moment.",
            ),
          );
        const list = el("ol", "", "ouinpo-ticket-timeline");
        events.forEach((e) => {
          const item = el("li");
          item.append(
            el(
              "strong",
              `${e.created_at} UTC · ${types[e.event_type] || e.event_type}`,
            ),
            el("pre", e.payload.text || ""),
          );
          list.append(item);
          if (e.event_type === "code_edit")
            item.append(el("pre", e.payload.content));
        });
        panel.append(list);
        if (!this.eventsComplete)
          panel.append(
            button(
              "Charger la suite du journal",
              async () => {
                const more = await api(
                  `/attempts/${this.a.id}/events?after=${this.a.events.at(-1).id}`,
                );
                this.a.events.push(...more);
                this.eventsComplete = more.length < 500;
                this.render();
              },
              root,
            ),
          );
        if (this.tab === "Notes" && !this.a.read_only) {
          const note = field(panel, "Note technique privée", "", true);
          panel.append(
            button(
              "Ajouter la note",
              () => this.mutate("/notes", "POST", { message: note.value }),
              root,
            ),
          );
        }
      } else if (this.tab === "Ressources") {
        t.resources.forEach((r) =>
          panel.append(
            button(
              `${r.label} · ${r.filename || r.type}`,
              async () => {
                const data = await api(
                  `/attempts/${this.a.id}/tickets/${t.id}/resources/${r.id}`,
                  "POST",
                  { revision: Number(this.a.revision) },
                );
                this.a = data.attempt;
                this.render();
                this.showResource(data.resource);
              },
              root,
            ),
          ),
        );
        if (!t.resources.length)
          panel.append(
            el(
              "p",
              "Aucune ressource révélée. Les investigations peuvent débloquer des éléments techniques.",
            ),
          );
      } else {
        const matches = (a) =>
          this.tab === "Tests"
            ? a.type === "test"
            : this.tab === "Spécialistes"
              ? [
                  "specialist",
                  "transfer",
                  "escalate",
                  "reassign",
                  "reply",
                ].includes(a.type)
              : a.type !== "test";
        t.actions.filter(matches).forEach((a) => {
          const card = el("div", "", "ouinpo-ticket-action");
          card.append(el("h4", a.label), el("p", a.description || ""));
          if (a.specialist)
            card.append(el("p", "Destinataire : " + a.specialist));
          if (a.cost) card.append(el("small", `Coût fictif : ${a.cost} min`));
          const inputs = {};
          if (a.type === "resolve") {
            for (const [k, l] of [
              ["cause", "Cause identifiée"],
              ["solution", "Solution appliquée"],
              ["tests", "Tests effectués"],
              ["result", "Résultat"],
              ["message", "Message final au demandeur"],
            ])
              inputs[k] = field(card, l, "", true);
          } else if (
            a.requires_message ||
            [
              "specialist",
              "transfer",
              "escalate",
              "reassign",
              "communication",
            ].includes(a.type)
          )
            inputs.message = field(card, "Votre message", "", true);
          if (!this.a.read_only)
            card.append(
              button(
                a.type === "resolve"
                  ? "Envoyer le compte rendu et résoudre"
                  : "Effectuer cette action",
                () =>
                  this.mutate(
                    "/actions/" + a.id,
                    "POST",
                    Object.fromEntries(
                      Object.entries(inputs).map(([k, v]) => [k, v.value]),
                    ),
                  ),
                root,
              ),
            );
          panel.append(card);
        });
        if (this.tab === "Tests")
          Object.entries(t.tests).forEach(([id, r]) => {
            panel.append(el("h4", id + " · " + r.outcome), el("pre", r.text));
          });
      }
      if (t.resolution) {
        const d = el("details");
        d.append(el("summary", "Dernier compte rendu"));
        Object.entries(t.resolution).forEach(([k, v]) =>
          d.append(el("h4", k), el("p", v)),
        );
        main.append(d);
      }
      if (t.teacher) {
        const d = el("details");
        d.append(
          el("summary", "Analyse professeur — informations privées"),
          el("pre", JSON.stringify(t.teacher, null, 2)),
          el(
            "p",
            "Les critères automatiques portent sur les traces. La qualité du texte reste à apprécier par le professeur.",
          ),
        );
        main.append(d);
      }
    }
    showResource(r) {
      const panel = this.root.querySelector(".ouinpo-ticket-panel");
      const wrap = el("div", "", "ouinpo-ticket-resource");
      wrap.append(
        el("h4", `${r.filename || r.label} · ${r.language || "texte"}`),
      );
      wrap.append(
        button(
          "Copier le contenu",
          async () => {
            await navigator.clipboard.writeText(r.content || "");
            const msg = el("p", "Contenu copié.");
            msg.setAttribute("role", "status");
            wrap.append(msg);
          },
          this.root,
        ),
      );
      const lines = el("ol");
      (r.content || "").split("\n").forEach((line) => {
        const li = el("li");
        li.append(el("code", line || " "));
        lines.append(li);
      });
      wrap.append(lines);
      const ticket = this.a.tickets.find((t) => t.id === this.ticketId);
      if (
        r.editable &&
        !this.a.read_only &&
        !["new", "resolved", "closed"].includes(ticket.status) &&
        !ticket.actions.some((a) => a.id === "__reply")
      ) {
        const consolePanel = el("details", undefined, "ouinpo-ticket-console");
        consolePanel.append(el("summary", "Ouvrir la console de correction"));
        consolePanel.append(
          el(
            "p",
            "Modifiez votre copie de l’extrait, enregistrez, puis relancez le test dans l’onglet Tests.",
          ),
        );
        const editor = field(
          consolePanel,
          "Votre code — " + (r.filename || r.label),
          r.content || "",
          true,
        );
        editor.rows = 18;
        editor.maxLength = 100000;
        editor.spellcheck = false;
        editor.wrap = "off";
        editor.setAttribute("autocapitalize", "off");
        const saved = el("p", "Copie enregistrée.");
        saved.setAttribute("role", "status");
        editor.addEventListener("input", () => {
          saved.textContent = "Modifications non enregistrées.";
        });
        consolePanel.append(
          button(
            "Enregistrer mon code",
            async () => {
              const content = editor.value;
              this.a = await api(
                `/attempts/${this.a.id}/tickets/${this.ticketId}/resources/${r.id}/code`,
                "PATCH",
                { content, revision: Number(this.a.revision) },
              );
              this.render();
              this.showResource({ ...r, content });
              const newConsole = this.root.querySelector(
                ".ouinpo-ticket-console",
              );
              if (newConsole) newConsole.open = true;
            },
            this.root,
          ),
          saved,
        );
        wrap.append(consolePanel);
      }
      panel.append(wrap);
    }
  }
  function studentGuide() {
    const guide = el("details", undefined, "ouinpo-ticket-guide");
    guide.append(
      el("summary", "Guide d’utilisation — comment traiter un ticket ?"),
    );
    guide.append(
      el(
        "p",
        "Vous jouez le rôle d’un technicien de support. Chaque scénario propose ses propres actions : les informations se découvrent au fil de vos investigations.",
      ),
    );
    const steps = el("ol");
    [
      [
        "Ouvrir un scénario",
        "Depuis les scénarios affectés, cliquez sur Ouvrir, puis choisissez un ticket dans la file. Vous pouvez retrouver votre travail dans Mes tentatives.",
      ],
      [
        "Prendre en charge et qualifier",
        "Dans Actions, prenez en charge le ticket puis commencez le diagnostic. Lisez la demande et renseignez la catégorie, l’impact, l’urgence et la priorité dans Qualifier / affecter le ticket.",
      ],
      [
        "Interroger et investiguer",
        "Posez les questions proposées et consultez les éléments disponibles. Après une demande, utilisez Recevoir la réponse et reprendre. Certaines actions débloquent des ressources supplémentaires.",
      ],
      [
        "Lire et corriger le code",
        "Dans Ressources, ouvrez le fichier révélé. Si le professeur l’autorise, ouvrez la console de correction, modifiez l’extrait puis cliquez sur Enregistrer mon code. Enregistrez avant de changer d’onglet. Votre copie est personnelle.",
      ],
      [
        "Tester",
        "Dans Tests, lancez les vérifications proposées et lisez leur résultat. Après chaque modification du code, enregistrez puis relancez le test. Il s’agit de tests pédagogiques simulés.",
      ],
      [
        "Contacter un spécialiste",
        "Dans Spécialistes, choisissez une action adaptée et rédigez une demande précise : symptômes, investigations et question. Recevez ensuite sa réponse pour reprendre le traitement.",
      ],
      [
        "Documenter",
        "Dans Notes, enregistrez vos observations techniques. Conversation rassemble les échanges ; Historique conserve les actions, tests et versions de code enregistrées pour vous et votre professeur.",
      ],
      [
        "Résoudre et clôturer",
        "Dans Actions, choisissez la résolution et complétez la cause, la solution, les tests, le résultat et le message final au demandeur. Envoyez le compte rendu, puis clôturez si cette action est proposée. Une résolution insuffisante peut provoquer une réouverture.",
      ],
    ].forEach(([title, text]) => {
      const item = el("li");
      item.append(el("strong", title), el("p", text));
      steps.append(item);
    });
    guide.append(steps);
    guide.append(
      el(
        "p",
        "Aucun scénario affiché ? Vérifiez votre compte et demandez au professeur de vérifier la publication et l’affectation. Une tentative archivée se consulte en lecture seule. Si un autre onglet a modifié la tentative, actualisez avant de réessayer.",
      ),
    );
    return guide;
  }
  async function home(root) {
    root.replaceChildren(el("h2", cfg.name), studentGuide());
    try {
      const [assignments, attempts] = await Promise.all([
        api("/assignments"),
        api("/attempts"),
      ]);
      root.replaceChildren(
        el("h2", cfg.name),
        el("p", "Recevoir · qualifier · investiguer · communiquer · résoudre"),
        studentGuide(),
      );
      const filter = field(root, "Filtrer par scénario");
      const list = el("div");
      root.append(list);
      const draw = () => {
        list.replaceChildren(el("h3", "Scénarios affectés"));
        assignments
          .filter((a) =>
            a.title.toLowerCase().includes(filter.value.toLowerCase()),
          )
          .forEach((a) =>
            list.append(
              button(
                a.title + " — Ouvrir",
                async () => {
                  const attempt = await api(
                    `/assignments/${a.id}/attempts`,
                    "POST",
                    {},
                  );
                  new Desk(root, await api("/attempts/" + attempt.id), () =>
                    home(root),
                  );
                },
                root,
              ),
            ),
          );
        list.append(el("h3", "Mes tentatives"));
        attempts.forEach((a) => {
          const assignment = assignments.find(
            (x) => Number(x.scenario_id) === Number(a.scenario_id),
          );
          const title = assignment?.title || `Scénario #${a.scenario_id}`;
          if (!title.toLowerCase().includes(filter.value.toLowerCase())) return;
          list.append(
            button(
              `${title} · tentative #${a.id} · ${a.status === "archived" ? "Archivée" : a.status === "completed" ? "Terminée" : "En cours"}`,
              async () =>
                new Desk(root, await api("/attempts/" + a.id), () =>
                  home(root),
                ),
              root,
            ),
          );
        });
        if (!assignments.length && !attempts.length)
          list.append(
            el(
              "p",
              "Votre professeur ne vous a pas encore affecté de scénario.",
            ),
          );
      };
      filter.addEventListener("input", draw);
      draw();
    } catch (e) {
      error(root, e);
    }
  }
  window.OuinpoTicketUI = {
    api,
    el,
    button,
    field,
    select,
    error,
    Desk,
    statuses,
  };
  document.querySelectorAll("[data-ticket-student]").forEach(home);
})();
