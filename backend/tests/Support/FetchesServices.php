<?php

declare(strict_types=1);

namespace Eventor\Tests\Support;

/**
 * Access to container services that tests need but production code leaves private: config/services_test.php
 * registers a public `test.<FQCN>` alias for each of them. Add the class there when you need another one.
 */
trait FetchesServices
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function service(string $class): object
    {
        $service = static::getContainer()->get('test.'.$class);
        \assert($service instanceof $class);

        return $service;
    }
}
