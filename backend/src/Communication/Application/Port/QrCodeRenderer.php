<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Port;

/**
 * Turns a payload into a PNG image (the QR payment code of an e-mail).
 */
interface QrCodeRenderer
{
    public function png(string $payload): string;
}
