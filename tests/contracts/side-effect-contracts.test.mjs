import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import {
  compareSideEffects,
  validateSideEffectContract,
} from '../../scripts/side-effect-contracts.mjs';

const fixture = JSON.parse(
  readFileSync('contracts/side-effects/message-created.json', 'utf8'),
);

test('validates a scenario containing every side-effect category', () => {
  assert.deepEqual(validateSideEffectContract(fixture), []);
});

for (const category of ['jobs', 'mail', 'webhooks', 'realtime']) {
  test(`rejects a contract without ${category}`, () => {
    const invalid = structuredClone(fixture);
    invalid.effects[category] = [];
    assert.match(
      validateSideEffectContract(invalid).join('\n'),
      new RegExp(`effects\\.${category}`),
    );
  });
}

test('compares category entries independent of capture order', () => {
  const actual = structuredClone(fixture);
  actual.effects.jobs.push({
    name: 'HookJob',
    queue: 'low',
    arguments: { event: 'message.created' },
  });
  const expected = structuredClone(actual);
  expected.effects.jobs.reverse();
  assert.equal(compareSideEffects(expected, actual).matches, true);
});

test('fails when a side-effect payload changes', () => {
  const actual = structuredClone(fixture);
  actual.effects.realtime[0].payload.conversation_id = 99;
  assert.equal(compareSideEffects(fixture, actual).matches, false);
});
