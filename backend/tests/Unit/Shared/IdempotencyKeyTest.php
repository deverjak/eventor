<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Shared\Application\Idempotency\IdempotencyKey;
use Eventor\Shared\Application\Idempotency\InvalidIdempotencyKey;
use Eventor\Shared\Application\Messaging\Stamp\IdempotencyStamp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class IdempotencyKeyTest extends TestCase
{
    public function test_a_uuid_is_a_valid_key(): void
    {
        $uuid = Uuid::v4()->toRfc4122();

        self::assertSame($uuid, (new IdempotencyKey($uuid))->value);
    }

    #[DataProvider('invalidKeys')]
    public function test_malformed_keys_are_rejected(string $key): void
    {
        $this->expectException(InvalidIdempotencyKey::class);

        new IdempotencyKey($key);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['abc'];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'spaces' => ['0123456789 abcdef'];
        yield 'newline' => ["0123456789abcdef\n"];
        yield 'slash' => ['0123456789/abcdef'];
    }

    public function test_the_request_hash_ignores_key_order_of_the_body(): void
    {
        $a = IdempotencyStamp::for('scope', 'k', ['b' => 2, 'a' => ['y' => 1, 'x' => 2]]);
        $b = IdempotencyStamp::for('scope', 'k', ['a' => ['x' => 2, 'y' => 1], 'b' => 2]);
        $c = IdempotencyStamp::for('scope', 'k', ['a' => ['x' => 2, 'y' => 1], 'b' => 3]);

        self::assertSame($a->requestHash, $b->requestHash);
        self::assertNotSame($a->requestHash, $c->requestHash);
    }

    public function test_list_order_matters_in_the_request_hash(): void
    {
        self::assertNotSame(
            IdempotencyStamp::for('s', 'k', ['persons' => ['a', 'b']])->requestHash,
            IdempotencyStamp::for('s', 'k', ['persons' => ['b', 'a']])->requestHash,
        );
    }
}
