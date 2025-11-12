<?php

declare(strict_types=1);

namespace LiveprotoOverrides;

use Tak\Liveproto\Database\MySQL;
use Tak\Liveproto\Utils\Tools;

if (!class_exists(MySQL::class, false)) {
    require __DIR__ . '/MySQL.php';
}

if (!class_exists(Tools::class, false)) {
    require __DIR__ . '/Tools.php';
}
