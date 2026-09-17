import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
const window = { OuinpoTicketing: {} };
vm.runInNewContext(
  fs.readFileSync(
    new URL("../assets/js/front/ticket-simulator.js", import.meta.url),
    "utf8",
  ),
  { window, document: { querySelectorAll: () => [] } },
);
const Desk = window.OuinpoTicketUI.Desk;
function desk(id = 1, ticket = "A") {
  const d = Object.create(Desk.prototype);
  d.a = { id, student_id: 11 };
  d.ticketId = ticket;
  return d;
}
function input(value = "") {
  const handlers = {};
  return {
    value,
    addEventListener: (event, callback) => {
      handlers[event] = callback;
    },
    type(text) {
      this.value = text;
      handlers.input();
    },
  };
}
const d = desk();
const original = d.remember(input(), "action:resolve:cause");
original.type("Analyse en cours");
assert.equal(
  d.remember(input(), "action:resolve:cause").value,
  "Analyse en cours",
);
assert.equal(
  desk().remember(input(), "action:resolve:cause").value,
  "Analyse en cours",
  "Returning to attempt retains draft",
);
assert.equal(
  desk(1, "B").remember(input(), "action:resolve:cause").value,
  "",
  "Tickets are isolated",
);
assert.equal(
  desk(2).remember(input(), "action:resolve:cause").value,
  "",
  "Attempts are isolated",
);
assert.equal(
  d.remember(input(), "action:specialist:message").value,
  "",
  "Actions are isolated",
);
d.clearSubmitted(
  "action:resolve:",
  { cause: "Earlier version" },
  d.draftKey(""),
);
assert.equal(
  d.remember(input(), "action:resolve:cause").value,
  "Analyse en cours",
  "New typing during save is preserved",
);
d.clearSubmitted(
  "action:resolve:",
  { cause: "Analyse en cours" },
  d.draftKey(""),
);
assert.equal(
  d.remember(input(), "action:resolve:cause").value,
  "",
  "Submitted draft is cleared",
);
for (const key of ["notes:message", "qualify:priority", "code:source"]) {
  const field = d.remember(input(), key);
  field.type("Brouillon");
  assert.equal(d.remember(input(), key).value, "Brouillon");
}
console.log(
  "Draft checks OK: restoration, ticket/attempt/action isolation and submitted-value cleanup.",
);
