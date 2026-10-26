<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Support;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client of one simulated user: it keeps its own cookie jar (the session cookie), sends JSON, and adds the
 * `csrf-token` header to unsafe requests like the real frontend does. It speaks plain HTTP to the running API and
 * has no other way into the system.
 */
final class ApiClient
{
    /** @var array<string, string> */
    private array $cookies = [];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $baseUrl,
    ) {}

    /**
     * @param array<array-key, mixed>|null $json
     * @param array<string, string>        $headers
     */
    public function request(string $method, string $path, ?array $json = null, array $headers = [], bool $csrf = true): ApiResponse
    {
        $headers += ['Accept' => 'application/json'];
        if ($csrf && !\in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $headers['csrf-token'] ??= bin2hex(random_bytes(16));
        }
        if ([] !== $this->cookies) {
            $headers['Cookie'] = implode('; ', array_map(static fn(string $name, string $value): string => "$name=$value", array_keys($this->cookies), $this->cookies));
        }

        $options = ['headers' => $headers, 'max_redirects' => 0, 'timeout' => 15];
        if (null !== $json) {
            $options['json'] = $json;
        }

        $response = $this->http->request($method, rtrim($this->baseUrl, '/').$path, $options);
        $status = $response->getStatusCode();
        $body = $response->getContent(false);
        /** @var array<string, list<string>> $responseHeaders */
        $responseHeaders = $response->getHeaders(false);

        $this->rememberCookies($responseHeaders['set-cookie'] ?? []);

        return new ApiResponse($status, $responseHeaders, $body);
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $path, array $headers = []): ApiResponse
    {
        return $this->request('GET', $path, null, $headers);
    }

    /**
     * @param array<array-key, mixed> $json
     * @param array<string, string>   $headers
     */
    public function post(string $path, array $json = [], array $headers = [], bool $csrf = true): ApiResponse
    {
        return $this->request('POST', $path, $json, $headers, $csrf);
    }

    /**
     * A multipart upload like a browser form: the file field plus text fields.
     *
     * @param array<string, string> $fields
     */
    public function upload(string $path, string $field, string $fileName, string $bytes, string $contentType, array $fields = []): ApiResponse
    {
        $form = new FormDataPart($fields + [$field => new DataPart($bytes, $fileName, $contentType)]);
        $headers = ['Accept: application/json', 'csrf-token: '.bin2hex(random_bytes(16)), ...$form->getPreparedHeaders()->toArray()];
        if ([] !== $this->cookies) {
            $headers[] = 'Cookie: '.implode('; ', array_map(static fn(string $name, string $value): string => "$name=$value", array_keys($this->cookies), $this->cookies));
        }

        $response = $this->http->request('POST', rtrim($this->baseUrl, '/').$path, ['headers' => $headers, 'body' => $form->bodyToIterable(), 'max_redirects' => 0, 'timeout' => 15]);
        /** @var array<string, list<string>> $responseHeaders */
        $responseHeaders = $response->getHeaders(false);
        $this->rememberCookies($responseHeaders['set-cookie'] ?? []);

        return new ApiResponse($response->getStatusCode(), $responseHeaders, $response->getContent(false));
    }

    public function hasSession(): bool
    {
        return isset($this->cookies['EVENTOR_SESSID']);
    }

    /**
     * @param list<string> $setCookies
     */
    private function rememberCookies(array $setCookies): void
    {
        foreach ($setCookies as $line) {
            $parts = array_map('trim', explode(';', $line));
            [$name, $value] = explode('=', array_shift($parts), 2) + [1 => ''];
            $expired = false;
            foreach ($parts as $attribute) {
                if (0 === strcasecmp($attribute, 'Max-Age=0') || (str_starts_with(strtolower($attribute), 'expires=') && strtotime(substr($attribute, 8)) < time())) {
                    $expired = true;
                }
            }

            if ($expired || 'deleted' === $value || '' === $value) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $value;
            }
        }
    }
}
