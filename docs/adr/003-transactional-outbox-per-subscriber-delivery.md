---
status: accepted
last-verified: 2026-10-25
---

# ADR 003 — Transactional outbox on the Messenger Doctrine transport, one transport per subscribing module

**Status:** accepted 2026-10-24, revised 2026-10-25 (custom outbox tables replaced by native Messenger) · design only, no code yet. Proof will be `backend/config/packages/messenger.php`, `backend/src/Shared/Infrastructure/Messenger/` (`DomainEventsMiddleware`, `IntegrationEventRoutingPass`, `ScheduleRetryStrategy`, `DeadLetterListener`), the migration for `messenger_messages` and `inbox_message`, and the scenario tests `S08`/`S09`.

## Context

Most reactions in Eventor happen after the fact and must not be lost:

- an order creates a Pairrot payment request (PAY-03, S08),
- a payment confirms participations (PAY-05),
- state changes send emails (COM-01, S09),
- failures land in the attention queue (ADM-03).

The NFRs require that an outage of Pairrot, the email service or storage **never loses an accepted registration**, and that deferred operations retry with a traceable state.

Two naive solutions fail. A message sent to a broker from inside the handler escapes even if the transaction rolls back (a phantom message). A message sent after commit is lost when the process dies in between. `DispatchAfterCurrentBusStamp` is the second case: it releases messages after the bus middleware stack returns, so it gives no atomicity.

The first version of this ADR designed its own `outbox_event` / `outbox_delivery` tables, a custom Messenger transport and a relay. That re-implements what Symfony already ships: a database queue that sits on the same connection as the ORM, a worker, retry strategies and a failure transport. A Symfony developer would expect the native pieces.

## Decision

**The queue is the outbox.** Async transports use the Messenger **Doctrine transport** on the ORM's own connection (`doctrine://default`). The `doctrine_transaction` middleware on `command.bus` opens the transaction around the handler. Integration events are dispatched **inside** that transaction, so the `INSERT INTO messenger_messages` commits or rolls back together with the state change. No relay is needed, because the consumer reads the same table. Postgres `LISTEN/NOTIFY` (`use_notify: true`) wakes the worker right after commit.

```php
// config/packages/messenger.php (excerpt)
$messenger->failureTransport('failed');
$messenger->bus('command.bus')->middleware(['validation', 'doctrine_transaction', DomainEventsMiddleware::class]);
$messenger->bus('event.bus')->defaultMiddleware()->allowNoHandlers(true);   // a domain event may have no handler

foreach (['catalog', 'legal', 'registration', 'payments', 'checkin', 'communication', 'content', 'privacy', 'attention', 'reporting', 'identity'] as $module) {
    $messenger->transport("{$module}_events")
        ->dsn('doctrine://default?queue_name='.$module.'_events')
        ->options(['use_notify' => true, 'redeliver_timeout' => 300, 'auto_setup' => false])
        ->retryStrategy()->service(ScheduleRetryStrategy::class);
}
$messenger->transport('pairrot_inbox')->dsn('doctrine://default?queue_name=pairrot_inbox')/* … */;
$messenger->transport('async')->dsn('doctrine://default?queue_name=async')/* thumbnails, CSV exports */;
$messenger->transport('async_mail')->dsn('doctrine://default?queue_name=async_mail')/* SendDelivery, 4 attempts */;
$messenger->transport('failed')->dsn('doctrine://default?queue_name=failed');
```

**Domain events and integration events are different things.**

- Aggregates record **domain events** (`<Module>\Domain\Event`). They are internal to the module and may change with the model.
- `DomainEventsMiddleware` runs inside `doctrine_transaction`. After the handler returns it flushes, pulls the recorded events from the aggregates in the unit of work and dispatches them on `event.bus` synchronously. They are not routed to any transport, so their handlers run in-process in the same transaction.
- A module publishes **integration events** (`<Module>\Contract\Event`) from those sync handlers. Integration events are versioned, carry no personal data and form the public contract ([catalogue](../architecture/06-asynchronni-svet/katalog-udalosti.md)). Only integration events are routed to transports.

**One transport per subscribing module, one handler per event and module.** A subscriber is an ordinary handler bound to its module's transport:

```php
#[AsMessageHandler(fromTransport: 'payments_events')]
final readonly class CreatePaymentRequestOnOrderPlaced
{
    public function __invoke(OrderPlaced $event): void { /* dispatches Payments' own command */ }
}
```

When an event is routed to several transports, the Doctrine transport writes one row per transport in the same transaction. Each subscribing module therefore gets its own copy with its own retry counter and failure record. A failing email never re-runs Pairrot request creation. Within one module, one handler per event keeps a retry from repeating side effects that already succeeded; a module that needs several reactions dispatches its own commands.

**Routing is derived from the subscribers.** The publisher must not know who listens. `IntegrationEventRoutingPass` reads every `#[AsMessageHandler(fromTransport: …)]` whose message is an integration event and adds that transport to the event's routing in `messenger.senders_locator`. The pass fails the container build when:

- an integration event class has no `#[IntegrationEvent(name, version)]`,
- an integration event has no subscriber and is not marked `#[NoSubscribers]`,
- a module has two handlers of one event on its transport,
- a handler binds to a transport that is not configured.

The event catalogue is therefore checked by code.

**Retry and dead letter.**

- `ScheduleRetryStrategy` (a `RetryStrategyInterface` service) waits 1 min, 5 min, 30 min, 2 h, then 12 h, with ±10 % jitter. It does not retry `UnrecoverableMessageHandlingException`, and it honours `RecoverableMessageHandlingException` with a delay (Pairrot `429 Retry-After`).
- After the 6th failure Messenger moves the envelope to the `failed` transport.
- `DeadLetterListener` (on `WorkerMessageFailedEvent` with `willRetry() === false`) opens an `AttentionItem outbox_dead_letter`. Its "retry" action does what `messenger:failed:retry <id>` does.
- `redeliver_timeout` (5 min) returns messages of a crashed worker to the queue. This is the lease.

**Subscriber contract.** Delivery is at-least-once. Subscribers are idempotent: state-guarded transitions, a key derived from the event id (Pairrot `external_id`, email `idempotency_key`) or a unique index (ledger). Global ordering is not guaranteed. A subscriber that cares about order compares versions (Registration ignores `OrderPaymentChanged` with an older `ledgerVersion`). A subscriber writes only its own module's tables.

**Metadata.** The envelope carries stamps: `CorrelationStamp`, `TenantStamp` (organizer id; `TenantContextMiddleware` restores the Doctrine filter in the worker) and `ActorStamp`. Messages are serialized as JSON by the Serializer, so rows stay readable.

**Inbox.** Inbound webhooks follow the same principle. `inbox_message(provider, external_event_id UNIQUE, payload, received_at)` deduplicates. The webhook command inserts the row and, only if it was new, dispatches `ProcessPairrotEvent(inboxId)` to `pairrot_inbox` in the same transaction ([ADR 004](004-pairrot-integration-contract.md)).

**Upgrade path.** Symfony 8.2 (November 2026, in development at the time of writing) adds a native `outbox:` option to Messenger transports. It relays messages from a Doctrine outbox to an external broker. Eventor has no broker, so it does not need it. If Payments ever moves out, its transport switches to RabbitMQ or SQS with `outbox: db_outbox`, and the handlers stay unchanged.

## Consequences

**Positive**
- No lost or phantom messages, and no relay process.
- Only native Symfony: Doctrine transport, worker (`messenger:consume`), retry strategy, failure transport, `messenger:stats`, `messenger:failed:show|retry`.
- Each subscribing module retries independently and its failures are visible in the attention queue.
- No extra infrastructure beyond Postgres.

**Negative**
- The Doctrine transport deletes a row on ack, so there is no event history in the queue. Audit (`audit_entry`) is the history.
- No per-aggregate ordering; subscribers must tolerate reordering (version checks).
- Write amplification of one row per subscribing module.
- Routing through a compiler pass touches a framework-internal service definition (`messenger.senders_locator`). A container compilation test guards it against framework upgrades.

**Operational**
- One `worker` container runs `messenger:consume scheduler_default pairrot_inbox payments_events registration_events … async --keepalive` under Docker restart policy.
- The health endpoint reports queue lengths, the age of the oldest undelivered message per transport (`min(available_at) WHERE delivered_at IS NULL`) and the size of `failed`. Older than 10 minutes means degraded and raises an `AttentionItem`.

## Alternatives considered

- **Custom outbox tables with a relay and custom transport** (the first version of this ADR). Same guarantees, but it re-implements the worker, retry and failure handling.
- **`DispatchAfterCurrentBusStamp` or a `postFlush` listener that dispatches.** Outside the transaction boundary.
- **One transport for all subscribers.** One failing handler would block or re-run the others.
- **Debezium/CDC to Kafka or RabbitMQ.** The right shape at scale, but heavy infrastructure for one instance.
- **Ecotone.** A capable message-driven framework, but opinionated and not what a Symfony team expects in a plain Symfony monolith.

## Verification

- An integration test per command asserts the expected `messenger_messages` rows (event class, transports).
- `S09_EmailOutageTest` stops SMTP (smtp4dev), registers, then restarts SMTP. It asserts exactly one email per recipient.
- `S08_PairrotOutageTest` makes the Pairrot gateway fail and asserts that the order exists, the message retries, and the request is created after recovery.
- A rollback test: a handler throws after dispatching an integration event, and no row stays in `messenger_messages`.
- A container compilation test covers the error cases of `IntegrationEventRoutingPass`.

## Related

[ADR 001](001-modular-monolith-with-event-sourced-ledger.md), [ADR 004](004-pairrot-integration-contract.md) · [06 Async world](../architecture/06-asynchronni-svet/README.md), [09 Symfony](../architecture/09-symfony-v-architekture/README.md) · Supersedes: the custom-table design in the first version of this ADR.
