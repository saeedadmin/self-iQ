<?php

declare(strict_types=1);

namespace App\Bot;

use danog\MadelineProto\SimpleEventHandler;

final class BaseHandler extends SimpleEventHandler
{
    public static function getPluginPaths(): array|string|null
    {
        return 'plugins/';
    }
}
