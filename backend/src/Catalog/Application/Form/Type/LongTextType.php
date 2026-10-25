<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;

#[AsFieldType('long_text')]
final class LongTextType extends TextType
{
    protected function defaultMaxLength(): int
    {
        return 5000;
    }
}
