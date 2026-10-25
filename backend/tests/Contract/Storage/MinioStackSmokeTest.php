<?php

declare(strict_types=1);

namespace Eventor\Tests\Contract\Storage;

use AsyncAws\S3\S3Client;
use PHPUnit\Framework\TestCase;

/**
 * Phase F0 smoke test of the S3 API of the compose `minio` service (the same client settings as the
 * `eventor.s3_client` service): the buckets exist and objects can be written, read and deleted.
 */
final class MinioStackSmokeTest extends TestCase
{
    private S3Client $s3;

    protected function setUp(): void
    {
        $this->s3 = new S3Client([
            'endpoint' => $this->env('S3_ENDPOINT', 'http://minio:9000'),
            'accessKeyId' => $this->env('S3_KEY', 'eventor'),
            'accessKeySecret' => $this->env('S3_SECRET', 'eventor-secret'),
            'region' => $this->env('S3_REGION', 'us-east-1'),
            'pathStyleEndpoint' => 'true',
        ]);
    }

    public function test_the_media_buckets_exist(): void
    {
        $names = [];
        foreach ($this->s3->listBuckets()->getBuckets() as $bucket) {
            $names[] = $bucket->getName();
        }

        self::assertContains('eventor-media', $names);
        self::assertContains('eventor-media-test', $names);
    }

    public function test_an_object_can_be_written_read_and_deleted(): void
    {
        $key = 'smoke/'.bin2hex(random_bytes(6)).'.txt';

        $this->s3->putObject(['Bucket' => 'eventor-media-test', 'Key' => $key, 'Body' => 'hello eventor']);
        self::assertSame('hello eventor', $this->s3->getObject(['Bucket' => 'eventor-media-test', 'Key' => $key])->getBody()->getContentAsString());

        $this->s3->deleteObject(['Bucket' => 'eventor-media-test', 'Key' => $key]);
        self::assertFalse($this->s3->objectExists(['Bucket' => 'eventor-media-test', 'Key' => $key])->isSuccess());
    }

    private function env(string $name, string $default): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== $value ? $value : $default;
    }
}
