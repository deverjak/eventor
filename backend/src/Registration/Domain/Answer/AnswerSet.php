<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Answer;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * The answers of one subject (the order or a person) to one form version, in the stored shape
 * `{ "<field_key>": { "t": "<type>", "v": <value> } }`. A correction never edits a set: it adds a new one and marks
 * the old one as superseded, so the history of answers stays (chapter 03, `answer_set`).
 */
#[ORM\Entity]
#[ORM\Table(name: 'answer_set', schema: 'registration')]
class AnswerSet implements TenantScoped
{
    /** Written by AnswerSetRepository::replace() with plain SQL (the unique index of current sets forbids the ORM's insert-first order). */
    #[ORM\Column(type: 'answer_set_id', nullable: true)]
    private ?AnswerSetId $supersededById = null;

    /**
     * @param array<string, array{t: string, v: mixed}> $answers
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'answer_set_id')]
        private AnswerSetId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'order_id')]
        private OrderId $orderId,
        #[ORM\Column(type: 'string', length: 16)]
        private string $scope,
        #[ORM\Column(type: 'guid')]
        private string $subjectId,
        #[ORM\Column(type: 'form_version_id')]
        private FormVersionId $formVersionId,
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        private array $answers,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    public function id(): AnswerSetId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    public function formVersionId(): FormVersionId
    {
        return $this->formVersionId;
    }

    /**
     * @return array<string, array{t: string, v: mixed}>
     */
    public function answers(): array
    {
        return $this->answers;
    }

    /** Anonymisation (PRV-04): the answers are emptied, the row stays. */
    public function wipe(): void
    {
        $this->answers = [];
    }
}
