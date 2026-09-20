import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const pending = [];
const requests = [];
const document = {
  body: {}, activeElement: null, addEventListener() {},
  createElement() { return { set textContent(value) { this.innerHTML = String(value); } }; }
};
const window = { location: { origin: 'https://example.test' }, OUINPO_FLASHCARDS: { api: 'https://example.test/wp-json/flashcards', nonce: 'test' } };
const source = fs.readFileSync(new URL('../assets/js/front/flashcards.js', import.meta.url), 'utf8');
vm.runInNewContext(source.replace(/\}\)\(\);\s*$/, 'window.testGrade = grade; })();'), {
  window, document, URL, URLSearchParams, console: { error() {} },
  fetch(url, options) {
    requests.push({ url, options });
    return new Promise((resolve, reject) => pending.push({
      resolve: data => resolve({ ok: true, json: async () => data }), reject
    }));
  }
});

function fixture() {
  const nodes = new Map();
  function node(selector) {
    if (!nodes.has(selector)) nodes.set(selector, {
      disabled: false, hidden: false, value: '', attributes: {}, options: [], selectedIndex: 0,
      classList: { add() {}, remove() {} },
      setAttribute(name, value) { this.attributes[name] = value; },
      focus() { document.activeElement = this; }
    });
    return nodes.get(selector);
  }
  const controls = ['.ouinpo-fc-grade', '.ouinpo-fc-reveal', '.ouinpo-fc-domain-select', '.ouinpo-fc-edit-selection'].map(node);
  const app = {
    dataset: { cardId: '17' },
    querySelector(selector) { return selector === '.ouinpo-fc-decks' ? null : node(selector); },
    querySelectorAll(selector) { return selector === 'button, input, select' ? controls : []; }
  };
  document.activeElement = controls[0];
  return { app, node, controls };
}
const tick = () => new Promise(resolve => setImmediate(resolve));
const result = { card: { id: 18, front_html: 'Suivante', back_html: 'Réponse' }, review: { new_box: 2, next_review_at: '2026-09-21' } };

const a = fixture();
a.controls[2].disabled = true;
const saving = window.testGrade(a.app, 'good');
assert(a.controls.every(control => control.disabled));
assert.equal(a.node('.ouinpo-fc-session').attributes['aria-busy'], 'true');
assert.match(a.node('.ouinpo-fc-feedback').textContent, /Enregistrement/);
await window.testGrade(a.app, 'hard');
assert.equal(requests.length, 1, 'Concurrent decisions send only one grade');
assert.equal(JSON.parse(requests[0].options.body).card_id, 17);
pending.shift().resolve(result);
await tick();
assert.equal(a.app.dataset.cardId, '18', 'Next card appears before the secondary refresh completes');
assert.equal(requests.length, 2);
pending.shift().reject(new Error('Indicators offline'));
await saving;
assert.match(a.node('.ouinpo-fc-feedback').textContent, /Carte enregistrée.*indicateurs/);
assert.equal(a.controls[0].disabled, false);
assert.equal(a.controls[2].disabled, true, 'Previously disabled controls remain disabled');
assert.equal(document.activeElement, a.node('.ouinpo-fc-reveal'));
assert.equal(a.node('.ouinpo-fc-session').attributes['aria-busy'], 'false');

const b = fixture();
const failed = window.testGrade(b.app, 'again');
const failureCheck = assert.rejects(failed, /Offline/);
pending.shift().reject(new Error('Offline'));
await failureCheck;
assert.equal(b.app.dataset.cardId, '17', 'Failure preserves the current card');
assert(b.controls.every(control => !control.disabled), 'Failure unlocks controls for retry');
const retry = window.testGrade(b.app, 'good');
pending.shift().resolve({ card: null, review: {} });
await tick();
pending.shift().resolve({ decks: [] });
await retry;
assert.equal(b.app.dataset.cardId, '');
assert.equal(document.activeElement, b.node('.ouinpo-fc-edit-selection'), 'End of session focuses a visible control');
console.log('Flashcards: concurrent clicks, saved grade with failed refresh, retry, busy state and focus passed.');
