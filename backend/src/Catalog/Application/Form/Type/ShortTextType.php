<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;

#[AsFieldType('short_text')]
final class ShortTextType extends TextType
{
    protected function defaultMaxLength(): int
    {
        return 255;
    }
}
