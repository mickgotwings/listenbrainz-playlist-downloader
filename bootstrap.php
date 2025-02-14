<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$env = Dotenv\Dotenv::createImmutable(__DIR__);
$env->load();

return new App\Application($env);
