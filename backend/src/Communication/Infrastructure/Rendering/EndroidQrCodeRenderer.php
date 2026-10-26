<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Rendering;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Eventor\Communication\Application\Port\QrCodeRenderer;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(QrCodeRenderer::class)]
final readonly class EndroidQrCodeRenderer implements QrCodeRenderer
{
    public function png(string $payload): string
    {
        return (new Builder(writer: new PngWriter(), data: $payload, size: 300, margin: 10))->build()->getString();
    }
}
