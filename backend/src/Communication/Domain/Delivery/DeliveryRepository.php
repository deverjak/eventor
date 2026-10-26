<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Delivery;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Id\OrderId;

interface DeliveryRepository
{
    public function add(Delivery $delivery): void;

    /** The delivery of the current organizer (the tenant filter scopes it), or null. */
    public function find(DeliveryId $id): ?Delivery;

    /** Same, with a row lock for the duration of the transaction (a delivery is attempted by one worker at a time). */
    public function findForUpdate(DeliveryId $id): ?Delivery;

    public function existsWithKey(string $idempotencyKey): bool;

    /**
     * @return list<Delivery> failed deliveries, of one campaign when given
     */
    public function failed(?CampaignId $campaign): array;

    /**
     * @return list<Delivery> waiting deliveries of one kind for an activity (pre-event reminders)
     */
    public function waitingForActivity(ActivityId $activity, string $trigger): array;

    /**
     * @return list<Delivery> waiting marketing deliveries of an order
     */
    public function waitingMarketingOfOrder(OrderId $order): array;

    /**
     * How the other deliveries of a campaign stand (the one in hand is excluded, its change is not flushed yet).
     *
     * @return array{waiting: int, sent: int, skipped: int}
     */
    public function progressOfCampaign(CampaignId $campaign, DeliveryId $except): array;
}
