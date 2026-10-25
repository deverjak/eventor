<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Support;

/**
 * Accounts that bin/api-test creates (through the console, before the suite starts) in the empty `eventor_api`
 * database. Keep in sync with the SEED block of bin/api-test. The tests themselves have no database access: data that
 * the API can create is created through the API, only these bootstrap accounts come from the console.
 */
final class ApiFixtures
{
    /** @var array{email: string, password: string, slug: string, name: string} */
    public const array ALPHA = [
        'email' => 'owner.alpha@api-test.example',
        'password' => 'alpha-owner-password',
        'slug' => 'api-alpha',
        'name' => 'API Alpha',
    ];

    /** @var array{email: string, password: string, slug: string, name: string} */
    public const array BETA = [
        'email' => 'owner.beta@api-test.example',
        'password' => 'beta-owner-password',
        'slug' => 'api-beta',
        'name' => 'API Beta',
    ];
}
