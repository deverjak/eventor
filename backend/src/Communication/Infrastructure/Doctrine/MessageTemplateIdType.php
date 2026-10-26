<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Doctrine;

use Eventor\Communication\Domain\Template\MessageTemplateId;
use Eventor\Shared\Infrastructure\Doctrine\AbstractIdType;

final class MessageTemplateIdType extends AbstractIdType
{
    public const string NAME = 'message_template_id';

    protected function idClass(): string
    {
        return MessageTemplateId::class;
    }
}
