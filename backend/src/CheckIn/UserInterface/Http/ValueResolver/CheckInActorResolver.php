<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http\ValueResolver;

use Eventor\Identity\Contract\DevicePrincipal;
use Eventor\Shared\Application\Security\IdentifiableUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Controller arguments of type CheckInActor come from the security token: a device principal or a team member.
 */
final readonly class CheckInActorResolver implements ValueResolverInterface
{
    public function __construct(private Security $security) {}

    /**
     * @return list<CheckInActor>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (CheckInActor::class !== $argument->getType()) {
            return [];
        }

        $user = $this->security->getUser() ?? throw new AccessDeniedException('Authentication required.');
        if ($user instanceof DevicePrincipal) {
            return [new CheckInActor(null, $user->label(), $user->checkInListId())];
        }
        if ($user instanceof IdentifiableUser) {
            return [new CheckInActor($user->accountId(), null, null)];
        }

        throw new AccessDeniedException('Unknown principal.');
    }
}
