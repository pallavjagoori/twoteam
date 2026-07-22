#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const controllerName = action => action.split('\\').at(-1)?.split('@')[0];

export const verifyRoutes = (routes, contract) => {
  const laravelRoutes = routes.filter(route => route.action.startsWith('App\\'));
  if (laravelRoutes.length < contract.minimumLaravelRoutes) {
    throw new Error(`Laravel route count fell from ${contract.minimumLaravelRoutes} to ${laravelRoutes.length}`);
  }
  const controllers = new Set(laravelRoutes.map(route => controllerName(route.action)));
  const missingControllers = contract.requiredControllers.filter(name => !controllers.has(name));
  if (missingControllers.length) throw new Error(`Missing Laravel controllers: ${missingControllers.join(', ')}`);

  for (const expected of contract.requiredRoutes) {
    const route = laravelRoutes.find(candidate =>
      candidate.uri === expected.uri && candidate.method.split('|').includes(expected.method));
    if (!route || controllerName(route.action) !== expected.controller) {
      throw new Error(`Unsupported cutover route: ${expected.method} ${expected.uri}`);
    }
  }
  return laravelRoutes.length;
};

export const verifyCompose = (compose, contract) => {
  const services = Object.keys(compose.services ?? {}).sort();
  const expected = [...contract.productionServices].sort();
  if (JSON.stringify(services) !== JSON.stringify(expected)) {
    throw new Error(`Unexpected production services: ${services.join(', ')}`);
  }
  const runtime = JSON.stringify(compose.services);
  if (/\b(rails|ruby|sidekiq|puma)\b|bundle exec/i.test(runtime)) {
    throw new Error('Production runtime contains a Rails or Ruby dependency');
  }
  for (const name of ['api', 'migrate', 'scheduler', 'web', 'worker']) {
    if (!compose.services[name]?.image) throw new Error(`${name} must use an immutable image`);
  }
  return services;
};

export const verifyRollback = (current, previous) => {
  const currentServices = Object.keys(current.services).sort();
  const previousServices = Object.keys(previous.services).sort();
  if (JSON.stringify(currentServices) !== JSON.stringify(previousServices)) {
    throw new Error('Rollback changes the production service topology');
  }
  if (current.services.api.image === previous.services.api.image || current.services.web.image === previous.services.web.image) {
    throw new Error('Rollback must select previous immutable API and web images');
  }
};

const renderCompose = (root, images = {}) => JSON.parse(execFileSync('docker', [
  'compose', '-f', 'infrastructure/compose.production.yml', 'config', '--format', 'json',
], {
  cwd: root,
  encoding: 'utf8',
  env: {
    ...process.env,
    APP_KEY: `base64:${'A'.repeat(43)}=`,
    APP_URL: 'https://support.example.test',
    POSTGRES_PASSWORD: 'cutover-database-secret',
    REDIS_PASSWORD: 'cutover-redis-secret',
    AWS_ACCESS_KEY_ID: 'cutover-access-key',
    AWS_SECRET_ACCESS_KEY: 'cutover-storage-secret',
    AWS_BUCKET: 'twoteam-cutover',
    ...images,
  },
}));

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  const root = path.resolve(import.meta.dirname, '..');
  const contract = JSON.parse(readFileSync(path.join(root, 'contracts/cutover/laravel-runtime.json'), 'utf8'));
  const routes = JSON.parse(execFileSync('php', ['artisan', 'route:list', '--json'], {
    cwd: path.join(root, 'apps/api'),
    encoding: 'utf8',
  }));
  const current = renderCompose(root, {
    TWOTEAM_API_IMAGE: 'twoteam-api:current',
    TWOTEAM_WEB_IMAGE: 'twoteam-web:current',
  });
  const previous = renderCompose(root, {
    TWOTEAM_API_IMAGE: 'twoteam-api:previous',
    TWOTEAM_WEB_IMAGE: 'twoteam-web:previous',
  });
  const routeCount = verifyRoutes(routes, contract);
  const services = verifyCompose(current, contract);
  verifyRollback(current, previous);
  console.log(JSON.stringify({ laravelRoutes: routeCount, productionServices: services.length, railsRuntimeDependencies: 0, rollback: 'verified' }));
}
