<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$registry = $kernel->getContainer()->get('doctrine');
assert($registry instanceof ManagerRegistry);

return $registry->getManager();
