<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Payments;

use Eventor\Payments\Application\Query\OrderFinanceQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Payment instructions show the domestic account number; when Pairrot announced only the IBAN it is derived from it.
 */
final class AccountNumberFromIbanTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function rows(): iterable
    {
        yield 'stored account number wins' => [['account_number' => '19-2000145399/0800', 'iban' => 'CZ6508000000192000145399'], '19-2000145399/0800'];
        yield 'czech IBAN with prefix' => [['account_number' => null, 'iban' => 'CZ65 0800 0000 1920 0014 5399'], '19-2000145399/0800'];
        yield 'czech IBAN without prefix' => [['account_number' => null, 'iban' => 'CZ5508000000001234567899'], '1234567899/0800'];
        yield 'foreign IBAN as it is' => [['account_number' => null, 'iban' => 'SK3112000000198742637541'], 'SK3112000000198742637541'];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('rows')]
    public function test_the_account_number_of_payment_instructions(array $row, string $expected): void
    {
        $method = new \ReflectionMethod(OrderFinanceQuery::class, 'accountNumber');

        self::assertSame($expected, $method->invoke(null, $row));
    }
}
