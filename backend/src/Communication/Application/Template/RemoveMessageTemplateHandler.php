<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Template;

use Eventor\Communication\Domain\Exception\MessageTemplateNotFound;
use Eventor\Communication\Domain\Template\MessageTemplateId;
use Eventor\Communication\Domain\Template\MessageTemplateRepository;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('The removed row leaves the unit of work, so its event could not be collected (and Audit has no write Contract); the removal of an own wording is not audited.')]
final readonly class RemoveMessageTemplateHandler
{
    public function __construct(private MessageTemplateRepository $templates) {}

    public function __invoke(RemoveMessageTemplate $command): void
    {
        $template = $this->templates->find(MessageTemplateId::fromString($command->templateId)) ?? throw MessageTemplateNotFound::forId($command->templateId);
        $this->templates->remove($template);
    }
}
