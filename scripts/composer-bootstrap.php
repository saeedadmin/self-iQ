<?php

declare(strict_types=1);

$overrides = __DIR__ . '/../src/Liveproto/overrides.php';

if (is_file($overrides)) {
    require_once $overrides;
}

$shimSource = __DIR__ . '/../src/Liveproto/Attributes/AttributesEngine.php';
$shimTarget = __DIR__ . '/../vendor/taknone/attributes/src/AttributesEngine.php';

if (is_file($shimSource) && !is_file($shimTarget)) {
    $shimDirectory = dirname($shimTarget);
    if (!is_dir($shimDirectory)) {
        mkdir($shimDirectory, 0777, true);
    }

    copy($shimSource, $shimTarget);
}
