<?php

declare(strict_types=1);

namespace Eventor\Privacy\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\DataSubjectRequestId;

/**
 * No such data subject request.
 */
#[AsProblem(code: 'data_subject_request_not_found', status: 404)]
final class DataSubjectRequestNotFound extends DomainException
{
    public static function forId(DataSubjectRequestId $id): self
    {
        return new self(\sprintf('Data subject request "%s" was not found.', $id->toString()));
    }
}
