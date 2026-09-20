import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const nodes = new Map();
const root = {
  getAttribute: key => key === 'data-initial-text' ? 'ABA' : 'A',
  querySelector(selector) {
    if (!nodes.has(selector)) nodes.set(selector, {
      value: selector === '.js-rt-text' ? 'ABA' : 'A',
      disabled: false, handlers: {},
      addEventListener(type, handler) { this.handlers[type] = handler; }
    });
    return nodes.get(selector);
  }
};
let tick = null;
vm.runInNewContext(fs.readFileSync(new URL('../assets/js/front/rechtext.js', import.meta.url), 'utf8'), {
  document: { addEventListener(type, callback) { callback(); }, querySelectorAll() { return [root]; } },
  setInterval(callback) { tick = callback; return 1; },
  clearInterval() { tick = null; }
});
const node = name => nodes.get('.js-rt-' + name);
assert.equal(node('prev').disabled, true);
assert.equal(node('next').disabled, false);
node('play').handlers.click();
for (let i = 0; tick && i < 100; i++) tick();
assert.equal(tick, null, 'Playback stops immediately at the last frame');
assert.equal(node('next').disabled, true);
assert.equal(node('play').disabled, true);
assert.equal(node('prev').disabled, false);
node('prev').handlers.click();
assert.equal(node('next').disabled, false);
assert.equal(node('play').disabled, false);
node('reset').handlers.click();
assert.equal(node('prev').disabled, true);
assert.equal(node('next').disabled, false);
console.log('RechText: boundaries, autoplay completion, step back and reset passed.');
