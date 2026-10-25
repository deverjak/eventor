<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http;

use Eventor\Identity\Application\Invite\InviteMember;
use Eventor\Identity\Application\Invite\RevokeInvitation;
use Eventor\Identity\Application\Query\TeamQuery;
use Eventor\Identity\Application\Team\ChangeMembership;
use Eventor\Identity\Application\Team\RemoveMember;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\UserInterface\Http\Request\ChangeMemberRequest;
use Eventor\Identity\UserInterface\Http\Request\InviteMemberRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Security\IdentifiableUser;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\UserAccountId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * ORG-03: the team of an organizer: members, roles, optional event scope, invitations by e-mail. Owner only
 * (`team.manage`).
 */
#[AsController]
final class TeamController extends AbstractController
{
    private const string ORG = '/api/admin/org/{orgSlug}';

    public function __construct(
        private readonly TeamQuery $team,
        private readonly CommandBus $commands,
        /** Hand the accept token to the caller (development and test stacks, where no mail is sent). */
        #[Autowire('%eventor.identity.expose_invitation_token%')]
        private readonly bool $exposeInvitationToken,
    ) {}

    #[Route(self::ORG.'/members', name: 'api_admin_team_members', methods: ['GET'])]
    #[RequiresPermission(Permission::TeamManage)]
    #[OA\Get(
        summary: 'Members of the team with role and event scope',
        tags: ['team'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'items: membershipId, userAccountId, email, displayName, role, eventScope, joinedAt'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: owner only'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function members(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return $this->json(['items' => $this->team->members($organizer->id)]);
    }

    #[Route(self::ORG.'/members/{membershipId}', name: 'api_admin_team_member_change', requirements: ['membershipId' => Requirement::UUID], methods: ['PUT'])]
    #[RequiresPermission(Permission::TeamManage)]
    #[OA\Put(
        summary: 'Change the role and the event scope of a member',
        tags: ['team'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'membershipId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['role'],
            properties: [
                new OA\Property(property: 'role', type: 'string', enum: ['OWNER', 'EVENT_MANAGER', 'FINANCE', 'CHECK_IN']),
                new OA\Property(property: 'eventScope', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), description: 'Empty = all events; only EVENT_MANAGER and CHECK_IN may be restricted'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'The updated member list'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / membership_not_found'),
            new OA\Response(response: 409, description: 'last_owner_required'),
            new OA\Response(response: 422, description: 'validation_failed or invalid_event_scope'),
        ],
    )]
    public function change(#[CurrentOrganizer] OrganizerView $organizer, string $membershipId, #[MapRequestPayload] ChangeMemberRequest $body): JsonResponse
    {
        $this->commands->dispatch(new ChangeMembership($organizer->id, MembershipId::fromString($membershipId), $body->role, $this->events($body->eventScope)));

        return $this->json(['items' => $this->team->members($organizer->id)]);
    }

    #[Route(self::ORG.'/members/{membershipId}', name: 'api_admin_team_member_remove', requirements: ['membershipId' => Requirement::UUID], methods: ['DELETE'])]
    #[RequiresPermission(Permission::TeamManage)]
    #[OA\Delete(
        summary: 'Remove a member from the team (their access ends at once)',
        tags: ['team'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'membershipId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Removed'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / membership_not_found'),
            new OA\Response(response: 409, description: 'last_owner_required'),
        ],
    )]
    public function remove(#[CurrentOrganizer] OrganizerView $organizer, string $membershipId): Response
    {
        $this->commands->dispatch(new RemoveMember($organizer->id, MembershipId::fromString($membershipId)));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(self::ORG.'/invitations', name: 'api_admin_team_invitations', methods: ['GET'])]
    #[RequiresPermission(Permission::TeamManage)]
    #[OA\Get(
        summary: 'Open invitations',
        tags: ['team'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'items: invitationId, email, role, eventScope, invitedAt, expiresAt'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function invitations(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return $this->json(['items' => $this->team->pendingInvitations($organizer->id)]);
    }

    #[Route(self::ORG.'/invitations', name: 'api_admin_team_invite', methods: ['POST'])]
    #[RequiresPermission(Permission::TeamManage)]
    #[OA\Post(
        summary: 'Invite a person by e-mail (the message is sent by Communication; a pending invitation for the same e-mail is replaced)',
        tags: ['team'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'role'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'role', type: 'string', enum: ['OWNER', 'EVENT_MANAGER', 'FINANCE', 'CHECK_IN']),
                new OA\Property(property: 'eventScope', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'invitationId, expiresAt (and `acceptToken` on development and test stacks only)'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 409, description: 'already_member'),
            new OA\Response(response: 422, description: 'validation_failed or invalid_event_scope'),
        ],
    )]
    public function invite(#[CurrentOrganizer] OrganizerView $organizer, #[CurrentUser] UserInterface $user, #[MapRequestPayload] InviteMemberRequest $body): JsonResponse
    {
        /** @var array{invitationId: string, token: string, expiresAt: string} $result */
        $result = $this->commands->dispatch(new InviteMember(
            $organizer->id,
            $body->email,
            $body->role,
            $this->events($body->eventScope),
            $this->accountOf($user),
        ));

        $response = ['invitationId' => $result['invitationId'], 'expiresAt' => $result['expiresAt']];
        if ($this->exposeInvitationToken) {
            $response['acceptToken'] = $result['token'];
        }

        return $this->json($response, 201);
    }

    #[Route(self::ORG.'/invitations/{invitationId}', name: 'api_admin_team_invitation_revoke', requirements: ['invitationId' => Requirement::UUID], methods: ['DELETE'])]
    #[RequiresPermission(Permission::TeamManage)]
    #[OA\Delete(
        summary: 'Revoke an open invitation',
        tags: ['team'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'invitationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Revoked'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / invitation_not_found'),
        ],
    )]
    public function revoke(#[CurrentOrganizer] OrganizerView $organizer, string $invitationId): Response
    {
        $this->commands->dispatch(new RevokeInvitation($organizer->id, InvitationId::fromString($invitationId)));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<EventId>
     */
    private function events(array $ids): array
    {
        return array_map(static fn(string $id): EventId => EventId::fromString($id), $ids);
    }

    private function accountOf(UserInterface $user): UserAccountId
    {
        \assert($user instanceof IdentifiableUser);

        return UserAccountId::fromString($user->accountId());
    }
}
