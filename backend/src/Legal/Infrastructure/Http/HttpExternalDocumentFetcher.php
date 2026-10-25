<?php

declare(strict_types=1);

namespace Eventor\Legal\Infrastructure\Http;

use Eventor\Legal\Application\Port\ExternalDocumentFetcher;
use Eventor\Legal\Domain\Document\CanonicalContent;
use Eventor\Legal\Domain\Exception\InvalidDocument;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * SSRF-safe download of an external legal document: https only (also after every redirect), no private or
 * link-local addresses (NoPrivateNetworkHttpClient checks the resolved address), at most 3 redirects, 5 MB, 10 s.
 */
#[AsAlias(ExternalDocumentFetcher::class)]
final readonly class HttpExternalDocumentFetcher implements ExternalDocumentFetcher
{
    private const int MAX_REDIRECTS = 3;

    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = new NoPrivateNetworkHttpClient($client);
    }

    public function fetch(string $url): array
    {
        try {
            for ($hop = 0; $hop <= self::MAX_REDIRECTS; ++$hop) {
                $this->assertHttps($url);
                $response = $this->client->request('GET', $url, ['max_redirects' => 0, 'timeout' => 10, 'max_duration' => 10]);
                $status = $response->getStatusCode();

                if ($status >= 300 && $status < 400) {
                    $location = $response->getHeaders(false)['location'][0] ?? null;
                    if (null === $location) {
                        throw InvalidDocument::because('The linked document redirects nowhere.');
                    }
                    $url = $this->absolute($url, $location);
                    continue;
                }
                if (200 !== $status) {
                    throw InvalidDocument::because(\sprintf('The linked document answered HTTP %d.', $status));
                }

                $body = '';
                foreach ($this->client->stream($response) as $chunk) {
                    $body .= $chunk->getContent();
                    if (\strlen($body) > CanonicalContent::MAX_BINARY_BYTES) {
                        $response->cancel();
                        throw InvalidDocument::because('The linked document is larger than 5 MB.');
                    }
                }

                return ['body' => $body, 'mime' => $response->getHeaders(false)['content-type'][0] ?? 'application/octet-stream'];
            }
        } catch (ExceptionInterface $e) {
            throw InvalidDocument::because('The linked document could not be downloaded: '.$e->getMessage());
        }

        throw InvalidDocument::because('The linked document redirects too many times.');
    }

    private function assertHttps(string $url): void
    {
        $parts = parse_url($url);
        if (false === $parts || 'https' !== strtolower($parts['scheme'] ?? '') || !isset($parts['host'])) {
            throw InvalidDocument::because('An external document must be an https link.');
        }
    }

    private function absolute(string $base, string $location): string
    {
        if (1 === preg_match('#^[a-z][a-z0-9+.-]*://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $origin.('/' === ($location[0] ?? '') ? $location : '/'.$location);
    }
}
