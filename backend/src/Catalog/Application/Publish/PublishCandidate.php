<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publish;

use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Catalog\Domain\Structure\Activity;
use Eventor\Catalog\Domain\Structure\Event;
use Eventor\Catalog\Domain\Structure\Variant;

/**
 * Everything a publication rule may look at.
 */
final readonly class PublishCandidate
{
    /**
     * @param list<Activity> $activities
     * @param list<Variant>  $variants
     */
    public function __construct(
        public Event $event,
        public array $activities,
        public array $variants,
        public FormSchema $form,
    ) {}
}
