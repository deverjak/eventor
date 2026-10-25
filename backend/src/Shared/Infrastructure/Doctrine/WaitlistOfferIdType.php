<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\WaitlistOfferId;

final class WaitlistOfferIdType extends AbstractIdType
{
    public const string NAME = 'waitlist_offer_id';

    protected function idClass(): string
    {
        return WaitlistOfferId::class;
    }
}
