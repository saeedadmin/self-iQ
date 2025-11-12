<?php

declare(strict_types=1);

$overrides = __DIR__ . '/../src/Liveproto/overrides.php';

if (is_file($overrides)) {
    require_once $overrides;
}
