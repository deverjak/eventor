<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\UpdateProfile;

use Eventor\Identity\Contract\Exception\OrganizerNotFound;
use Eventor\Identity\Domain\Organizer\OrganizerProfile;
use Eventor\Identity\Domain\Organizer\OrganizerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateOrganizerProfileHandler
{
    public function __construct(private OrganizerRepository $organizers) {}

    public function __invoke(UpdateOrganizerProfile $command): void
    {
        $organizer = $this->organizers->findById($command->organizerId) ?? throw OrganizerNotFound::forId($command->organizerId);

        $organizer->updateProfile(new OrganizerProfile(
            trim($command->name),
            $this->nullIfBlank($command->contactEmail),
            $this->nullIfBlank($command->contactPhone),
            $this->nullIfBlank($command->legalName),
            $this->nullIfBlank($command->companyId),
            $this->nullIfBlank($command->vatId),
            $this->nullIfBlank($command->billingStreet),
            $this->nullIfBlank($command->billingCity),
            $this->nullIfBlank($command->billingPostalCode),
            $this->nullIfBlank($command->billingCountry),
            $this->nullIfBlank($command->logoUrl),
            $this->nullIfBlank($command->coverImageUrl),
            $this->nullIfBlank($command->brandColor),
        ));
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
