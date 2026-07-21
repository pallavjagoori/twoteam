#!/usr/bin/env node

import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const comparableHeaders = ['content-type'];

const sorted = value => {
  if (Array.isArray(value)) return value.map(sorted);
  if (value && typeof value === 'object') {
    return Object.fromEntries(
      Object.entries(value)
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([key, child]) => [key, sorted(child)]),
    );
  }
  return value;
};

const removeJsonPointer = (value, pointer) => {
  const parts = pointer
    .split('/')
    .slice(1)
    .map(part => part.replaceAll('~1', '/').replaceAll('~0', '~'));
  const leaf = parts.pop();
  let current = value;
  for (const part of parts) {
    if (!current || typeof current !== 'object') return;
    current = current[part];
  }
  if (current && typeof current === 'object' && leaf !== undefined) {
    delete current[leaf];
  }
};

const normalizeHeader = (name, value) => {
  if (name === 'content-type') return value?.split(';')[0]?.trim() ?? null;
  return value ?? null;
};

export const normalizeResponse = (response, normalization = {}) => {
  const body = structuredClone(response.body);
  for (const pointer of normalization.ignoreJsonPointers ?? []) {
    removeJsonPointer(body, pointer);
  }

  const headers = Object.fromEntries(
    comparableHeaders
      .filter(name => !(normalization.ignoreHeaders ?? []).includes(name))
      .map(name => [name, normalizeHeader(name, response.headers[name])]),
  );

  return sorted({ status: response.status, headers, body });
};

const requestFor = async (baseUrl, request) => {
  const headers = { accept: 'application/json', ...(request.headers ?? {}) };
  const options = { method: request.method ?? 'GET', headers };
  if (request.body !== undefined) {
    headers['content-type'] = 'application/json';
    options.body = JSON.stringify(request.body);
  }

  const response = await fetch(new URL(request.path, baseUrl), options);
  const contentType = response.headers.get('content-type') ?? '';
  const body = contentType.includes('json')
    ? await response.json()
    : await response.text();

  return {
    status: response.status,
    headers: Object.fromEntries(response.headers.entries()),
    body,
  };
};

export const compareScenario = async (scenario, targets) => {
  const [reference, candidate] = await Promise.all([
    requestFor(targets.referenceUrl, scenario.reference),
    requestFor(targets.candidateUrl, scenario.candidate),
  ]);
  const normalizedReference = normalizeResponse(
    reference,
    scenario.normalization,
  );
  const normalizedCandidate = normalizeResponse(
    candidate,
    scenario.normalization,
  );
  const matches =
    JSON.stringify(normalizedReference) === JSON.stringify(normalizedCandidate);

  return {
    id: scenario.id,
    matches,
    reference: normalizedReference,
    candidate: normalizedCandidate,
  };
};

export const runDifferential = async (contracts, targets) => {
  const results = [];
  for (const scenario of contracts.scenarios) {
    results.push(await compareScenario(scenario, targets));
  }
  return {
    schemaVersion: 1,
    matches: results.every(result => result.matches),
    results,
  };
};

const argument = (name, fallback) => {
  const index = process.argv.indexOf(name);
  return index === -1 ? fallback : process.argv[index + 1];
};

const main = async () => {
  const contractPath = path.resolve(
    argument('--contracts', 'contracts/http/scenarios.json'),
  );
  const reportPath = argument('--report');
  const referenceUrl = argument(
    '--reference-url',
    process.env.REFERENCE_URL ?? 'http://127.0.0.1:3100',
  );
  const candidateUrl = argument(
    '--candidate-url',
    process.env.CANDIDATE_URL ?? 'http://127.0.0.1:8000',
  );
  const contracts = JSON.parse(readFileSync(contractPath, 'utf8'));
  const report = await runDifferential(contracts, {
    referenceUrl,
    candidateUrl,
  });
  const output = `${JSON.stringify(report, null, 2)}\n`;

  if (reportPath) writeFileSync(reportPath, output);
  process.stdout.write(output);
  if (!report.matches) process.exitCode = 1;
};

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  await main();
}
