<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\RequestReconfirmation;

use Eventor\Legal\Domain\Requirement\LegalRequirementRepository;
use Eventor\Registration\Contract\ParticipationQuery;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * For every active requirement of the document, announces the participations and orders whose latest granted
 * confirmation is on an older version (`legal.reconfirmation_requested`, through the requirement aggregate). When
 * Registration is available, cancelled, expired and transferred participations are left out. Nothing is granted.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Emits one domain event per affected requirement; there may legitimately be none when nobody confirmed the older version.')]
final readonly class RequestReconfirmationHandler
{
    public function __construct(
        private LegalRequirementRepository $requirements,
        private ReconfirmationAudienceQuery $audience,
        private TenantContext $tenant,
        private ?ParticipationQuery $participations = null,
    ) {}

    /**
     * @return array{requirements: int}
     */
    public function __invoke(RequestReconfirmation $command): array
    {
        $organizer = $this->tenant->require();
        $version = DocumentVersionId::fromString($command->versionId);

        $announced = 0;
        foreach ($this->requirements->activeOfDocument(LegalDocumentId::fromString($command->documentId)) as $requirement) {
            $who = $this->audience->forRequirement($organizer, $requirement->id(), $version);
            $participationIds = $this->active($organizer, $who['participations']);
            if ([] === $participationIds && [] === $who['orders']) {
                continue;
            }

            $requirement->requestReconfirmation($version, $participationIds, $who['orders']);
            ++$announced;
        }

        return ['requirements' => $announced];
    }

    /**
     * @param list<ParticipationId> $ids
     *
     * @return list<ParticipationId>
     */
    private function active(\Eventor\Shared\Domain\Id\OrganizerId $organizer, array $ids): array
    {
        if (null === $this->participations || [] === $ids) {
            return $ids;
        }

        $active = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($this->participations->findMany($organizer, $chunk) as $view) {
                if (!\in_array($view->state, [ParticipationState::Cancelled, ParticipationState::Expired, ParticipationState::Transferred], true)) {
                    $active[] = $view->id;
                }
            }
        }

        return $active;
    }
}
