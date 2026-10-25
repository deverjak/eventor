<?php

declare(strict_types=1);

namespace Eventor\Shared\UserInterface\Http;

use Eventor\Shared\Application\Idempotency\IdempotencyKey;
use Eventor\Shared\Application\Idempotency\IdempotencyKeyRequired;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Controller arguments of type IdempotencyKey come from the `Idempotency-Key` header. `?IdempotencyKey` is optional,
 * a non-nullable argument makes the header mandatory (public order creation, cash, check-in). The key is validated
 * here; replay/conflict handling happens in IdempotencyMiddleware.
 */
final readonly class IdempotencyKeyResolver implements ValueResolverInterface
{
    public const string HEADER = 'Idempotency-Key';

    /**
     * @return list<IdempotencyKey|null>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (IdempotencyKey::class !== $argument->getType()) {
            return [];
        }

        $header = $request->headers->get(self::HEADER);
        if (null === $header || '' === $header) {
            if ($argument->isNullable()) {
                return [null];
            }

            throw IdempotencyKeyRequired::create();
        }

        return [new IdempotencyKey($header)];
    }
}
