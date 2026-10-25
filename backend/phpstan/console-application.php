<?php

declare(strict_types=1);

use Eventor\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('dev', true);

return new Application($kernel);
