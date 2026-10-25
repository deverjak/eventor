<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Catalog\Contract\ProgramVisibility;
use Eventor\Catalog\Domain\Program\ProgramItemDetails;
use Eventor\Shared\Domain\Id\ActivityId;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of the program endpoints (PRG-01..03). `important` marks a change that should be announced to the affected
 * participants (the link to an activity decides who they are).
 */
final readonly class ProgramItemInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $title,
        public \DateTimeImmutable $startsAt,
        #[Assert\Length(max: 20000)]
        public ?string $description = null,
        #[Assert\Length(max: 200)]
        public ?string $location = null,
        #[Assert\Length(max: 200)]
        public ?string $responsible = null,
        public ?\DateTimeImmutable $endsAt = null,
        #[Assert\Uuid]
        public ?string $activityId = null,
        #[Assert\Choice(choices: ['public', 'participants'])]
        public string $visibility = 'public',
        public bool $important = false,
    ) {}

    public function toDetails(): ProgramItemDetails
    {
        return new ProgramItemDetails(
            trim($this->title),
            $this->description,
            $this->location,
            $this->responsible,
            $this->startsAt,
            $this->endsAt,
            null === $this->activityId ? null : ActivityId::fromString($this->activityId),
            ProgramVisibility::tryFrom($this->visibility) ?? ProgramVisibility::Public,
        );
    }
}
