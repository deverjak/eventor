/** Hand-reviewed wire DTOs from the backend controllers/read models. Not generated OpenAPI. */
export interface ProblemDetails {
  type?: string;
  title: string;
  status: number;
  code: string;
  detail?: string | null;
  context?: Record<string, string | number | boolean | null>;
  nextSteps?: { action: string; href?: string }[];
  violations?: { path: string; message: string; code?: string }[];
  correlationId?: string | null;
}

export interface Membership { organizerId: string; slug: string; name: string; role: string }
export interface AccountProfile { id: string; email: string; displayName: string; memberships: Membership[] }
export interface Items<T> { items: T[] }
export interface Page<T> extends Items<T> { page: number; perPage: number; total: number }
export interface Money { minor: number; currency: string }
export interface EventListItem {
  id: string; slug: string; name: string; shortDescription: string | null;
  startsAt: string; endsAt: string; visibility: string; publicationState: string;
  registrationState: string; version: number;
}
export interface Venue { name: string; address?: string | null; mapUrl?: string | null }
export interface EventDetailsInput {
  name: string; startsAt: string; endsAt: string;
  shortDescription?: string | null; description?: string | null; coverImageUrl?: string | null;
  contactName?: string | null; contactEmail?: string | null; contactPhone?: string | null;
  timezone?: string; ageReferenceDate?: string | null; visibility?: 'public' | 'unlisted';
  venues?: Venue[]; registrationOpensAt?: string | null; registrationClosesAt?: string | null;
}
export interface CreateEventInput {
  details: EventDetailsInput; slug?: string | null; mode?: 'simple' | 'multi';
  price?: Money | null; capacityLimit?: number | null;
}
export interface CreatedEvent { eventId: string; slug: string; activityId: string | null; variantId: string | null }
export interface PaymentRules {
  methods: string[]; dueDays: number; holdTtlHours: number; autoRestoreOnLatePayment: boolean;
  selfServiceCancellationAllowed: boolean; selfServiceCancellationDeadline: string | null; currency: string;
}
export interface Variant {
  id: string; name: string; price: Money; capacityLimit: number | null; capacityBucketId: string;
  capacityUnit: string; teamSize: number | null; minAge: number | null; maxAge: number | null;
  position: number; version: number;
}
export interface Activity {
  id: string; name: string; description: string | null; location: string | null;
  startsAt: string | null; endsAt: string | null; implicit: boolean; registrationState: string;
  registrationOpensAt: string | null; registrationClosesAt: string | null;
  minAge: number | null; maxAge: number | null; minorsOnly: boolean;
  capacityLimit: number | null; capacityBucketId: string; position: number; version: number; variants: Variant[];
}
export interface EventDetail extends EventListItem {
  description: string | null; coverImageUrl: string | null;
  contact: { name: string | null; email: string | null; phone: string | null };
  timezone: string; ageReferenceDate: string | null; registrationOpensAt: string | null;
  registrationClosesAt: string | null; capacityLimit: number | null; capacityBucketId: string;
  paymentRules: PaymentRules; venues: Venue[]; priceListVersion: number;
  cancellationReason: string | null; createdAt: string; updatedAt: string; activities: Activity[];
}
export interface CapacityBucket {
  bucketId: string; scope: 'event' | 'activity' | 'variant' | 'wave'; scopeId: string;
  unit: 'seat' | 'team'; limit: number | null; held: number; confirmed: number;
  available: number | null; overbooked: boolean;
}
export interface CapacityOverview { eventId: string; buckets: CapacityBucket[] }
export interface AttentionItem {
  id: string; type: string; state: string; subjectType: string; subjectId: string; eventId: string | null;
  openedAt: string; acknowledgedAt: string | null; resolvedAt: string | null;
  resolution: string | null; resolutionNote: string | null; payload: Record<string, unknown>;
}
export interface AttentionSummary { unresolved: number; byType: Record<string, number> }
export interface AttentionFilters { type?: string; state?: string; eventId?: string; page?: number; perPage?: number }
export interface LegalDocument {
  id: string; level: string; scopeId: string; title: string; replacesDocumentId: string | null;
  currentVersion: { id: string; number: number; publishedAt: string | null } | null; createdAt: string;
}
export interface TeamMember {
  membershipId: string; userAccountId: string; email: string; displayName: string;
  role: string; eventScope: string[]; joinedAt: string;
}
export interface OrganizerProfile {
  id: string; slug: string; name: string; contactEmail: string | null; contactPhone: string | null;
  legalName: string | null; companyId: string | null; vatId: string | null;
  billingStreet: string | null; billingCity: string | null; billingPostalCode: string | null;
  billingCountry: string | null; logoUrl: string | null; coverImageUrl: string | null; brandColor: string | null;
}
export interface UnmatchedTransaction {
  id: string; amount: Money; variableSymbol: string | null; bookedOn: string;
  matchingResult: string; handlingState: string; createdAt: string | null;
  resolvedAt: string | null; resolution: string | null; resolvedOrderId: string | null; allocationReason: string | null;
}
export interface RefundTask {
  id: string; orderId: string; amount: Money; reason: string; status: string; note: string | null;
  createdAt: string | null; settledAt: string | null; settleMethod: string | null; settleReference: string | null; cancelledAt: string | null;
}
export interface PaymentConnection {
  connected: boolean; subtenantId: string | null; bankConnectionId: string | null;
  maskedIban: string | null; inboundAddress: string | null; receptionState: string | null;
  pairrotReachable: boolean | null; updatedAt?: string;
}
