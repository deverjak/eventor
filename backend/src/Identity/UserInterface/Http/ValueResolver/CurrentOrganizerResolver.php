<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http\ValueResolver;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\OrganizerDirectory;
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
 * Tenant resolution from the URL (ADR 005). For `#[CurrentOrganizer] OrganizerView $organizer` on a route with
 * `{orgSlug}`:
 *
 *  - no signed-in user -> 401 (the firewall normally answers first),
 *  - unknown slug or the user is no member -> 404, so foreign tenants cannot be told from missing ones,
 *  - otherwise the tenant is activated: TenantContext + Doctrine `organizer` filter for the rest of the request.
 *
 * Targeted: it only runs for arguments that carry the attribute, never as a fallback for other arguments.
 */
#[AsTargetedValueResolver(CurrentOrganizer::RESOLVER)]
final readonly class CurrentOrganizerResolver implements ValueResolverInterface
{
    public function __construct(
        private OrganizerDirectory $organizers,
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
            throw new \LogicException(\sprintf('#[CurrentOrganizer] must be used on an argument of type %s, "%s" given.', OrganizerView::class, $argument->getType() ?? 'none'));
        }

        $user = $this->security->getUser() ?? throw new AccessDeniedException('Authentication required.');
        $slug = $request->attributes->getString('orgSlug');

        $organizer = '' === $slug ? null : $this->organizers->findForMember($slug, $user->getUserIdentifier());
        if (null === $organizer) {
            throw new NotFoundHttpException('Organizer not found.');
        }

        $this->tenant->enter($organizer->id);
        $this->filter->sync();

        return [$organizer];
    }
}
