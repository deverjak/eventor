<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;

/**
 * The registration form for a chosen combination of activities and variants (FRM-11, PUB): which fields each person
 * has to answer, which of them the order contact answers, and which legal documents have to be confirmed by whom
 * (REG-06). `formToken` is the signed time stamp of the antispam (public form only).
 */
final readonly class RegistrationPreviewView
{
    /**
     * @param list<PreviewFieldView>       $orderFields
     * @param list<PreviewRequirementView> $orderRequirements
     * @param list<PreviewItemView>        $items
     */
    public function __construct(
        public EventId $eventId,
        public FormVersionId $formVersionId,
        public array $orderFields,
        public array $orderRequirements,
        public array $items,
        public ?string $formToken,
    ) {}
}
