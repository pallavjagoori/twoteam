#!/usr/bin/env node

import process from 'node:process';

const baseUrl = (process.argv[2] ?? 'http://127.0.0.1:8080').replace(/\/$/, '');
const expectStatus = async (path, status, options = {}) => {
  const url = new URL(path, `${baseUrl}/`);
  const response = await fetch(url, { ...options, signal: AbortSignal.timeout(10000) });
  if (response.status !== status) throw new Error(`${path} returned ${response.status}, expected ${status}`);
  return response;
};

await expectStatus('/up', 200);
const readiness = await (await expectStatus('/api/health/ready', 200)).json();
if (readiness.status !== 'ready') throw new Error('Laravel readiness did not report ready');

const login = await (await expectStatus('/app/login', 200)).text();
if (!login.includes('id="twoteam-runtime-config"')) throw new Error('Laravel runtime configuration is missing');
const asset = login.match(/<script type="module" src="([^"]+)"/u)?.[1];
if (!asset) throw new Error('Unmodified Chatwoot login entrypoint is missing');
const assetResponse = await expectStatus(asset, 200);
if ((await assetResponse.arrayBuffer()).byteLength < 1000) throw new Error('Chatwoot entrypoint asset is unexpectedly small');

const rejectedLogin = await expectStatus('/auth/sign_in', 401, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email: 'missing@twoteam.test', password: 'invalid' }),
});
const rejectedPayload = await rejectedLogin.json();
if (rejectedPayload.success !== false) throw new Error('Laravel authentication response is incompatible');

console.log(JSON.stringify({
  liveness: 'ok',
  readiness: readiness.dependencies,
  frontend: asset,
  authentication: 'laravel',
}));
