<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\DataSubjectRequestId;

final class DataSubjectRequestIdType extends AbstractIdType
{
    public const string NAME = 'data_subject_request_id';

    protected function idClass(): string
    {
        return DataSubjectRequestId::class;
    }
}
