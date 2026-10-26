<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http\ValueResolver;

use Eventor\Registration\Contract\ParticipationQrTokens;
use Eventor\Registration\Contract\ParticipationQuery;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\ParticipationId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Resolves the participation of a desk request from exactly one of three references in the JSON body (CHK-02, CHK-03):
 *
 *  - `qr`: the signed token `qr1.<payload>.<sig>`, verified by Registration (bad signature or an outdated version is a
 *    422 `qr_token_invalid`; the state of the participation is judged later by the rule matrix);
 *  - `code`: the registration code typed at the desk (unknown: 404);
 *  - `participationId`: an id from the search results (unknown or foreign: 404).
 *
 * It must run after the organizer argument, which activates the tenant.
 */
final readonly class CheckInParticipationResolver implements ValueResolverInterface
{
    private const array REFERENCES = ['qr', 'code', 'participationId'];

    public function __construct(
        private TenantContext $tenant,
        private ParticipationQrTokens $tokens,
        private ParticipationQuery $participations,
    ) {}

    /**
     * @return list<ResolvedParticipation>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (ResolvedParticipation::class !== $argument->getType()) {
            return [];
        }

        $payload = $request->getPayload();
        $given = [];
        foreach (self::REFERENCES as $name) {
            $value = $payload->get($name);
            if (null !== $value && '' !== $value) {
                if (!\is_string($value)) {
                    throw $this->invalid($name, 'This value must be a string.');
                }
                $given[$name] = $value;
            }
        }
        if (1 !== \count($given)) {
            throw $this->invalid('qr', 'Send exactly one of "qr", "code" and "participationId".');
        }

        $organizer = $this->tenant->require();
        $reference = reset($given);
        switch (array_key_first($given)) {
            case 'qr':
                return [new ResolvedParticipation($this->tokens->verify($organizer, $reference)->participationId)];
            case 'code':
                $id = $this->participations->findByRegistrationCode($organizer, $reference);

                return [new ResolvedParticipation($id ?? throw new NotFoundHttpException('No participation has this registration code.'))];
            default:
                if (!ParticipationId::isValid($reference)) {
                    throw $this->invalid('participationId', 'This is not a valid id.');
                }

                return [new ResolvedParticipation(ParticipationId::fromString($reference))];
        }
    }

    private function invalid(string $path, string $message): UnprocessableEntityHttpException
    {
        $violations = new ConstraintViolationList([new ConstraintViolation($message, null, [], null, $path, null)]);

        return new UnprocessableEntityHttpException('Invalid participant reference.', new ValidationFailedException(null, $violations));
    }
}
