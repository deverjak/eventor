<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Legal;

use Eventor\Tests\Api\Support\ApiClient;
use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Api\Support\ApiTestCase;
use Eventor\Tests\Integration\Legal\Support\Json;

/**
 * Black-box tests of the Legal API (LEG-01 to LEG-07, LEG-11): real HTTP against the running stack. The data is created
 * through the API with unique titles; the admin zone needs a session, the public read does not.
 */
final class LegalApiTest extends ApiTestCase
{
    private const string ADMIN = '/api/admin/org/api-alpha/legal';
    private const string PUBLIC = '/api/public/orgs/api-alpha/legal';
    private const string SOME_EVENT = '0197a000-0000-7000-8000-000000000001';
    private const string UNKNOWN = '0197a000-0000-7000-8000-00000000dead';

    public function test_create_publish_require_read_publicly_then_publish_a_new_version_with_reconfirmation(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);
        $title = 'Terms '.bin2hex(random_bytes(4));
        $textOne = "# $title\n\nVersion one.";

        // 1. a document, then its first version
        $created = $owner->post(self::ADMIN.'/documents', ['level' => 'organizer', 'title' => $title]);
        self::assertSame(201, $created->status, $created->body);
        $documentId = Json::string($created->json(), 'documentId');

        $draft = $owner->post(self::ADMIN.'/documents/'.$documentId.'/versions', ['kind' => 'text', 'text' => $textOne]);
        self::assertSame(201, $draft->status, $draft->body);
        $v1 = $draft->json();
        self::assertSame(1, $v1['number']);
        self::assertSame(hash('sha256', $textOne), $v1['sha256']);
        $v1Id = Json::string($v1, 'versionId');

        // a draft is invisible to the public
        $this->assertProblem($this->anonymous()->get(self::PUBLIC.'/versions/'.$v1Id), 404, 'not_found');

        $published = $owner->post(self::ADMIN.'/documents/'.$documentId.'/versions/'.$v1Id.'/publish', []);
        self::assertSame(200, $published->status, $published->body);

        // 2. required at organizer level
        $required = $owner->post(self::ADMIN.'/requirements', ['documentId' => $documentId, 'type' => 'terms', 'purpose' => 'Conditions of participation', 'confirmer' => 'orderer', 'mandatory' => true]);
        self::assertSame(201, $required->status, $required->body);
        $requirementId = Json::string($required->json(), 'requirementId');
        $listed = $owner->get(self::ADMIN.'/requirements?documentId='.$documentId)->json();
        self::assertSame([$requirementId], Json::column($listed['items'] ?? null, 'id'));
        self::assertTrue(Json::at($listed, 'items', 0, 'mandatory'));

        // inherited by any event of the organizer
        $preview = $owner->get(self::ADMIN.'/preview?eventId='.self::SOME_EVENT);
        self::assertSame(200, $preview->status, $preview->body);
        self::assertContains($requirementId, Json::column($preview->json()['orderLevel'] ?? null, 'requirementId'));

        // 3. public read by id and by hash, without a session
        $public = $this->anonymous()->get(self::PUBLIC.'/versions/'.$v1Id);
        self::assertSame(200, $public->status, $public->body);
        self::assertSame($textOne, $public->json()['text']);
        self::assertSame($v1['sha256'], $public->json()['sha256']);
        $raw = $this->anonymous()->get(self::PUBLIC.'/versions/'.$v1Id.'/content');
        self::assertSame(200, $raw->status);
        self::assertSame($textOne, $raw->body);
        self::assertSame('"'.Json::string($v1, 'sha256').'"', $raw->header('ETag'));
        self::assertSame($textOne, $this->anonymous()->get(self::PUBLIC.'/content/'.Json::string($v1, 'sha256'))->body);

        // 4. version two with reconfirmation
        $draft2 = $owner->post(self::ADMIN.'/documents/'.$documentId.'/versions', ['kind' => 'text', 'text' => "# $title\n\nVersion two."])->json();
        self::assertSame(2, $draft2['number']);
        $v2Id = Json::string($draft2, 'versionId');
        $published2 = $owner->post(self::ADMIN.'/documents/'.$documentId.'/versions/'.$v2Id.'/publish', ['requiresReconfirmation' => true]);
        self::assertSame(200, $published2->status, $published2->body);
        self::assertTrue($published2->json()['requiresReconfirmation']);

        $document = $owner->get(self::ADMIN.'/documents/'.$documentId)->json();
        self::assertSame($v2Id, Json::at($document, 'currentVersion', 'id'));
        self::assertSame([2, 1], Json::column($document['versions'] ?? null, 'number'));
        self::assertSame(['published', 'published'], Json::column($document['versions'] ?? null, 'state'));
        // the old version stays readable and unchanged
        self::assertSame($textOne, $this->anonymous()->get(self::PUBLIC.'/versions/'.$v1Id)->json()['text']);
        // the preview now points to the new version
        $after = Json::array($owner->get(self::ADMIN.'/preview?eventId='.self::SOME_EVENT)->json(), 'orderLevel');
        $index = array_search($requirementId, Json::column($after, 'requirementId'), true);
        self::assertNotFalse($index);
        self::assertSame($v2Id, Json::at($after, $index, 'versionId'));

        // 5. nobody has confirmed anything
        $confirmations = $owner->get(self::ADMIN.'/confirmations?requirementId='.$requirementId);
        self::assertSame(200, $confirmations->status);
        self::assertSame([], $confirmations->json()['items']);

        // change and archive the requirement
        self::assertSame(204, $owner->request('PUT', self::ADMIN.'/requirements/'.$requirementId, ['purpose' => 'Conditions (updated)', 'confirmer' => 'each_participant', 'mandatory' => true])->status);
        self::assertSame(204, $owner->request('DELETE', self::ADMIN.'/requirements/'.$requirementId)->status);
        self::assertSame([], $owner->get(self::ADMIN.'/requirements?documentId='.$documentId)->json()['items']);
    }

    public function test_a_pdf_version_is_uploaded_and_served_with_its_media_type(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);
        $documentId = $this->newDocument($owner);
        $pdf = "%PDF-1.4\n% api test ".bin2hex(random_bytes(8))."\n%%EOF";

        $draft = $owner->post(self::ADMIN.'/documents/'.$documentId.'/versions', ['kind' => 'pdf', 'pdfBase64' => base64_encode($pdf)]);
        self::assertSame(201, $draft->status, $draft->body);
        $owner->post(self::ADMIN.'/documents/'.$documentId.'/versions/'.Json::string($draft->json(), 'versionId').'/publish', []);

        $raw = $this->anonymous()->get(self::PUBLIC.'/content/'.hash('sha256', $pdf));
        self::assertSame(200, $raw->status);
        self::assertStringStartsWith('application/pdf', (string) $raw->header('Content-Type'));
        self::assertSame($pdf, $raw->body);
    }

    public function test_a_voluntary_consent_cannot_be_mandatory_or_prechecked(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);
        $documentId = $this->newDocument($owner);

        $mandatory = $owner->post(self::ADMIN.'/requirements', ['documentId' => $documentId, 'type' => 'consent', 'purpose' => 'Newsletter', 'mandatory' => true]);
        $problem = $this->assertProblem($mandatory, 422, 'validation_failed');
        self::assertContains('mandatory', Json::column($problem['violations'] ?? null, 'path'));

        $prechecked = $owner->post(self::ADMIN.'/requirements', ['documentId' => $documentId, 'type' => 'likeness', 'purpose' => 'Photos', 'preChecked' => true]);
        self::assertContains('preChecked', Json::column($this->assertProblem($prechecked, 422, 'validation_failed')['violations'] ?? null, 'path'));

        $voluntary = $owner->post(self::ADMIN.'/requirements', ['documentId' => $documentId, 'type' => 'likeness', 'purpose' => 'Photos', 'mandatory' => false]);
        self::assertSame(201, $voluntary->status, $voluntary->body);
        $items = $owner->get(self::ADMIN.'/requirements?documentId='.$documentId)->json();
        self::assertTrue(Json::at($items, 'items', 0, 'revocable'), 'voluntary consents are revocable by default');
    }

    public function test_error_cases_of_documents_and_versions(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);
        $documentId = $this->newDocument($owner);
        $versions = self::ADMIN.'/documents/'.$documentId.'/versions';

        $this->assertProblem($owner->post(self::ADMIN.'/documents', ['level' => 'event', 'title' => 'No scope']), 422, 'invalid_legal_document');
        $this->assertProblem($owner->post(self::ADMIN.'/documents', ['level' => 'bogus', 'title' => 'x']), 422, 'validation_failed');
        $this->assertProblem($owner->post($versions, ['kind' => 'pdf', 'pdfBase64' => base64_encode('not a pdf')]), 422, 'invalid_legal_document');
        $this->assertProblem($owner->post($versions, ['kind' => 'external_link', 'url' => 'http://insecure.example/terms']), 422, 'invalid_legal_document');
        $this->assertProblem($owner->post($versions, ['kind' => 'text', 'text' => '   ']), 422, 'invalid_legal_document');
        $this->assertProblem($owner->get(self::ADMIN.'/documents/'.self::UNKNOWN), 404, 'legal_document_not_found');
        $this->assertProblem($owner->post($versions.'/'.self::UNKNOWN.'/publish', []), 404, 'legal_document_not_found');

        $draft = $owner->post($versions, ['kind' => 'text', 'text' => 'once'])->json();
        $publishUrl = $versions.'/'.Json::string($draft, 'versionId').'/publish';
        self::assertSame(200, $owner->post($publishUrl, [])->status);
        $this->assertProblem($owner->post($publishUrl, []), 409, 'document_version_already_published');
    }

    public function test_public_reads_of_unknown_content_are_404(): void
    {
        $anonymous = $this->anonymous();

        $this->assertProblem($anonymous->get(self::PUBLIC.'/versions/'.self::UNKNOWN), 404, 'not_found');
        $this->assertProblem($anonymous->get(self::PUBLIC.'/content/'.str_repeat('0', 64)), 404, 'not_found');
        $this->assertProblem($anonymous->get('/api/public/orgs/no-such-organizer/legal/content/'.str_repeat('0', 64)), 404, 'not_found');
    }

    public function test_anonymous_access_to_the_admin_api_is_401(): void
    {
        $anonymous = $this->anonymous();

        $this->assertProblem($anonymous->get(self::ADMIN.'/documents'), 401, 'unauthorized');
        $this->assertProblem($anonymous->get(self::ADMIN.'/requirements'), 401, 'unauthorized');
        $this->assertProblem($anonymous->get(self::ADMIN.'/confirmations'), 401, 'unauthorized');
    }

    public function test_another_organizer_cannot_see_or_use_documents_of_this_one(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);
        $beta = $this->loginAs(ApiFixtures::BETA);
        $documentId = $this->newDocument($alpha);
        $draft = $alpha->post(self::ADMIN.'/documents/'.$documentId.'/versions', ['kind' => 'text', 'text' => 'alpha secret '.bin2hex(random_bytes(4))])->json();
        $versionId = Json::string($draft, 'versionId');
        $alpha->post(self::ADMIN.'/documents/'.$documentId.'/versions/'.$versionId.'/publish', []);

        // beta addressing alpha's tenant gets a 404 like for a missing one
        $this->assertProblem($beta->get(self::ADMIN.'/documents'), 404, 'not_found');
        $this->assertProblem($beta->post(self::ADMIN.'/documents', ['level' => 'organizer', 'title' => 'x']), 404, 'not_found');
        // in its own tenant the document does not exist
        $this->assertProblem($beta->get('/api/admin/org/api-beta/legal/documents/'.$documentId), 404, 'legal_document_not_found');
        $this->assertProblem($beta->post('/api/admin/org/api-beta/legal/documents/'.$documentId.'/versions', ['kind' => 'text', 'text' => 'hijack']), 404, 'legal_document_not_found');
        // and the content is not served under its public address
        $this->assertProblem($this->anonymous()->get('/api/public/orgs/api-beta/legal/versions/'.$versionId), 404, 'not_found');
        self::assertNotContains($documentId, Json::column($beta->get('/api/admin/org/api-beta/legal/documents')->json()['items'] ?? null, 'id'));
    }

    private function newDocument(ApiClient $owner): string
    {
        $created = $owner->post(self::ADMIN.'/documents', ['level' => 'organizer', 'title' => 'Doc '.bin2hex(random_bytes(4))]);
        self::assertSame(201, $created->status, $created->body);

        return Json::string($created->json(), 'documentId');
    }
}
