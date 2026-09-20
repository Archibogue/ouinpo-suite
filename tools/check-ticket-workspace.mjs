import fs from "node:fs";
import vm from "node:vm";
import assert from "node:assert/strict";
class Element {
  constructor(tag) {
    this.tag = tag;
    this.children = [];
    this.dataset = {};
    this.classList = { add() {}, remove() {} };
  }
  append(...children) {
    this.children.push(...children);
  }
  prepend(...children) {
    this.children.unshift(...children);
  }
  replaceChildren(...children) {
    this.children = children;
  }
  setAttribute() {}
  addEventListener() {}
}
const window = { OuinpoTicketing: {} };
vm.runInNewContext(
  fs.readFileSync(
    new URL("../assets/js/front/ticket-simulator.js", import.meta.url),
    "utf8",
  ),
  {
    window,
    document: {
      querySelectorAll: () => [],
      createElement: (tag) => new Element(tag),
    },
  },
);
const flatten = (n) => [n, ...n.children.flatMap(flatten)];
for (const read_only of [false, true]) {
  const root = new Element("div");
  new window.OuinpoTicketUI.Desk(
    root,
    {
      id: 1,
      student_id: 11,
      events: [],
      read_only,
      tickets: [
        {
          id: "T",
          title: "Ticket",
          status: "diagnosing",
          fields: {},
          done: [],
          resources: [],
          tests: {},
          actions: [
            { id: "test", type: "test", label: "Tester la correction" },
            {
              id: "ask",
              type: "specialist",
              label: "Consulter le spécialiste",
            },
          ],
          ai_recipients: [{ id: "requester", label: "Demandeur" }],
        },
      ],
    },
    () => {},
  );
  const nodes = flatten(root);
  for (const text of [
    "Traiter le ticket",
    "Conversation",
    "Tester la correction",
    "Consulter le spécialiste",
  ])
    assert(
      nodes.some((n) => n.textContent === text),
      text,
    );
  assert(!nodes.some((n) => n.tag === "nav"), "No panel navigation required");
  for (const text of ["Ressources", "Notes", "Historique"])
    assert(
      nodes.some((n) => n.tag === "button" && n.textContent === text),
      text,
    );
}
console.log(
  "Unified workspace renders actions, tests, conversation and expandable supports in editable and read-only modes.",
);
