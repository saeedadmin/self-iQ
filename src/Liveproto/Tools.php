<?php

declare(strict_types=1);

namespace Tak\Liveproto\Utils;

use Tak\Liveproto\Utils\Tools as BaseTools;

final class Tools extends BaseTools
{
    public static function inferType(mixed $data): string
    {
        if ($data === null) {
            return 'TEXT';
        }

        return parent::inferType($data);
    }
}
