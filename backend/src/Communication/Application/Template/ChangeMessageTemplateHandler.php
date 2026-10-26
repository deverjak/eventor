<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Template;

use Eventor\Communication\Application\Rendering\SandboxedTemplateRenderer;
use Eventor\Communication\Domain\Exception\MessageTemplateNotFound;
use Eventor\Communication\Domain\Template\MessageTemplateId;
use Eventor\Communication\Domain\Template\MessageTemplateRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ChangeMessageTemplateHandler
{
    public function __construct(
        private MessageTemplateRepository $templates,
        private SandboxedTemplateRenderer $renderer,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ChangeMessageTemplate $command): void
    {
        $template = $this->templates->find(MessageTemplateId::fromString($command->templateId)) ?? throw MessageTemplateNotFound::forId($command->templateId);
        $this->renderer->assertValid($command->subject, $command->body);
        $template->change($command->subject, $command->body, \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
