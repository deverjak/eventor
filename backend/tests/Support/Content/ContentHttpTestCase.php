<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Content;

use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Registration\RegistrationScenarioCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Base of the Content HTTP tests: organizer "klub" with a published event (content belongs to published events) and
 * helpers for collections, multipart photo uploads and generated test images.
 */
abstract class ContentHttpTestCase extends RegistrationScenarioCase
{
    protected string $eventId;

    protected string $eventSlug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent('vikend');
        $this->publish($event['eventId']);
        $this->drain();
        $this->eventId = $event['eventId'];
        $this->eventSlug = $event['slug'];
    }

    protected function admin(string $suffix = '', ?string $eventId = null, string $orgSlug = 'klub'): string
    {
        return '/api/admin/org/'.$orgSlug.'/events/'.($eventId ?? $this->eventId).$suffix;
    }

    protected function public(string $suffix): string
    {
        return '/api/public/orgs/klub/events/'.$this->eventSlug.$suffix;
    }

    /**
     * @return array{collectionId: string, linkToken: string|null}
     */
    protected function createCollection(string $title = 'Fotky ze závodu', string $visibility = 'public', ?string $externalUrl = null): array
    {
        $created = $this->request('POST', $this->admin('/collections'), ['title' => $title, 'visibility' => $visibility, 'externalUrl' => $externalUrl]);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());

        return ['collectionId' => Json::str($created, 'collectionId'), 'linkToken' => null === $created || null === ($created['linkToken'] ?? null) ? null : Json::str($created, 'linkToken')];
    }

    /**
     * Uploads a photo as the browser does (multipart) and delivers `content.photo_uploaded`, which makes the thumbnail.
     *
     * @param array<string, string> $fields
     *
     * @return array<array-key, mixed>|null
     */
    protected function upload(string $collectionId, ?string $bytes = null, array $fields = [], ?string $eventId = null): ?array
    {
        $path = tempnam(sys_get_temp_dir(), 'photo');
        \assert(\is_string($path));
        file_put_contents($path, $bytes ?? self::jpeg());
        $this->client->request(
            'POST',
            $this->admin('/collections/'.$collectionId.'/photos', $eventId),
            $fields,
            ['photo' => new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true)],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_CSRF_TOKEN' => bin2hex(random_bytes(16))],
        );
        $content = (string) $this->client->getResponse()->getContent();
        $this->drain();

        /** @var array<array-key, mixed>|null $decoded */
        $decoded = '' === $content ? null : json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    protected static function jpeg(int $width = 1200, int $height = 800): string
    {
        $image = imagecreatetruecolor($width, $height);
        \assert(false !== $image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 120, 200));
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    protected function anonymous(): void
    {
        $this->client->getCookieJar()->clear();
    }
}
