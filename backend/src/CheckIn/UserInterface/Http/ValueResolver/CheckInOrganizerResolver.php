<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http\ValueResolver;

use Eventor\Identity\Contract\DevicePrincipal;
use Eventor\Identity\Contract\OrganizerDirectory;
use Eventor\Identity\Contract\OrganizerLookup;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Infrastructure\Doctrine\TenantFilterSynchronizer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The tenant of a check-in request (ADR 005, ADR 006). Team member: `{orgSlug}` + membership, otherwise 404 (a foreign
 * tenant cannot be told from a missing one). Device: the organizer of the token; a slug in the URL, if present, must be
 * that organizer's, otherwise 404 as well.
 */
#[AsTargetedValueResolver(CheckInOrganizer::RESOLVER)]
final readonly class CheckInOrganizerResolver implements ValueResolverInterface
{
    public function __construct(
        private OrganizerDirectory $directory,
        private OrganizerLookup $lookup,
        private Security $security,
        private TenantContext $tenant,
        private TenantFilterSynchronizer $filter,
    ) {}

    /**
     * @return iterable<OrganizerView>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (OrganizerView::class !== $argument->getType()) {
            throw new \LogicException(\sprintf('#[CheckInOrganizer] must be used on an argument of type %s.', OrganizerView::class));
        }

        $user = $this->security->getUser() ?? throw new AccessDeniedException('Authentication required.');
        $slug = $request->attributes->getString('orgSlug');

        if ($user instanceof DevicePrincipal) {
            $organizer = $this->lookup->get($user->organizerId());
            if ('' !== $slug && $slug !== $organizer->slug) {
                throw new NotFoundHttpException('Organizer not found.');
            }
        } else {
            $organizer = '' === $slug ? null : $this->directory->findForMember($slug, $user->getUserIdentifier());
            if (null === $organizer) {
                throw new NotFoundHttpException('Organizer not found.');
            }
        }

        $this->tenant->enter($organizer->id);
        $this->filter->sync();

        return [$organizer];
    }
}
