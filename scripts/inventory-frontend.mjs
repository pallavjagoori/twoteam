#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const repositoryRoot = path.resolve(import.meta.dirname, '..');
const frontendRoot = path.join(
  repositoryRoot,
  'upstream/chatwoot/app/javascript',
);
const packageJson = JSON.parse(
  readFileSync(path.join(repositoryRoot, 'upstream/chatwoot/package.json'), 'utf8'),
);
const upstreamMetadata = readFileSync(
  path.join(repositoryRoot, 'upstream/CHATWOOT_VERSION'),
  'utf8',
);
const upstreamTree = upstreamMetadata.match(/^TREE_SHA=(.+)$/m)?.[1];
if (!upstreamTree) throw new Error('Missing TREE_SHA in upstream metadata');
const outputJson = path.join(repositoryRoot, 'contracts/frontend/inventory.json');
const outputMarkdown = path.join(repositoryRoot, 'docs/FRONTEND_INVENTORY.md');
const sourceExtensions = new Set(['.js', '.ts', '.vue']);
const internalAliases = new Set([
  'assets',
  'components',
  'dashboard',
  'helpers',
  'next',
  'shared',
  'survey',
  'v3',
  'widget',
]);
const requestPattern = /\b(axios|API)\.(get|post|put|patch|delete)\s*\(/g;
const fetchPattern = /(?<![\w.])fetch\s*\(/g;
const importPatterns = [
  /\bfrom\s+['"]([^'"]+)['"]/g,
  /\bimport\s+['"]([^'"]+)['"]/g,
  /\brequire\(\s*['"]([^'"]+)['"]\s*\)/g,
  /\bimport\(\s*['"]([^'"]+)['"]\s*\)/g,
];

const walk = directory =>
  readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const absolutePath = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      if (['node_modules', 'specs'].includes(entry.name)) return [];
      return walk(absolutePath);
    }
    return sourceExtensions.has(path.extname(entry.name)) ? [absolutePath] : [];
  });

const lineAt = (source, index) => source.slice(0, index).split('\n').length;
const relativeSource = file => path.relative(repositoryRoot, file);
const normalizeExpression = value => value.replace(/\s+/g, ' ').trim();

const firstArgument = (source, start) => {
  let quote = null;
  let escaped = false;
  let templateDepth = 0;
  const opening = { '(': ')', '[': ']', '{': '}' };
  const stack = [];

  for (let index = start; index < source.length; index += 1) {
    const character = source[index];

    if (escaped) {
      escaped = false;
      continue;
    }
    if (quote && character === '\\') {
      escaped = true;
      continue;
    }
    if (quote === '`' && character === '$' && source[index + 1] === '{') {
      templateDepth = 1;
      index += 1;
      continue;
    }
    if (quote === '`' && templateDepth > 0 && character === '{') {
      templateDepth += 1;
      continue;
    }
    if (quote === '`' && templateDepth > 0 && character === '}') {
      templateDepth -= 1;
      continue;
    }
    if (quote && character === quote && templateDepth === 0) {
      quote = null;
      continue;
    }
    if (!quote && ['\'', '"', '`'].includes(character)) {
      quote = character;
      continue;
    }
    if (quote && quote !== '`') continue;
    if (quote === '`' && templateDepth === 0) continue;

    if (opening[character]) stack.push(opening[character]);
    if (stack.at(-1) === character) {
      stack.pop();
      continue;
    }
    if (character === ',' && stack.length === 0) {
      return source.slice(start, index);
    }
    if (character === ')' && stack.length === 0) {
      return source.slice(start, index);
    }
  }

  return source.slice(start);
};

const packageName = specifier => {
  if (specifier.startsWith('@')) return specifier.split('/').slice(0, 2).join('/');
  return specifier.split('/')[0];
};

const files = walk(frontendRoot).sort();
const requests = [];
const dependencyFiles = new Map();

for (const file of files) {
  const source = readFileSync(file, 'utf8');
  for (const match of source.matchAll(requestPattern)) {
    const expression = normalizeExpression(
      firstArgument(source, match.index + match[0].length),
    );
    requests.push({
      client: match[1],
      method: match[2].toUpperCase(),
      expression,
      source: relativeSource(file),
      line: lineAt(source, match.index),
      static: /^(['"`])[^$]*\1$/.test(expression),
    });
  }

  for (const match of source.matchAll(fetchPattern)) {
    if (/async\s+$/.test(source.slice(Math.max(0, match.index - 10), match.index))) {
      continue;
    }
    const expression = normalizeExpression(
      firstArgument(source, match.index + match[0].length),
    );
    const followingSource = source.slice(
      match.index + match[0].length + expression.length,
      match.index + match[0].length + expression.length + 300,
    );
    const configuredMethod = followingSource.match(
      /\bmethod\s*:\s*['"](GET|POST|PUT|PATCH|DELETE)['"]/i,
    )?.[1];
    requests.push({
      client: 'fetch',
      method: configuredMethod?.toUpperCase() ?? 'GET',
      expression,
      source: relativeSource(file),
      line: lineAt(source, match.index),
      static: /^(['"`])[^$]*\1$/.test(expression),
    });
  }

  for (const pattern of importPatterns) {
    for (const match of source.matchAll(pattern)) {
      if (match[1].startsWith('.') || match[1].startsWith('/')) continue;
      const dependency = packageName(match[1]);
      if (internalAliases.has(dependency) || /\s/.test(dependency)) continue;
      const usedBy = dependencyFiles.get(dependency) ?? new Set();
      usedBy.add(relativeSource(file));
      dependencyFiles.set(dependency, usedBy);
    }
  }
}

requests.sort((left, right) =>
  `${left.source}:${String(left.line).padStart(6, '0')}`.localeCompare(
    `${right.source}:${String(right.line).padStart(6, '0')}`,
  ),
);

const declaredDependencies = {
  ...packageJson.dependencies,
  ...packageJson.devDependencies,
};
const dependencies = [...dependencyFiles.entries()]
  .map(([name, usedBy]) => ({
    name,
    version: declaredDependencies[name] ?? null,
    declared: Object.prototype.hasOwnProperty.call(declaredDependencies, name),
    sourceFiles: [...usedBy].sort(),
  }))
  .sort((left, right) => left.name.localeCompare(right.name));

const inventory = {
  schemaVersion: 1,
  upstream: {
    version: packageJson.version,
    tree: upstreamTree,
  },
  detectors: {
    requests: [
      'axios.get|post|put|patch|delete',
      'API.get|post|put|patch|delete',
      'fetch',
    ],
    sourceExtensions: [...sourceExtensions],
    excludedDirectories: ['node_modules', 'specs'],
  },
  summary: {
    sourceFiles: files.length,
    requests: requests.length,
    staticRequests: requests.filter(request => request.static).length,
    dynamicRequests: requests.filter(request => !request.static).length,
    dependencies: dependencies.length,
    undeclaredImports: dependencies.filter(dependency => !dependency.declared).length,
  },
  requests,
  dependencies,
};

const json = `${JSON.stringify(inventory, null, 2)}\n`;
const digest = createHash('sha256').update(json).digest('hex');
const methods = Object.entries(
  requests.reduce((counts, request) => {
    counts[request.method] = (counts[request.method] ?? 0) + 1;
    return counts;
  }, {}),
).sort();
const markdown = `# Frontend endpoint and dependency inventory

This file is generated by \`scripts/inventory-frontend.mjs\` from the pinned
Chatwoot frontend. Do not edit it manually.

## Baseline

| Metric | Value |
| --- | ---: |
| Chatwoot version | ${inventory.upstream.version} |
| Upstream tree | \`${inventory.upstream.tree}\` |
| Scanned source files | ${inventory.summary.sourceFiles} |
| Detected HTTP requests | ${inventory.summary.requests} |
| Static request expressions | ${inventory.summary.staticRequests} |
| Dynamic request expressions | ${inventory.summary.dynamicRequests} |
| Imported packages | ${inventory.summary.dependencies} |
| Undeclared package imports | ${inventory.summary.undeclaredImports} |
| Inventory SHA-256 | \`${digest}\` |

## Requests by method

| Method | Count |
| --- | ---: |
${methods.map(([method, count]) => `| ${method} | ${count} |`).join('\n')}

Every detected call site, including its exact URL expression, source file and
line, is registered in [\`contracts/frontend/inventory.json\`](../contracts/frontend/inventory.json).
Dynamic expressions remain explicit so implementation work cannot mistake them
for resolved routes.

## Dependency policy

The JSON inventory records every bare package import, its pinned declaration
when available and every source file using it. An undeclared import is a failing
validation condition.

## Regeneration

Run \`node scripts/inventory-frontend.mjs\` after importing a new upstream
version. Run \`node scripts/inventory-frontend.mjs --check\` in CI to fail when
the committed inventory does not match the source snapshot.
`;

if (process.argv.includes('--check')) {
  const stale =
    !existsSync(outputJson) ||
    !existsSync(outputMarkdown) ||
    readFileSync(outputJson, 'utf8') !== json ||
    readFileSync(outputMarkdown, 'utf8') !== markdown;
  if (stale) {
    console.error('Frontend inventory is stale; regenerate it');
    process.exit(1);
  }
  if (inventory.summary.undeclaredImports > 0) {
    console.error('Frontend inventory contains undeclared package imports');
    process.exit(1);
  }
  console.log(`Frontend inventory verified: ${requests.length} requests`);
} else {
  writeFileSync(outputJson, json);
  writeFileSync(outputMarkdown, markdown);
  console.log(`Registered ${requests.length} requests and ${dependencies.length} dependencies`);
}
