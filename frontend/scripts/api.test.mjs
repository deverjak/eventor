import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { api, ApiError, request } from '../src/api/client.ts';

const originalFetch = globalThis.fetch;
afterEach(() => { globalThis.fetch = originalFetch; });

test('login uses cookie session, JSON and a fresh CSRF token', async () => {
  const tokens = [];
  globalThis.fetch = async (path, init) => {
    assert.equal(path, '/api/auth/login');
    assert.equal(init.credentials, 'same-origin');
    assert.equal(init.method, 'POST');
    assert.equal(init.headers.get('Content-Type'), 'application/json');
    assert.deepEqual(JSON.parse(init.body), { email: 'member@example.test', password: 'secret' });
    assert.ok(init.headers.get('csrf-token').length >= 24);
    tokens.push(init.headers.get('csrf-token'));
    return Response.json({ id: 'a', email: 'member@example.test', displayName: 'Member', memberships: [] });
  };
  await api.auth.login('member@example.test', 'secret');
  await api.auth.login('member@example.test', 'secret');
  assert.notEqual(tokens[0], tokens[1]);
});

test('reads remain scoped to encoded organizer and do not send CSRF', async () => {
  globalThis.fetch = async (path, init) => {
    assert.equal(path, '/api/admin/org/team%2Fother/events');
    assert.equal(init.headers.has('csrf-token'), false);
    return Response.json({ items: [] });
  };
  assert.deepEqual(await api.organizer('team/other').events.list(), { items: [] });
});

test('version and idempotency keys survive an explicit command', async () => {
  globalThis.fetch = async (_path, init) => {
    assert.equal(init.headers.get('If-Match'), '7');
    assert.equal(init.headers.get('Idempotency-Key'), 'same-command');
    return new Response(null, { status: 204 });
  };
  assert.equal(await request('/api/admin/org/team/action', { method: 'POST', version: 7, idempotencyKey: 'same-command' }), undefined);
});

test('problem errors preserve field violations and correlation id', async () => {
  globalThis.fetch = async () => Response.json({ code: 'validation_failed', title: 'Invalid', violations: [{ path: 'details.name', message: 'Required' }] }, { status: 422, headers: { 'X-Correlation-ID': 'trace-1' } });
  await assert.rejects(api.auth.me(), (error) => {
    assert.ok(error instanceof ApiError);
    assert.equal(error.status, 422);
    assert.equal(error.problem.correlationId, 'trace-1');
    assert.equal(error.problem.violations[0].path, 'details.name');
    return true;
  });
});

test('proxy HTML and null JSON errors still become ApiError', async () => {
  for (const response of [new Response('<h1>Unavailable</h1>', { status: 502 }), Response.json(null, { status: 503 })]) {
    globalThis.fetch = async () => response;
    await assert.rejects(api.auth.me(), ApiError);
  }
});

test('network failure is actionable and aborted requests stay aborted', async () => {
  globalThis.fetch = async () => { throw new TypeError('Failed to fetch'); };
  await assert.rejects(api.auth.me(), error => error instanceof ApiError && error.code === 'network_error');
  const aborted = new DOMException('Aborted', 'AbortError');
  globalThis.fetch = async () => { throw aborted; };
  await assert.rejects(api.auth.me(), error => error === aborted);
});

test('external URLs and invalid path segments are rejected before fetch', async () => {
  globalThis.fetch = async () => { assert.fail('must not call fetch'); };
  await assert.rejects(request('https://example.test/api/auth/me'));
  assert.throws(() => api.organizer('..'));
});
