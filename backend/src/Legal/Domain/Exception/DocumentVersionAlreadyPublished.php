<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * A published version is immutable (LEG-03): it cannot be published again or replaced.
 */
#[AsProblem(code: 'document_version_already_published', status: 409)]
final class DocumentVersionAlreadyPublished extends DomainException {}
