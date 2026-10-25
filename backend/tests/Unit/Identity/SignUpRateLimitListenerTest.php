<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Identity;

use Eventor\Identity\Infrastructure\Security\SignUpRateLimitListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class SignUpRateLimitListenerTest extends TestCase
{
    private function listener(int $limit): SignUpRateLimitListener
    {
        return new SignUpRateLimitListener(
            new RateLimiterFactory(['id' => 'signup', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 hour'], new InMemoryStorage()),
            new MockClock(),
        );
    }

    private function event(string $route, string $ip, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/api/auth/sign-up', 'POST', server: ['REMOTE_ADDR' => $ip]);
        $request->attributes->set('_route', $route);

        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type);
    }

    public function test_it_lets_the_configured_number_of_requests_through_and_then_answers_429_with_retry_after(): void
    {
        $listener = $this->listener(3);

        for ($i = 0; $i < 3; ++$i) {
            $listener($this->event('api_auth_sign_up', '203.0.113.7'));
        }

        try {
            $listener($this->event('api_auth_sign_up', '203.0.113.7'));
            self::fail('the fourth request must be throttled');
        } catch (TooManyRequestsHttpException $e) {
            self::assertSame(429, $e->getStatusCode());
            $retry = $e->getHeaders()['Retry-After'] ?? 0;
            self::assertTrue(is_numeric($retry) && (int) $retry > 0, 'Retry-After header');
        }
    }

    public function test_the_limit_is_per_address_and_shared_by_the_three_public_routes(): void
    {
        $listener = $this->listener(2);
        $listener($this->event('api_auth_sign_up', '203.0.113.7'));
        $listener($this->event('api_auth_invitation_preview', '203.0.113.7'));

        $listener($this->event('api_auth_invitation_accept', '203.0.113.99'));
        $this->expectException(TooManyRequestsHttpException::class);
        $listener($this->event('api_auth_invitation_accept', '203.0.113.7'));
    }

    public function test_other_routes_and_sub_requests_are_not_counted(): void
    {
        $listener = $this->listener(1);

        for ($i = 0; $i < 5; ++$i) {
            $listener($this->event('api_auth_login', '203.0.113.7'));
            $listener($this->event('api_auth_sign_up', '203.0.113.7', HttpKernelInterface::SUB_REQUEST));
        }

        $listener($this->event('api_auth_sign_up', '203.0.113.7'));
        $this->addToAssertionCount(1);
    }
}
