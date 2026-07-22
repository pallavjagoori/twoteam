import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import path from 'node:path';
import test from 'node:test';
import { percentile, runLoadSmoke } from '../../scripts/load-smoke.mjs';

const root = path.resolve(import.meta.dirname, '../..');
const validEnvironment = {
  APP_ENV: 'production',
  APP_DEBUG: 'false',
  APP_URL: 'https://support.example.com',
  APP_KEY: `base64:${'A'.repeat(43)}=`,
  SESSION_SECURE_COOKIE: 'true',
  LOG_LEVEL: 'info',
  DB_PASSWORD: 'strong-database-secret',
  REDIS_PASSWORD: 'strong-redis-secret',
};

test('accepts a hardened production environment', () => {
  const result = spawnSync('node', ['scripts/validate-production-env.mjs'], {
    cwd: root,
    env: { ...process.env, ...validEnvironment },
  });
  assert.equal(result.status, 0, result.stderr.toString());
});

test('rejects unsafe and missing production values without printing secrets', () => {
  const result = spawnSync('node', ['scripts/validate-production-env.mjs'], {
    cwd: root,
    env: { PATH: process.env.PATH, APP_ENV: 'local', APP_DEBUG: 'true', APP_URL: 'http://localhost', APP_KEY: 'secret', SESSION_SECURE_COOKIE: 'false', LOG_LEVEL: 'debug', DB_PASSWORD: 'password' },
  });
  const error = result.stderr.toString();
  assert.equal(result.status, 1);
  assert.match(error, /APP_ENV must be production/);
  assert.match(error, /REDIS_PASSWORD is required/);
  assert.doesNotMatch(error, /strong-database-secret/);
});

test('calculates percentiles and accepts a healthy service under load', async () => {
  assert.equal(percentile([40, 10, 30, 20], 0.5), 20);
  const server = createServer((request, response) => {
    response.writeHead(200, { 'Content-Type': 'application/json' });
    response.end('{"status":"ok"}');
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const address = server.address();
  const report = await runLoadSmoke({
    url: `http://127.0.0.1:${address.port}`,
    requests: 20,
    concurrency: 4,
    maxP95: 1000,
  });
  assert.equal(report.errors, 0);
  await new Promise(resolve => server.close(resolve));
});

test('fails load smoke when the service returns errors', async () => {
  const server = createServer((request, response) => {
    response.writeHead(503);
    response.end();
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const address = server.address();
  await assert.rejects(() => runLoadSmoke({
    url: `http://127.0.0.1:${address.port}`,
    requests: 2,
    concurrency: 1,
    maxP95: 1000,
  }), /Load smoke failed/);
  await new Promise(resolve => server.close(resolve));
});
