import createClient from 'openapi-fetch';
import type {
  AccountProfile, AttentionFilters, AttentionItem, AttentionSummary, CapacityOverview,
  CreatedEvent, CreateEventInput, EventDetail, EventDetailsInput, EventListItem, Items,
  LegalDocument, OrganizerProfile, Page, PaymentConnection, ProblemDetails, RefundTask,
  TeamMember, UnmatchedTransaction,
} from './types';

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly problem: ProblemDetails;
  constructor(problem: ProblemDetails) {
    super(problem.detail || problem.title);
    this.name = 'ApiError';
    this.status = problem.status;
    this.code = problem.code;
    this.problem = problem;
  }
}

export interface RequestOptions extends Omit<RequestInit, 'body'> {
  body?: unknown;
  /** Reuse this key when explicitly retrying the same command. No automatic write retries. */
  idempotencyKey?: string;
  version?: number;
}

function csrfHeaders(path: string, method: string, headers: Headers) {
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase()) && /^\/api\/(admin|auth)(\/|$)/.test(path)) {
    headers.set('csrf-token', crypto.randomUUID());
  }
}

/** Same-origin only: session cookies and backend Origin checks must pass through the Next rewrite. */
export async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  if (!path.startsWith('/api/') || path.startsWith('//') || path.includes('\\')) {
    throw new Error('API requests must use a same-origin /api/ path.');
  }
  const { body, idempotencyKey, version, ...init } = options;
  const method = init.method || 'GET';
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  if (body !== undefined) headers.set('Content-Type', 'application/json');
  csrfHeaders(path, method, headers);
  if (idempotencyKey) headers.set('Idempotency-Key', idempotencyKey);
  if (version !== undefined) headers.set('If-Match', String(version));
  let response: Response;
  try {
    response = await fetch(path, { ...init, method, headers, credentials: 'same-origin', cache: 'no-store', body: body === undefined ? undefined : JSON.stringify(body) });
  } catch (error) {
    if (error instanceof Error && error.name === 'AbortError') throw error;
    throw new ApiError({ status: 0, code: 'network_error', title: 'Backend není dostupný', detail: 'Spojení se nepodařilo. Zkontrolujte připojení a zkuste to znovu.' });
  }
  if (!response.ok) {
    let problem: Partial<ProblemDetails> = {};
    try {
      const payload: unknown = await response.json();
      if (payload && typeof payload === 'object') problem = payload as Partial<ProblemDetails>;
    } catch { /* A reverse proxy may return HTML. */ }
    throw new ApiError({
      ...problem,
      status: response.status,
      code: problem.code || `http_${response.status}`,
      title: problem.title || 'Požadavek se nepodařilo dokončit',
      correlationId: problem.correlationId || response.headers.get('X-Correlation-ID'),
    });
  }
  if (response.status === 204) return undefined as T;
  return await response.json() as T;
}

const segment = (value: string) => {
  if (!value || value === '.' || value === '..') throw new Error('An API path segment is required.');
  return encodeURIComponent(value);
};
const query = (values?: object) => {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(values || {})) if (value !== undefined && value !== null) params.set(key, String(value));
  return params.size ? `?${params}` : '';
};

export const api = {
  request,
  auth: {
    me: (signal?: AbortSignal) => request<AccountProfile>('/api/auth/me', { signal }),
    login: (email: string, password: string) => request<AccountProfile>('/api/auth/login', { method: 'POST', body: { email, password } }),
    logout: () => request<void>('/api/auth/logout', { method: 'POST' }),
  },
  organizer(slug: string) {
    const root = `/api/admin/org/${segment(slug)}`;
    const event = (id: string) => `${root}/events/${segment(id)}`;
    return {
      profile: () => request<OrganizerProfile>(`${root}/profile`),
      events: {
        list: (signal?: AbortSignal) => request<Items<EventListItem>>(`${root}/events`, { signal }),
        get: (id: string, signal?: AbortSignal) => request<EventDetail>(event(id), { signal }),
        create: (body: CreateEventInput) => request<CreatedEvent>(`${root}/events`, { method: 'POST', body }),
        update: (id: string, body: EventDetailsInput, version: number) => request<EventDetail>(event(id), { method: 'PUT', body, version }),
        publish: (id: string) => request<{ formVersionId: string; formVersion: number }>(`${event(id)}/publish`, { method: 'POST' }),
        registration: (id: string, transition: 'open' | 'pause' | 'resume' | 'close', version: number) => request<EventDetail>(`${event(id)}/registration/${transition}`, { method: 'POST', version }),
        capacity: (id: string) => request<CapacityOverview>(`${event(id)}/capacity`),
      },
      capacity: (id: string) => request<CapacityOverview>(`${event(id)}/capacity`),
      attention: {
        list: (filters?: AttentionFilters) => request<Page<AttentionItem>>(`${root}/attention${query(filters)}`),
        summary: (eventId?: string) => request<AttentionSummary>(`${root}/attention/summary${query({ eventId })}`),
      },
      legal: { documents: () => request<Items<LegalDocument>>(`${root}/legal/documents`) },
      team: { members: () => request<Items<TeamMember>>(`${root}/members`) },
      payments: {
        unmatched: () => request<Items<UnmatchedTransaction>>(`${root}/unmatched-transactions`),
        refunds: () => request<Items<RefundTask>>(`${root}/refund-tasks`),
        connection: () => request<PaymentConnection>(`${root}/payments/connection`),
      },
    };
  },
};

/** Use with `paths` from schema.d.ts after `npm run openapi`. No guessed schema is shipped. */
export function createOpenApiClient<Paths extends object>() {
  return createClient<Paths>({
    credentials: 'same-origin',
    fetch: async (input: Request) => {
      const url = new URL(input.url);
      if (typeof location !== 'undefined' && url.origin !== location.origin) throw new Error('Cross-origin API request rejected.');
      const headers = new Headers(input.headers);
      csrfHeaders(url.pathname, input.method, headers);
      return fetch(new Request(input, { headers, credentials: 'same-origin' }));
    },
  });
}

export type * from './types';
