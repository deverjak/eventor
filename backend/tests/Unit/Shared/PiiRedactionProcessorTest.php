<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Shared\Infrastructure\Logging\PiiRedactionProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class PiiRedactionProcessorTest extends TestCase
{
    public function test_emails_tokens_ibans_and_phones_are_masked_in_message_and_context(): void
    {
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'app',
            Level::Info,
            'Sent to jana.novakova@example.cz with ss1_0199abcd_secretvalue12345',
            ['nested' => ['iban' => 'CZ65 0800 0000 1920 0014 5399', 'phone' => '+420 777 123 456', 'qr' => 'qr1.AAECAwQ.sigsigsig', 'pairrot' => 'prt_abc123def_secret']],
        );

        $redacted = (new PiiRedactionProcessor())($record);

        self::assertSame('Sent to [email] with [token]', $redacted->message);
        self::assertSame(['nested' => ['iban' => '[iban]', 'phone' => '[phone]', 'qr' => '[token]', 'pairrot' => '[token]']], $redacted->context);
    }

    public function test_harmless_text_is_untouched(): void
    {
        $record = new LogRecord(new \DateTimeImmutable(), 'app', Level::Info, 'Order 1234 placed', ['count' => 3]);

        $redacted = (new PiiRedactionProcessor())($record);

        self::assertSame('Order 1234 placed', $redacted->message);
        self::assertSame(['count' => 3], $redacted->context);
    }
}
