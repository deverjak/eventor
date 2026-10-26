<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Registration;

use Eventor\Communication\Application\Port\ParticipationFacts;
use Eventor\Registration\Contract\ParticipationQuery;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Reads participations through Registration's Contract (chunked, the facade takes a bounded list).
 */
#[AsAlias(ParticipationFacts::class)]
final readonly class RegistrationParticipationFacts implements ParticipationFacts
{
    public function __construct(private ParticipationQuery $participations) {}

    public function find(OrganizerId $organizer, array $ids): array
    {
        $views = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            array_push($views, ...$this->participations->findMany($organizer, $chunk));
        }

        return $views;
    }
}
