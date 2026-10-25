<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\AttentionItemId;

final class AttentionItemIdType extends AbstractIdType
{
    public const string NAME = 'attention_item_id';

    protected function idClass(): string
    {
        return AttentionItemId::class;
    }
}
