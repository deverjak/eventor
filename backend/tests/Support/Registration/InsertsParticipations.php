<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Registration;

use Doctrine\DBAL\Connection;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * Test arrangement for other modules' tests that need a participation Registration knows (its `ParticipationQuery` is
 * the filter of Legal's reconfirmation audience): the minimal order -> person -> participation rows, in a given state.
 */
/** @phpstan-ignore-next-line */
trait InsertsParticipations
{
    abstract protected function connection(): Connection;

    protected function insertParticipation(OrganizerId $organizer, ParticipationId $id, string $state = 'confirmed', ?OrderId $order = null): OrderId
    {
        $order ??= OrderId::generate();
        $person = PersonId::generate();
        $now = '2026-11-01 12:00:00+00';
        $exists = false !== $this->connection()->fetchOne('SELECT 1 FROM registration.orders WHERE id = ?', [$order->toString()]);
        if (!$exists) {
            $this->connection()->executeStatement(
                'INSERT INTO registration.orders (id, organizer_id, event_id, number, orderer_email, payment_method, amount_due_minor, amount_due_currency, form_version_id, placed_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                [$order->toString(), $organizer->toString(), EventId::generate()->toString(), 'T-'.substr($order->toString(), -12), 'fixture@example.test', 'cash', 'CZK', FormVersionId::generate()->toString(), $now],
            );
        }
        $this->connection()->executeStatement('INSERT INTO registration.person (id, organizer_id, order_id) VALUES (?, ?, ?)', [$person->toString(), $organizer->toString(), $order->toString()]);
        $this->connection()->executeStatement(
            'INSERT INTO registration.participation (id, organizer_id, order_id, person_id, event_id, activity_id, variant_id, state, hold_expires_at, registration_code, price_minor, price_currency, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [$id->toString(), $organizer->toString(), $order->toString(), $person->toString(), EventId::generate()->toString(), ActivityId::generate()->toString(), VariantId::generate()->toString(), $state, 'pending' === $state ? '2026-12-01 12:00:00+00' : null, strtoupper(substr($id->toString(), -10)), 'CZK', $now],
        );

        return $order;
    }
}
