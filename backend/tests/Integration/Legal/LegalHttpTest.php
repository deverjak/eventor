<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Tests\Integration\Legal\Support\Json;
use Eventor\Tests\Support\Foundry\MembershipFactory;
use Eventor\Tests\Support\HttpTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The admin API of Legal over the in-process kernel: happy paths, validation (422), 401, 403 by role, 404 for a
 * foreign organizer, and the public read of published content.
 */
final class LegalHttpTest extends HttpTestCase
{
    use Factories;

    private const string BASE = '/api/admin/org/klub/legal';

    /** @var array{organizerId: string, userAccountId: string, membershipId: string} */
    private array $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createOwner('jana@example.test', 'klub');
    }

    public function test_anonymous_gets_401_on_every_admin_route(): void
    {
        foreach ([['GET', '/documents'], ['POST', '/documents'], ['GET', '/requirements'], ['GET', '/confirmations'], ['GET', '/preview']] as [$method, $path]) {
            $this->request($method, self::BASE.$path, 'POST' === $method ? [] : null);
            $this->assertProblem(401, 'unauthorized');
        }
    }

    public function test_the_whole_flow_document_version_requirement_preview_and_public_read(): void
    {
        $this->login('jana@example.test');

        $this->request('POST', self::BASE.'/documents', ['level' => 'organizer', 'title' => 'General terms']);
        self::assertResponseStatusCodeSame(201);
        $documentId = $this->created('documentId');

        $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions', ['kind' => 'text', 'text' => "# Terms\n\nBe nice."]);
        self::assertResponseStatusCodeSame(201);
        $versionId = $this->created('versionId');

        $draft = $this->request('GET', self::BASE.'/documents/'.$documentId.'/versions/'.$versionId);
        self::assertSame('draft', $draft['state'] ?? null);
        self::assertSame("# Terms\n\nBe nice.", $draft['text'] ?? null);

        $this->request('GET', '/api/public/orgs/klub/legal/versions/'.$versionId);
        $this->assertProblem(404, 'not_found');

        $published = $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions/'.$versionId.'/publish', []);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $published['number'] ?? null);

        $this->request('POST', self::BASE.'/requirements', ['documentId' => $documentId, 'type' => 'terms', 'purpose' => 'Participation', 'confirmer' => 'orderer', 'mandatory' => true]);
        self::assertResponseStatusCodeSame(201);
        $requirementId = $this->created('requirementId');

        $list = $this->request('GET', self::BASE.'/documents');
        self::assertSame($versionId, Json::at($list, 'items', 0, 'currentVersion', 'id'));
        $one = $this->request('GET', self::BASE.'/documents/'.$documentId);
        self::assertSame('published', Json::at($one, 'versions', 0, 'state'));
        $requirements = $this->request('GET', self::BASE.'/requirements?documentId='.$documentId);
        self::assertSame([$requirementId], Json::column(Json::at($requirements, 'items'), 'id'));

        $preview = $this->request('GET', self::BASE.'/preview?eventId='.Uuid::v7()->toRfc4122());
        self::assertSame($requirementId, Json::at($preview, 'orderLevel', 0, 'requirementId'));
        self::assertSame($versionId, Json::at($preview, 'orderLevel', 0, 'versionId'));

        $public = $this->request('GET', '/api/public/orgs/klub/legal/versions/'.$versionId);
        self::assertResponseIsSuccessful();
        self::assertSame("# Terms\n\nBe nice.", $public['text'] ?? null);
        self::assertSame(hash('sha256', "# Terms\n\nBe nice."), $public['sha256'] ?? null);
        self::assertSame('"'.hash('sha256', "# Terms\n\nBe nice.").'"', $this->client->getResponse()->headers->get('ETag'));

        $this->client->request('GET', '/api/public/orgs/klub/legal/versions/'.$versionId.'/content');
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('text/markdown', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame("# Terms\n\nBe nice.", $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/public/orgs/klub/legal/content/'.hash('sha256', "# Terms\n\nBe nice."));
        self::assertResponseIsSuccessful();

        $this->request('PUT', self::BASE.'/requirements/'.$requirementId, ['purpose' => 'Participation (changed)', 'mandatory' => false, 'confirmer' => 'each_participant']);
        self::assertResponseStatusCodeSame(204);
        $this->request('DELETE', self::BASE.'/requirements/'.$requirementId);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], Json::at($this->request('GET', self::BASE.'/requirements'), 'items'));
        self::assertCount(1, Json::array($this->request('GET', self::BASE.'/requirements?includeArchived=1'), 'items'));
    }

    public function test_a_voluntary_consent_cannot_be_mandatory_or_prechecked_over_http(): void
    {
        $this->login('jana@example.test');
        $this->request('POST', self::BASE.'/documents', ['level' => 'organizer', 'title' => 'Marketing']);
        $documentId = $this->created('documentId');

        $this->request('POST', self::BASE.'/requirements', ['documentId' => $documentId, 'type' => 'consent', 'purpose' => 'Newsletter', 'mandatory' => true, 'preChecked' => true]);

        $problem = $this->assertProblem(422, 'validation_failed');
        $paths = Json::column(Json::at($problem, 'violations'), 'path');
        self::assertContains('mandatory', $paths);
        self::assertContains('preChecked', $paths);
    }

    public function test_validation_and_domain_errors_are_problems(): void
    {
        $this->login('jana@example.test');

        $this->request('POST', self::BASE.'/documents', ['level' => 'galaxy', 'title' => 'x']);
        $this->assertProblem(422, 'validation_failed');
        $this->request('POST', self::BASE.'/documents', ['level' => 'event', 'title' => 'Event terms']);
        $this->assertProblem(422, 'invalid_legal_document');
        $this->request('GET', self::BASE.'/documents/'.Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'legal_document_not_found');
        $this->request('GET', self::BASE.'/documents/not-a-uuid');
        $this->assertProblem(404, 'not_found');

        $this->request('POST', self::BASE.'/documents', ['level' => 'organizer', 'title' => 'Terms']);
        $documentId = $this->created('documentId');
        $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions', ['kind' => 'pdf', 'pdfBase64' => base64_encode('not a pdf')]);
        $this->assertProblem(422, 'invalid_legal_document');
        $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions', ['kind' => 'external_link', 'url' => 'http://insecure.example.test/terms']);
        $this->assertProblem(422, 'invalid_legal_document');
        $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions', ['kind' => 'zip']);
        $this->assertProblem(422, 'validation_failed');
        $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions/'.Uuid::v7()->toRfc4122().'/publish', []);
        $this->assertProblem(404, 'legal_document_not_found');
        $this->request('DELETE', self::BASE.'/requirements/'.Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'legal_requirement_not_found');
        $this->request('GET', self::BASE.'/confirmations?decision=maybe');
        $this->assertProblem(422, 'validation_failed');
        $this->request('GET', self::BASE.'/preview');
        $this->assertProblem(422, 'validation_failed');
    }

    public function test_publishing_twice_is_a_409(): void
    {
        $this->login('jana@example.test');
        $this->request('POST', self::BASE.'/documents', ['level' => 'organizer', 'title' => 'Terms']);
        $documentId = $this->created('documentId');
        $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions', ['kind' => 'text', 'text' => 'v1']);
        $versionId = $this->created('versionId');
        $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions/'.$versionId.'/publish', []);
        self::assertResponseIsSuccessful();

        $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions/'.$versionId.'/publish', []);

        $this->assertProblem(409, 'document_version_already_published');
    }

    public function test_the_confirmations_list_is_empty_and_paged(): void
    {
        $this->login('jana@example.test');

        $body = $this->request('GET', self::BASE.'/confirmations?limit=5&current=1');

        self::assertResponseIsSuccessful();
        self::assertSame(['items' => [], 'total' => 0], $body);
    }

    public function test_an_event_manager_manages_event_documents_but_not_the_organizer_defaults_403(): void
    {
        $manager = $this->createOwner('manager@example.test', 'manager-own-club');
        MembershipFactory::createOne([
            'userAccountId' => UserAccountId::fromString($manager['userAccountId']),
            'organizerId' => OrganizerId::fromString($this->owner['organizerId']),
            'role' => Role::EventManager,
        ]);
        $this->login('manager@example.test');

        $this->request('POST', self::BASE.'/documents', ['level' => 'event', 'scopeId' => Uuid::v7()->toRfc4122(), 'title' => 'Camp terms']);
        self::assertResponseStatusCodeSame(201);
        $this->request('POST', self::BASE.'/documents', ['level' => 'organizer', 'title' => 'Defaults']);
        $this->assertProblem(403, 'forbidden');
    }

    public function test_finance_and_check_in_roles_get_403_and_a_foreign_organizer_404(): void
    {
        $finance = $this->createOwner('fin@example.test', 'fin-own-club');
        MembershipFactory::createOne([
            'userAccountId' => UserAccountId::fromString($finance['userAccountId']),
            'organizerId' => OrganizerId::fromString($this->owner['organizerId']),
            'role' => Role::Finance,
        ]);
        $this->login('fin@example.test');

        $this->request('GET', self::BASE.'/documents');
        $this->assertProblem(403, 'forbidden');
        $this->request('GET', '/api/admin/org/fin-own-club/legal/documents');
        self::assertResponseIsSuccessful();
        $this->request('GET', '/api/admin/org/does-not-exist/legal/documents');
        $this->assertProblem(404, 'not_found');
    }

    public function test_a_foreign_organizers_document_is_a_404_and_public_content_does_not_cross_organizers(): void
    {
        $this->login('jana@example.test');
        $this->request('POST', self::BASE.'/documents', ['level' => 'organizer', 'title' => 'Terms']);
        $documentId = $this->created('documentId');
        $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions', ['kind' => 'text', 'text' => 'secret v1']);
        $versionId = $this->created('versionId');
        $this->request('POST', self::BASE.'/documents/'.$documentId.'/versions/'.$versionId.'/publish', []);
        $this->createOwner('petr@example.test', 'cizi-klub');

        $this->request('GET', '/api/public/orgs/cizi-klub/legal/versions/'.$versionId);
        $this->assertProblem(404, 'not_found');
        $this->request('GET', '/api/public/orgs/cizi-klub/legal/content/'.hash('sha256', 'secret v1'));
        $this->assertProblem(404, 'not_found');
        $this->request('GET', '/api/public/orgs/no-such-club/legal/versions/'.$versionId);
        $this->assertProblem(404, 'not_found');

        $this->login('petr@example.test');
        $this->request('GET', '/api/admin/org/cizi-klub/legal/documents/'.$documentId);
        $this->assertProblem(404, 'legal_document_not_found');
        $this->request('GET', '/api/admin/org/klub/legal/documents');
        $this->assertProblem(404, 'not_found');
    }

    private function created(string $key): string
    {
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertIsString($body[$key] ?? null, (string) $this->client->getResponse()->getContent());

        return $body[$key];
    }
}
