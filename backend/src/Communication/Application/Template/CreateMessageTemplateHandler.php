<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Template;

use Eventor\Communication\Application\Rendering\SandboxedTemplateRenderer;
use Eventor\Communication\Domain\Exception\TemplateKeyTaken;
use Eventor\Communication\Domain\Template\MessageTemplate;
use Eventor\Communication\Domain\Template\MessageTemplateId;
use Eventor\Communication\Domain\Template\MessageTemplateRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateMessageTemplateHandler
{
    public function __construct(
        private MessageTemplateRepository $templates,
        private SandboxedTemplateRenderer $renderer,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{templateId: string}
     */
    public function __invoke(CreateMessageTemplate $command): array
    {
        if (null !== $this->templates->findByKey($command->key, $command->locale)) {
            throw TemplateKeyTaken::for($command->key->value, $command->locale->value);
        }
        $this->renderer->assertValid($command->subject, $command->body);

        $template = MessageTemplate::create(
            MessageTemplateId::generate(),
            $this->tenant->require(),
            $command->key,
            $command->locale,
            $command->subject,
            $command->body,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->templates->add($template);

        return ['templateId' => $template->id()->toString()];
    }
}
