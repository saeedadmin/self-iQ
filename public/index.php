<?php

declare(strict_types=1);

use App\Http\Kernel;

require_once dirname(__DIR__) . '/config/bootstrap.php';

$kernel = new Kernel();
$kernel->handle();
