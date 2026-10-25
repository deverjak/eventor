<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal\Support;

use Eventor\Legal\Application\CreateDocument\CreateLegalDocument;
use Eventor\Legal\Application\DefineRequirement\DefineRequirement;
use Eventor\Legal\Application\DraftVersion\DraftDocumentVersion;
use Eventor\Legal\Application\PublishVersion\PublishDocumentVersion;
use Eventor\Legal\Contract\ConfirmationChannel;
use Eventor\Legal\Contract\ConfirmationToRecord;
use Eventor\Legal\Contract\Confirmer;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

/**
 * Helpers of the Legal integration tests: an organizer, documents with published versions, requirements, and decisions
 * recorded through the real `ConfirmationRecorder` facade in a command transaction.
 */
abstract class LegalTestCase extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RecordedSpy::$received = [];
        WithdrawnSpy::$received = [];
        ReconfirmationSpy::$received = [];
        FakeExternalDocumentFetcher::reset();
    }

    protected function organizer(string $slug = 'klub'): OrganizerId
    {
        return OrganizerId::fromString($this->createOwner('owner@'.$slug.'.test', $slug)['organizerId']);
    }

    /**
     * Integration events of a class that were sent to the in-memory transport of the Legal module.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    protected function published(string $transport, string $class): array
    {
        $inMemory = static::getContainer()->get('messenger.transport.'.$transport);
        \assert($inMemory instanceof InMemoryTransport);

        $found = [];
        foreach ($inMemory->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof $class) {
                $found[] = $message;
            }
        }

        return $found;
    }

    protected function dispatch(OrganizerId $organizer, object $command): mixed
    {
        try {
            return $this->commandBus()->dispatch($command, TenantStamp::of($organizer));
        } finally {
            // A command is a request of its own: the next one must not see this one's entities in the identity map.
            $em = static::getContainer()->get('doctrine.orm.entity_manager');
            \assert($em instanceof \Doctrine\ORM\EntityManagerInterface);
            $em->clear();
        }
    }

    /**
     * @return array{documentId: string, versionId: string}
     */
    protected function publishedDocument(OrganizerId $organizer, DocumentLevel $level = DocumentLevel::Organizer, ?string $scopeId = null, string $text = "# Terms\n\nVersion one.", string $title = 'Terms', ?string $replaces = null): array
    {
        $scopeId ??= DocumentLevel::Organizer === $level ? $organizer->toString() : Uuid::v7()->toRfc4122();

        /** @var array{documentId: string} $document */
        $document = $this->dispatch($organizer, new CreateLegalDocument($level, $scopeId, $title, $replaces));
        $versionId = $this->publishVersion($organizer, $document['documentId'], $text);

        return ['documentId' => $document['documentId'], 'versionId' => $versionId];
    }

    protected function publishVersion(OrganizerId $organizer, string $documentId, string $text, bool $reconfirm = false): string
    {
        /** @var array{versionId: string} $draft */
        $draft = $this->dispatch($organizer, new DraftDocumentVersion($documentId, DraftDocumentVersion::TEXT, text: $text));
        $this->dispatch($organizer, new PublishDocumentVersion($documentId, $draft['versionId'], $reconfirm));

        return $draft['versionId'];
    }

    protected function requirement(OrganizerId $organizer, string $documentId, RequirementType $type = RequirementType::Terms, bool $mandatory = true, Confirmer $confirmer = Confirmer::EachParticipant, ?string $activityId = null, ?string $variantId = null, string $purpose = 'Participation'): LegalRequirementId
    {
        /** @var array{requirementId: string} $result */
        $result = $this->dispatch($organizer, new DefineRequirement($documentId, $type, $purpose, $confirmer, $mandatory, activityId: $activityId, variantId: $variantId));

        return LegalRequirementId::fromString($result['requirementId']);
    }

    /**
     * @return list<string> the ids of the appended rows
     */
    protected function decide(OrganizerId $organizer, LegalRequirementId $requirement, string $versionId, ConsentDecision $decision, PersonId $subject, ?ParticipationId $participation = null, ?OrderId $order = null, ?PersonId $guardian = null, ConfirmationChannel $channel = ConfirmationChannel::Web, ?string $note = null): array
    {
        /** @var list<string> $ids */
        $ids = $this->dispatch($organizer, new RecordConfirmationsCommand($organizer, [
            new ConfirmationToRecord($requirement, DocumentVersionId::fromString($versionId), $decision, $subject, $guardian, $participation ?? (null === $order ? ParticipationId::generate() : null), $order, $channel, $note, new \DateTimeImmutable('2026-11-01T10:00:00+00:00')),
        ]));

        return $ids;
    }
}
