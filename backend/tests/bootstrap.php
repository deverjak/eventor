<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Parallel agents run with different TEST_TOKENs (database eventor_test_<token>, compiled container
// var/cache/test_<token>). See backend/README.md.
if (!isset($_SERVER['TEST_TOKEN']) || '' === $_SERVER['TEST_TOKEN']) {
    $_SERVER['TEST_TOKEN'] = $_ENV['TEST_TOKEN'] = 'default';
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}
