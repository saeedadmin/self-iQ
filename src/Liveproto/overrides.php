<?php

declare(strict_types=1);

namespace LiveprotoOverrides;

use Tak\Liveproto\Database\MySQL;
use Tak\Liveproto\Utils\Tools;

$mysqlPath = __DIR__ . '/MySQL.php';
if (is_file($mysqlPath) && !class_exists(MySQL::class, false)) {
    require $mysqlPath;
}

$toolsPath = __DIR__ . '/Tools.php';
if (is_file($toolsPath) && !class_exists(Tools::class, false)) {
    require $toolsPath;
}

if (class_exists(Tools::class, false) && class_exists(MySQL::class, false)) {
    Tools::set('mysql', MySQL::class);
}

final class AttributeShim
{
    private const CLASS_MAP = [
        'Tak\\Attributes\\AttributesEngine' => __DIR__ . '/Attributes/AttributesEngine.php',
    ];

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load'], prepend: true);
    }

    private static function load(string $class): void
    {
        if (!isset(self::CLASS_MAP[$class])) {
            return;
        }

        $path = self::CLASS_MAP[$class];

        if (class_exists($class, false) || trait_exists($class, false)) {
            return;
        }

        if (!is_file($path)) {
            return;
        }

        require_once $path;
    }
}

AttributeShim::register();
