<?php

declare(strict_types=1);

use App\Http\Kernel;

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!headers_sent() && ob_get_level() === 0) {
    ob_start();
}

require_once dirname(__DIR__) . '/config/bootstrap.php';

$kernel = new Kernel();
$kernel->handle();

if (ob_get_level() > 0) {
    ob_end_flush();
}
