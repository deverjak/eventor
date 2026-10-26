<?php

declare(strict_types=1);

namespace Eventor\Content\Infrastructure\Image;

use Eventor\Content\Application\Port\Thumbnailer;
use Eventor\Content\Contract\Exception\InvalidPhoto;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Thumbnails with GD (bundled in the PHP image). The type is read from the bytes, never from the client's claim.
 */
#[AsAlias(Thumbnailer::class)]
final readonly class GdThumbnailer implements Thumbnailer
{
    private const array ACCEPTED = [\IMAGETYPE_JPEG => 'image/jpeg', \IMAGETYPE_PNG => 'image/png', \IMAGETYPE_WEBP => 'image/webp'];

    public function detect(string $bytes): string
    {
        $info = '' === $bytes ? false : @getimagesizefromstring($bytes);
        if (false === $info || !isset(self::ACCEPTED[$info[2]])) {
            throw InvalidPhoto::because('The file is not a JPEG, PNG or WebP image.');
        }

        return self::ACCEPTED[$info[2]];
    }

    public function thumbnail(string $bytes, int $maxSize): string
    {
        $source = @imagecreatefromstring($bytes);
        if (false === $source) {
            throw InvalidPhoto::because('The stored photo cannot be decoded.');
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, $maxSize / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        \assert(false !== $target);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($target, null, 82);
        $jpeg = ob_get_clean();
        \assert(\is_string($jpeg));

        return $jpeg;
    }
}
