<?php

declare(strict_types=1);

namespace Eventor\Attention\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\AttentionItemId;

/**
 * No such attention item.
 */
#[AsProblem(code: 'attention_item_not_found', status: 404)]
final class AttentionItemNotFound extends DomainException
{
    public static function forId(AttentionItemId $id): self
    {
        return new self(\sprintf('Attention item "%s" was not found.', $id->toString()));
    }
}
