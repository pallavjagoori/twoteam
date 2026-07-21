import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { after, before, test } from 'node:test';

import { runDifferential } from '../../scripts/http-differential.mjs';

const servers = [];
const startServer = async responseFor => {
  const server = createServer((request, response) => {
    const fixture = responseFor(request);
    response.writeHead(fixture.status, {
      'content-type': 'application/json; charset=utf-8',
    });
    response.end(JSON.stringify(fixture.body));
  });
  await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', resolve);
  });
  servers.push(server);
  return `http://127.0.0.1:${server.address().port}`;
};

let referenceUrl;
let candidateUrl;

before(async () => {
  referenceUrl = await startServer(request => ({
    status: request.url === '/different-status' ? 201 : 200,
    body: {
      id: request.url === '/different-body' ? 1 : 7,
      name: 'Reference',
      generated_at: '2026-01-01T00:00:00Z',
    },
  }));
  candidateUrl = await startServer(request => ({
    status: 200,
    body: {
      id: request.url === '/different-body' ? 2 : 7,
      name: request.url === '/ignored-field' ? 'Laravel' : 'Reference',
      generated_at: '2026-07-21T00:00:00Z',
    },
  }));
});

after(async () => {
  servers.forEach(server => server.closeAllConnections());
  await Promise.all(
    servers.map(server => new Promise(resolve => server.close(resolve))),
  );
});

const run = (path, normalization = {}) =>
  runDifferential(
    {
      scenarios: [
        {
          id: 'test',
          reference: { path },
          candidate: { path },
          normalization,
        },
      ],
    },
    { referenceUrl, candidateUrl },
  );

test('matches equivalent responses after explicit normalization', async () => {
  const report = await run('/ignored-field', {
    ignoreJsonPointers: ['/generated_at', '/name'],
  });
  assert.equal(report.matches, true);
});

test('fails on a meaningful JSON body difference', async () => {
  const report = await run('/different-body', {
    ignoreJsonPointers: ['/generated_at'],
  });
  assert.equal(report.matches, false);
  assert.equal(report.results[0].reference.body.id, 1);
  assert.equal(report.results[0].candidate.body.id, 2);
});

test('fails on an HTTP status difference', async () => {
  const report = await run('/different-status', {
    ignoreJsonPointers: ['/generated_at'],
  });
  assert.equal(report.matches, false);
  assert.equal(report.results[0].reference.status, 201);
  assert.equal(report.results[0].candidate.status, 200);
});
