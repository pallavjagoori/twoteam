#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const options = Object.fromEntries(
  process.argv.slice(2).reduce((pairs, value, index, values) => {
    if (value.startsWith('--')) pairs.push([value.slice(2), values[index + 1]]);
    return pairs;
  }, []),
);
const required = ['baseline', 'candidate', 'baseline-ref', 'candidate-ref', 'json', 'markdown'];
for (const name of required) {
  if (!options[name]) throw new Error(`Missing --${name}`);
}

const root = process.cwd();
const baselineRoot = path.resolve(root, options.baseline);
const candidateRoot = path.resolve(root, options.candidate);
if (!existsSync(baselineRoot) || !existsSync(candidateRoot)) {
  throw new Error('Baseline and candidate directories must exist');
}

const ignoredDirectories = new Set(['.git', 'log', 'node_modules', 'storage', 'tmp']);
const sourceExtensions = new Set(['.js', '.ts', '.vue']);
const requestPattern = /\b(axios|API)\.(get|post|put|patch|delete)\s*\(\s*([^,\n)]+)/g;
const fetchPattern = /(?<![\w.])fetch\s*\(\s*([^,\n)]+)/g;
const importPatterns = [
  /\bfrom\s+['"]([^'"]+)['"]/g,
  /\bimport\s+['"]([^'"]+)['"]/g,
  /\brequire\(\s*['"]([^'"]+)['"]\s*\)/g,
  /\bimport\(\s*['"]([^'"]+)['"]\s*\)/g,
];
const railsPatterns = [/@rails\//i, /action[_-]?cable/i, /rails-ujs/i, /\.js\.erb\b/i];
const backendAreas = {
  routes: ['config/routes.rb'],
  api: ['app/controllers/api/'],
  migrations: ['db/migrate/'],
  jobs: ['app/jobs/'],
  mailers: ['app/mailers/'],
  realtime: ['app/channels/'],
  models: ['app/models/'],
  services: ['app/services/'],
};

const walk = directory => readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
  if (ignoredDirectories.has(entry.name)) return [];
  const absolute = path.join(directory, entry.name);
  return entry.isDirectory() ? walk(absolute) : [absolute];
});
const digest = file => createHash('sha256').update(readFileSync(file)).digest('hex');
const manifest = directory => new Map(
  walk(directory).map(file => [path.relative(directory, file), { hash: digest(file), bytes: statSync(file).size }]),
);
const normalize = value => value.replace(/\s+/g, ' ').trim();
const packageName = specifier => specifier.startsWith('@')
  ? specifier.split('/').slice(0, 2).join('/')
  : specifier.split('/')[0];

const inspectFrontend = directory => {
  const frontend = path.join(directory, 'app/javascript');
  const files = existsSync(frontend)
    ? walk(frontend).filter(file => sourceExtensions.has(path.extname(file)))
    : [];
  const requests = new Set();
  const dependencies = new Set();
  const railsAssumptions = new Set();
  for (const file of files) {
    const source = readFileSync(file, 'utf8');
    const relative = path.relative(directory, file);
    for (const match of source.matchAll(requestPattern)) {
      const line = source.slice(source.lastIndexOf('\n', match.index) + 1, match.index);
      if (line.includes('//')) continue;
      requests.add(`${match[2].toUpperCase()} ${normalize(match[3])} (${relative})`);
    }
    for (const match of source.matchAll(fetchPattern)) {
      const line = source.slice(source.lastIndexOf('\n', match.index) + 1, match.index);
      if (line.includes('//')) continue;
      requests.add(`FETCH ${normalize(match[1])} (${relative})`);
    }
    for (const pattern of importPatterns) {
      for (const match of source.matchAll(pattern)) {
        if (!match[1].startsWith('.') && !match[1].startsWith('/')) dependencies.add(packageName(match[1]));
      }
    }
    if (railsPatterns.some(pattern => pattern.test(source))) railsAssumptions.add(relative);
  }
  return { files: files.length, requests, dependencies, railsAssumptions };
};

const difference = (left, right) => [...left].filter(value => !right.has(value)).sort();
const baselineFiles = manifest(baselineRoot);
const candidateFiles = manifest(candidateRoot);
const baselineFrontend = inspectFrontend(baselineRoot);
const candidateFrontend = inspectFrontend(candidateRoot);
const addedFiles = difference(new Set(candidateFiles.keys()), new Set(baselineFiles.keys()));
const removedFiles = difference(new Set(baselineFiles.keys()), new Set(candidateFiles.keys()));
const modifiedFiles = [...candidateFiles.keys()]
  .filter(file => baselineFiles.has(file) && baselineFiles.get(file).hash !== candidateFiles.get(file).hash)
  .sort();
const changedFiles = [...new Set([...addedFiles, ...removedFiles, ...modifiedFiles])];
const packageJson = directory => JSON.parse(readFileSync(path.join(directory, 'package.json'), 'utf8'));
const packages = packageJson(candidateRoot);
const baselinePackages = packageJson(baselineRoot);
const dependencyMap = value => ({ ...value.dependencies, ...value.devDependencies });
const beforeDependencies = dependencyMap(baselinePackages);
const afterDependencies = dependencyMap(packages);
const dependencyChanges = [...new Set([...Object.keys(beforeDependencies), ...Object.keys(afterDependencies)])]
  .filter(name => beforeDependencies[name] !== afterDependencies[name])
  .sort()
  .map(name => ({ name, before: beforeDependencies[name] ?? null, after: afterDependencies[name] ?? null }));
const backendChanges = Object.fromEntries(Object.entries(backendAreas).map(([area, prefixes]) => [
  area,
  changedFiles.filter(file => prefixes.some(prefix => file === prefix || file.startsWith(prefix))),
]));

const report = {
  schemaVersion: 1,
  generatedAt: new Date().toISOString(),
  baseline: { ref: options['baseline-ref'], version: baselinePackages.version ?? null },
  candidate: { ref: options['candidate-ref'], version: packages.version ?? null },
  files: { added: addedFiles, removed: removedFiles, modified: modifiedFiles },
  frontend: {
    sourceFiles: { before: baselineFrontend.files, after: candidateFrontend.files },
    requests: {
      added: difference(candidateFrontend.requests, baselineFrontend.requests),
      removed: difference(baselineFrontend.requests, candidateFrontend.requests),
    },
    importedPackages: {
      added: difference(candidateFrontend.dependencies, baselineFrontend.dependencies),
      removed: difference(baselineFrontend.dependencies, candidateFrontend.dependencies),
    },
    dependencyVersions: dependencyChanges,
    railsAssumptions: {
      added: difference(candidateFrontend.railsAssumptions, baselineFrontend.railsAssumptions),
      removed: difference(baselineFrontend.railsAssumptions, candidateFrontend.railsAssumptions),
    },
  },
  backend: backendChanges,
  requiredGates: [
    'Build and test the unmodified candidate frontend',
    'Register added and removed frontend requests in compatibility contracts',
    'Classify new Rails-specific frontend assumptions',
    'Translate relevant migrations, jobs, events, and payload changes to Laravel',
    'Run HTTP and side-effect contracts against Rails and Laravel',
    'Run critical browser workflows and update docs/COMPATIBILITY.md',
  ],
};
report.summary = {
  changedFiles: changedFiles.length,
  addedRequests: report.frontend.requests.added.length,
  removedRequests: report.frontend.requests.removed.length,
  dependencyChanges: dependencyChanges.length,
  newRailsAssumptions: report.frontend.railsAssumptions.added.length,
  backendChanges: Object.values(backendChanges).reduce((total, files) => total + files.length, 0),
};

const jsonPath = path.resolve(root, options.json);
const markdownPath = path.resolve(root, options.markdown);
mkdirSync(path.dirname(jsonPath), { recursive: true });
mkdirSync(path.dirname(markdownPath), { recursive: true });
writeFileSync(jsonPath, `${JSON.stringify(report, null, 2)}\n`);
const list = values => values.length
  ? values.map(value => `- \`${typeof value === 'string' ? value : value.name}\``).join('\n')
  : '- None';
const markdown = `# Chatwoot upstream update analysis

Generated automatically from source trees. The candidate source remains outside
Twoteam's pinned, read-only \`upstream/chatwoot\` snapshot.

| Field | Value |
| --- | --- |
| Baseline | \`${report.baseline.ref}\` |
| Candidate | \`${report.candidate.ref}\` |
| Changed files | ${report.summary.changedFiles} |
| Added / removed frontend requests | ${report.summary.addedRequests} / ${report.summary.removedRequests} |
| Dependency changes | ${report.summary.dependencyChanges} |
| New Rails assumptions | ${report.summary.newRailsAssumptions} |
| Relevant backend changes | ${report.summary.backendChanges} |

## Added frontend requests

${list(report.frontend.requests.added)}

## Removed frontend requests

${list(report.frontend.requests.removed)}

## New Rails-specific assumptions

${list(report.frontend.railsAssumptions.added)}

## Backend change counts

${Object.entries(backendChanges).map(([area, files]) => `- ${area}: ${files.length}`).join('\n')}

## Required compatibility gates

${report.requiredGates.map(gate => `- [ ] ${gate}`).join('\n')}
`;
writeFileSync(markdownPath, markdown);
console.log(JSON.stringify(report.summary));
