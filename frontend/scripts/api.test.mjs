import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { api, ApiError, request, organizerSlug, signUpWithGeneratedSlug } from '../src/api/client.ts';
import { commandPayload, resolveActionPath } from '../src/api/payload.ts';

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

test('sign-up establishes a cookie session with the actual organizer onboarding payload', async () => {
  const body = { email: 'owner@example.test', password: 'strong-password', displayName: 'Owner', organizerName: 'My team', slug: 'my-team' };
  globalThis.fetch = async (path, init) => {
    assert.equal(path, '/api/auth/sign-up');
    assert.equal(init.credentials, 'same-origin');
    assert.ok(init.headers.get('csrf-token').length >= 24);
    assert.deepEqual(JSON.parse(init.body), body);
    return Response.json({ memberships: [{ slug: 'my-team', role: 'OWNER' }] });
  };
  assert.equal((await api.auth.signUp(body)).memberships[0].slug, 'my-team');
});

test('photo upload keeps multipart boundaries under browser control and includes CSRF', async () => {
  const data = new FormData();
  data.append('photo', new Blob(['photo'], { type: 'image/png' }), 'photo.png');
  data.append('author', 'Jana');
  globalThis.fetch = async (path, init) => {
    assert.equal(path, '/api/admin/org/team/events/event/collections/collection/photos');
    assert.equal(init.body, data);
    assert.equal(init.headers.has('Content-Type'), false);
    assert.ok(init.headers.get('csrf-token'));
    return Response.json({ photoId: 'photo' }, { status: 201 });
  };
  assert.deepEqual(await request('/api/admin/org/team/events/event/collections/collection/photos', { method: 'POST', body: data }), { photoId: 'photo' });
});

test('form edits discard inapplicable constraints and preserve zero, false and existing conditions', () => {
  const draft = { fields: [{ type: 'number', constraints: { min: 0, integer: false, maxLength: 20, max: null }, condition: { type: 'answer', field: 'other', operator: 'equals', value: true } }, { type: 'email', constraints: { maxLength: 30 }, condition: { type: null } }] };
  const body = commandPayload('/api/admin/org/team/events/event/form/draft', draft);
  assert.deepEqual(body.fields[0].constraints, { min: 0, integer: false });
  assert.deepEqual(body.fields[0].condition, draft.fields[0].condition);
  assert.deepEqual(body.fields[1].constraints, {});
  assert.equal(body.fields[1].condition, null);
  assert.equal(draft.fields[0].constraints.maxLength, 20);
});

test('audience check-in filter is a boolean on the wire and empty remains null', () => {
  assert.equal(commandPayload('/api/admin/org/team/campaigns', { audience: { checkedIn: 'false' } }).audience.checkedIn, false);
  assert.equal(commandPayload('/api/admin/org/team/campaigns', { audience: { checkedIn: null } }).audience.checkedIn, null);
});

test('row commands resolve related order IDs without losing tenant scope or allowing path injection', () => {
  assert.equal(resolveActionPath('/api/admin/org/team/events/event/participations', 'person', '/api/admin/org/team/orders/{orderId}/finance', { orderId: 'order/other' }), '/api/admin/org/team/orders/order%2Fother/finance');
  assert.equal(resolveActionPath('/api/admin/org/team/collections', 'a/b', '/publish', {}), '/api/admin/org/team/collections/a%2Fb/publish');
  assert.throws(() => resolveActionPath('/api/admin/org/team', 'person', '/api/admin/org/team/orders/{orderId}', {}));
});

test('organizer addresses are generated from names within backend length and syntax limits', () => {
  assert.equal(organizerSlug('  Děvěra Events!  '), 'devera-events');
  for (const name of ['Ž', '🎉', 'a'.repeat(100), 'a'.repeat(63) + ' z']) {
    const slug = organizerSlug(name);
    assert.ok(slug.length >= 2 && slug.length <= 64);
    assert.match(slug, /^[a-z0-9]+(?:-[a-z0-9]+)*$/);
  }
});

test('sign-up resolves an occupied generated address without asking the user for a slug', async () => {
  const slugs = [];
  globalThis.fetch = async (_path, init) => {
    slugs.push(JSON.parse(init.body).slug);
    if (slugs.length === 1) return Response.json({ code: 'organizer_slug_taken', title: 'Taken' }, { status: 409 });
    return Response.json({ memberships: [{ slug: slugs.at(-1) }] });
  };
  const profile = await signUpWithGeneratedSlug({ email: 'owner@example.test', password: 'strong-password', displayName: 'Owner', organizerName: 'Děvěra Events' });
  assert.equal(slugs[0], 'devera-events');
  assert.match(slugs[1], /^devera-events-[a-f0-9]{8}$/);
  assert.equal(profile.memberships[0].slug, slugs[1]);
});

test('generated-slug onboarding never repeats an ambiguous network failure', async () => {
  let calls = 0;
  globalThis.fetch = async () => { calls++; throw new TypeError('Network failure'); };
  await assert.rejects(signUpWithGeneratedSlug({ email: 'owner@example.test', password: 'strong-password', displayName: 'Owner', organizerName: 'Team' }), ApiError);
  assert.equal(calls, 1);
});
