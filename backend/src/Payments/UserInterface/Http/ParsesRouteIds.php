<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Shared\Domain\Id\AbstractId;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A route id that is not a UUID cannot name anything: 404, not a 500 from the id constructor.
 */
trait ParsesRouteIds
{
    /**
     * @template T of AbstractId
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function routeId(string $raw, string $class): AbstractId
    {
        if (!$class::isValid($raw)) {
            throw new NotFoundHttpException('Not found.');
        }

        return $class::fromString($raw);
    }
}
