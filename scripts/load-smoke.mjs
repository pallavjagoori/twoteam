#!/usr/bin/env node

import process from 'node:process';
import { pathToFileURL } from 'node:url';

export const percentile = (values, ratio) => {
  const sorted = [...values].sort((left, right) => left - right);
  return sorted[Math.max(0, Math.ceil(sorted.length * ratio) - 1)] ?? 0;
};

export const runLoadSmoke = async ({ url, requests, concurrency, maxP95 }) => {
  let next = 0;
  const durations = [];
  const errors = [];
  const worker = async () => {
    while (next < requests) {
      next += 1;
      const started = performance.now();
      try {
        const response = await fetch(url, { signal: AbortSignal.timeout(5000) });
        if (!response.ok) errors.push(`HTTP ${response.status}`);
        await response.arrayBuffer();
      } catch (error) {
        errors.push(error instanceof Error ? error.message : String(error));
      }
      durations.push(performance.now() - started);
    }
  };
  await Promise.all(Array.from({ length: concurrency }, worker));
  const report = {
    url,
    requests,
    concurrency,
    errors: errors.length,
    p50Ms: Math.round(percentile(durations, 0.5)),
    p95Ms: Math.round(percentile(durations, 0.95)),
    maxMs: Math.round(Math.max(...durations)),
  };
  if (report.errors > 0 || report.p95Ms > maxP95) {
    throw new Error(`Load smoke failed: ${JSON.stringify(report)}`);
  }
  return report;
};

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  const [url, requestValue = '200', concurrencyValue = '20', p95Value = '750'] = process.argv.slice(2);
  if (!url) throw new Error('Usage: load-smoke.mjs URL [requests] [concurrency] [max-p95-ms]');
  const report = await runLoadSmoke({
    url,
    requests: Number(requestValue),
    concurrency: Number(concurrencyValue),
    maxP95: Number(p95Value),
  });
  console.log(JSON.stringify(report));
}
