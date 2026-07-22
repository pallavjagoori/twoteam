import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import test from 'node:test';
import { verifyCompose, verifyRollback, verifyRoutes } from '../../scripts/verify-laravel-cutover.mjs';

const root = path.resolve(import.meta.dirname, '../..');

test('verifies the real Laravel-only production cutover contract', () => {
  const output = execFileSync('node', ['scripts/verify-laravel-cutover.mjs'], { cwd: root, encoding: 'utf8' });
  const report = JSON.parse(output);
  assert.ok(report.laravelRoutes >= 131);
  assert.equal(report.railsRuntimeDependencies, 0);
  assert.equal(report.rollback, 'verified');
});

test('rejects missing routes and non-Laravel handlers', () => {
  const contract = {
    minimumLaravelRoutes: 1,
    requiredControllers: ['MessageController'],
    requiredRoutes: [{ method: 'POST', uri: 'api/messages', controller: 'MessageController' }],
  };
  assert.throws(() => verifyRoutes([], contract), /route count fell/);
  assert.throws(() => verifyRoutes([
    { method: 'POST', uri: 'api/messages', action: 'App\\Http\\Controllers\\OtherController@store' },
  ], contract), /Missing Laravel controllers/);
});

test('rejects Rails services and rollback topology drift', () => {
  const contract = { productionServices: ['api'] };
  assert.throws(() => verifyCompose({ services: { api: { image: 'rails:latest', command: 'bundle exec puma' } } }, contract), /Rails or Ruby/);
  assert.throws(() => verifyRollback(
    { services: { api: { image: 'api:current' }, web: { image: 'web:current' } } },
    { services: { api: { image: 'api:previous' } } },
  ), /topology/);
});
