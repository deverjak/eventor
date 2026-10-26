<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Doctrine;

use Eventor\Registration\Domain\Answer\AnswerSetId;
use Eventor\Shared\Infrastructure\Doctrine\AbstractIdType;

final class AnswerSetIdType extends AbstractIdType
{
    public const string NAME = 'answer_set_id';

    protected function idClass(): string
    {
        return AnswerSetId::class;
    }
}
