<?php

declare(strict_types=1);

use App\Http\Kernel;

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/bootstrap.php';

$kernel = new Kernel();
$kernel->handle();
