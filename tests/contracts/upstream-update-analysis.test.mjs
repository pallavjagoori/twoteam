import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';

const root = path.resolve(import.meta.dirname, '../..');
const source = (directory, values) => {
  mkdirSync(path.join(directory, 'app/javascript'), { recursive: true });
  mkdirSync(path.join(directory, 'app/jobs'), { recursive: true });
  writeFileSync(path.join(directory, 'package.json'), JSON.stringify(values.package));
  writeFileSync(path.join(directory, 'app/javascript/client.js'), values.frontend);
  writeFileSync(path.join(directory, 'app/jobs/example_job.rb'), values.job);
};

test('reports frontend, dependency, Rails, and backend update risks', () => {
  const temporary = mkdtempSync(path.join(tmpdir(), 'twoteam-upstream-'));
  const baseline = path.join(temporary, 'baseline');
  const candidate = path.join(temporary, 'candidate');
  const json = path.join(temporary, 'report.json');
  const markdown = path.join(temporary, 'report.md');
  source(baseline, {
    package: { version: '1.0.0', dependencies: { axios: '1.0.0' } },
    frontend: "import axios from 'axios';\naxios.get('/api/v1/accounts');\n",
    job: 'class ExampleJob; end\n',
  });
  source(candidate, {
    package: { version: '1.1.0', dependencies: { axios: '2.0.0', '@rails/actioncable': '8.0.0' } },
    frontend: "import axios from 'axios';\nimport cable from '@rails/actioncable';\naxios.post('/api/v1/messages');\n// A newer fetch (filter change) wins.\n",
    job: 'class ExampleJob; def perform; end; end\n',
  });
  execFileSync('node', [
    path.join(root, 'scripts/analyze-upstream-update.mjs'),
    '--baseline', baseline,
    '--candidate', candidate,
    '--baseline-ref', 'v1.0.0',
    '--candidate-ref', 'v1.1.0',
    '--json', json,
    '--markdown', markdown,
  ], { cwd: root });
  const report = JSON.parse(readFileSync(json, 'utf8'));
  assert.equal(report.summary.addedRequests, 1);
  assert.equal(report.summary.removedRequests, 1);
  assert.equal(report.summary.dependencyChanges, 2);
  assert.equal(report.summary.newRailsAssumptions, 1);
  assert.doesNotMatch(JSON.stringify(report.frontend.requests), /filter change/);
  assert.equal(report.backend.jobs.length, 1);
  assert.match(readFileSync(markdown, 'utf8'), /Required compatibility gates/);
});

test('requires both source trees and explicit report metadata', () => {
  assert.throws(() => execFileSync('node', [
    path.join(root, 'scripts/analyze-upstream-update.mjs'),
  ], { cwd: root, stdio: 'pipe' }), /Command failed/);
});
