// HTTP smoke test of the isolated PHP fixture. Does not replace WordPress/MySQL QA.
import { spawn } from "node:child_process";
import { createServer } from "node:net";
import { fileURLToPath } from "node:url";
import assert from "node:assert/strict";
const root = fileURLToPath(new URL("../", import.meta.url));
const probe = createServer();
await new Promise((resolve) => probe.listen(0, "127.0.0.1", resolve));
const port = probe.address().port;
await new Promise((resolve) => probe.close(resolve));
const child = spawn(
  "php",
  ["-S", `127.0.0.1:${port}`, "tools/ticket-simulator-preview.php"],
  { cwd: root, windowsHide: true, stdio: "ignore" },
);
let serverError;
child.on("error", (error) => {
  serverError = error;
});
let cookie = "";
const base = `http://127.0.0.1:${port}`;
async function request(path, method = "GET", body, status = 200) {
  const response = await fetch(base + path, {
    method,
    headers: { Cookie: cookie, "Content-Type": "application/json" },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });
  cookie = response.headers.get("set-cookie")?.split(";")[0] || cookie;
  assert.equal(response.status, status, `${method} ${path}`);
  const text = await response.text();
  if (!response.headers.get("content-type")?.includes("application/json"))
    return text;
  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`${method} ${path}: ${text.slice(0, 1500)}`);
  }
}
try {
  let ready = false;
  for (let i = 0; i < 50; i++) {
    if (serverError) throw serverError;
    try {
      await request("/");
      ready = true;
      break;
    } catch {
      await new Promise((resolve) => setTimeout(resolve, 100));
    }
  }
  assert.ok(ready, "Fixture starts");
  for (const path of [
    "/assets/js/front/ticket-simulator.js",
    "/assets/js/admin/ticket-simulator-admin.js",
    "/assets/css/front/ticket-simulator.css",
    "/assets/css/admin/ticket-simulator-admin.css",
  ]) {
    assert.ok((await request(path)).length > 100, `Asset served: ${path}`);
  }
  const assignments = await request("/api/assignments");
  assert.equal(assignments.length, 1);
  await request("/api/assignments/1/attempts", "POST", {});
  let attempt = await request("/api/attempts/1");
  assert.ok(
    !JSON.stringify(attempt).includes("date_creation"),
    "Fresh payload hides evidence",
  );
  const ticket = "/api/attempts/1/tickets/INC-0001";
  await request(
    ticket + "/resources/code",
    "POST",
    { revision: attempt.revision },
    400,
  );
  async function action(id, input = {}) {
    attempt = await request(ticket + "/actions/" + id, "POST", {
      ...input,
      revision: attempt.revision,
    });
  }
  await action("take");
  await action("diagnose");
  await action("since");
  assert.equal(attempt.tickets[0].status, "waiting_user");
  await action("__reply");
  await action("logs");
  await action("code");
  const resource = await request(ticket + "/resources/code", "POST", {
    revision: attempt.revision,
  });
  attempt = resource.attempt;
  assert.ok(resource.resource.content.includes("date_creation"));
  await request(
    ticket + "/actions/dba",
    "POST",
    { revision: attempt.revision },
    400,
  );
  await action("dba", { message: "Le schéma a-t-il changé ?" });
  await action("__reply");
  attempt = await request(ticket + "/notes", "POST", {
    revision: attempt.revision,
    message: "Renommage de colonne confirmé.",
  });
  attempt = await request(ticket + "/resources/code/code", "PATCH", {
    revision: attempt.revision,
    content: resource.resource.content.replaceAll(
      "date_creation",
      "created_at",
    ),
  });
  await action("verify");
  assert.equal(attempt.tickets[0].tests.verify.outcome, "success");
  await action("resolve", {
    cause: "Migration",
    solution: "created_at",
    tests: "Export",
    result: "128 lignes",
    message: "Incident résolu.",
  });
  await action("close");
  assert.equal(attempt.tickets[0].status, "closed");
  assert.ok(attempt.events.some((e) => e.event_type === "technical_note"));
  assert.ok(attempt.events.some((e) => e.event_type === "specialist_reply"));
  await request("/?admin=1");
  const observed = await request("/api/attempts/1");
  assert.ok(observed.read_only && observed.tickets[0].teacher);
  const scenario = await request("/api/scenarios/1");
  await request("/api/scenarios/1", "PATCH", {
    definition: scenario.definition,
    status: "published",
  });
  await request("/src/Modules/TicketSimulator/demo.php", "GET", undefined, 404);
  console.log(
    "HTTP fixture OK: assets, full workflow, code, messages, notes, tests, resolution and teacher view.",
  );
} finally {
  const stopped = new Promise((resolve) => child.once("close", resolve));
  child.kill();
  await stopped;
}
