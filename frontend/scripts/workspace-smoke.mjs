import assert from 'node:assert/strict';

// Explicit, opt-in integration check. Creates its own isolated organizer and labelled test data.
const origin = process.env.SMOKE_ORIGIN || 'http://localhost:3000';
const unique = Date.now().toString(36);
let cookie = '';
const jar = new Map();
let count = 0;
async function call(path, method = 'GET', body, extraHeaders = {}) {
  const multipart = body instanceof FormData;
  const response = await fetch(origin + path, {
    method, headers: { Accept: 'application/json', Origin: origin, 'csrf-token': crypto.randomUUID(), ...(cookie ? { Cookie: cookie } : {}), ...(body !== undefined && !multipart ? { 'Content-Type': 'application/json' } : {}), ...extraHeaders },
    body: body === undefined ? undefined : multipart ? body : JSON.stringify(body), signal: AbortSignal.timeout(30000),
  });
  const cookies = response.headers.getSetCookie();
  for (const item of cookies) { const pair = item.split(';')[0]; const index = pair.indexOf('='); jar.set(pair.slice(0, index), pair.slice(index + 1)); }
  cookie = [...jar.entries()].map(([name, value]) => `${name}=${value}`).join('; ');
  const result = response.status === 204 ? undefined : await response.json();
  assert.ok(response.ok, `${method} ${path.replace(/ss1_[^/]+/g, '[token]')}: ${response.status} ${result?.code || ''} ${JSON.stringify(result?.violations || [])}`);
  count++;
  if (count % 10 === 0) console.log(`${count} API checks passed`);
  return result;
}
const slug = `frontend-smoke-${unique}`;
await call('/api/auth/sign-up', 'POST', { email: `${slug}@example.test`, password: crypto.randomUUID(), displayName: 'Frontend integration check', organizerName: 'Frontend integration check', slug });
const org = `/api/admin/org/${slug}`;
assert.equal((await call('/api/auth/me')).memberships[0].slug, slug);
const profile = await call(`${org}/profile`);
await call(`${org}/profile`, 'PUT', { ...profile, contactEmail: `${slug}@example.test` });
const created = await call(`${org}/events`, 'POST', { details: { name: 'Frontend integration check', startsAt: '2026-12-01T09:00:00+01:00', endsAt: '2026-12-01T17:00:00+01:00' }, mode: 'simple', price: { minor: 10000, currency: 'CZK' }, capacityLimit: 20 });
const root = `${org}/events/${created.eventId}`;
let event = await call(root);
await call(`${root}/payment-rules`, 'PUT', { methods: ['cash'], dueDays: 7, holdTtlHours: 48, selfServiceCancellationAllowed: true }, { 'If-Match': String(event.version) });
const form = await call(`${root}/form`);
await call(`${root}/form/draft`, 'PUT', { fields: form.draft.fields });
await call(`${root}/program`, 'POST', { title: 'Welcome', startsAt: '2026-12-01T09:00:00+01:00', endsAt: '2026-12-01T09:30:00+01:00', visibility: 'public' });
await call(`${root}/news`, 'POST', { title: 'Welcome', body: 'Integration test', publish: false });
await call(`${root}/publish`, 'POST', {});
await call(`${root}/registration/open`, 'POST', { cascade: true });
event = await call(root);
const publicRoot = `/api/public/orgs/${slug}/events/${event.slug}`;
await call(publicRoot);
const selection = [{ itemRef: 'p1', activityId: created.activityId, variantId: created.variantId, minor: false }];
const preview = await call(`${publicRoot}/registration-form`, 'POST', { items: selection });
const collection = await call(`${root}/collections`, 'POST', { title: 'Integration gallery', visibility: 'public' });
const collectionPath = `${root}/collections/${collection.collectionId}`;
const photo = new FormData();
photo.append('photo', new Blob([Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', 'base64')], { type: 'image/png' }), 'test.png');
photo.append('author', 'Integration check');
await call(`${collectionPath}/photos`, 'POST', photo);
await call(`${collectionPath}/photos`);
await call(`${collectionPath}/publish`, 'POST', {});
await call(`${publicRoot}/collections/${collection.collectionId}/photos`);
await call(`${collectionPath}/archive`, 'PUT', { inArchive: true });
const material = await call(`${root}/materials`, 'POST', { title: 'Test results', url: 'https://example.com/results', type: 'results', position: 0 });
await call(`${root}/materials/${material.materialId}/publish`, 'POST', {});
await call(`${publicRoot}/content`);
await call(`${root}/closing-message`, 'POST', {});
await call(`${org}/members`);
const invitation = await call(`${org}/invitations`, 'POST', { email: `invited-${unique}@example.test`, role: 'EVENT_MANAGER', eventScope: [] });
await call(`${org}/invitations/${invitation.invitationId}`, 'DELETE');
const device = await call(`${org}/device-tokens`, 'POST', { label: 'Integration check' });
await call(`${org}/device-tokens/${device.id}`, 'DELETE');
await call(`${org}/communication/reminder-settings`);
await call(`${org}/message-templates/preview`, 'POST', { locale: 'cs', subject: 'Test', body: 'Test' });
const campaign = await call(`${org}/campaigns`, 'POST', { eventId: created.eventId, name: 'Integration draft', purpose: 'organizational', locale: 'cs', subject: 'Test', body: 'Test', audience: {} });
await call(`${org}/campaigns/${campaign.campaignId || campaign.id}`);
const document = await call(`${org}/legal/documents`, 'POST', { title: 'Integration terms', level: 'organizer' });
const version = await call(`${org}/legal/documents/${document.documentId}/versions`, 'POST', { kind: 'text', text: 'Integration terms.' });
await call(`${org}/legal/documents/${document.documentId}/versions/${version.versionId}/publish`, 'POST', { requiresReconfirmation: false });
await call(`${org}/legal/requirements`, 'POST', { documentId: document.documentId, type: 'consent', purpose: 'Integration consent', confirmer: 'orderer', mandatory: false });
// The preceding calls give the public antispam timestamp time to mature.
const order = await call(`${publicRoot}/orders`, 'POST', { items: selection.map(i => ({ ...i, personRef: 'p1' })), persons: [{ personRef: 'p1', answers: { first_name: 'Test', last_name: 'Participant', birth_date: '1990-01-01' } }], orderAnswers: { orderer_first_name: 'Test', orderer_last_name: 'Contact', orderer_email: `${slug}@example.test` }, decisions: [], paymentMethod: 'cash', formToken: preview.formToken, website: '', locale: 'cs' }, { 'Idempotency-Key': crypto.randomUUID() });
await call(`${root}/orders/${order.orderId}`);
await call(`${org}/orders/${order.orderId}/finance`);
await call(`${org}/orders/${order.orderId}/cash`, 'POST', { amountMinor: 10000, note: 'Integration check' }, { 'Idempotency-Key': crypto.randomUUID() });
await call(`${org}/orders/${order.orderId}/ledger`);
await call(`${root}/participations`);
await call(`${root}/capacity`);
await call(`/api/check-in/org/${slug}/events/${created.eventId}/lists`);
await call(`/api/check-in/org/${slug}/events/${created.eventId}/overview`);
await call(`${org}/audit-log`);
await call(`${org}/attention`);
await call('/api/auth/logout', 'POST', {});
console.log(`${count} API checks passed through the frontend proxy. Isolated workspace: ${slug}`);
