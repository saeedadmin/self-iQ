<?php

declare(strict_types=1);

namespace Tak\Liveproto\Tl;

final class DocBuilder
{
    private const DEFAULT_LAYER = 166;
    private const DEFAULT_SECRET_LAYER = 23;

    public static function layer(bool $secret = false): int
    {
        $envKey = $secret ? 'LIVEPROTO_SECRET_LAYER' : 'LIVEPROTO_LAYER';
        $envValue = getenv($envKey);
        if ($envValue !== false && $envValue !== '' && is_numeric($envValue)) {
            return (int) $envValue;
        }

        $file = self::layerFilePath($secret);
        if ($file !== null) {
            $layer = self::extractLayer($file);
            if ($layer !== null) {
                return $layer;
            }
        }

        return $secret ? self::DEFAULT_SECRET_LAYER : self::DEFAULT_LAYER;
    }

    private static function layerFilePath(bool $secret): ?string
    {
        $envKey = $secret ? 'LIVEPROTO_SECRET_LAYER_FILE' : 'LIVEPROTO_LAYER_FILE';
        $candidate = getenv($envKey);
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            return $candidate;
        }

        $local = __DIR__ . DIRECTORY_SEPARATOR . ($secret ? 'secret.tl' : 'api.tl');
        if (is_file($local)) {
            return $local;
        }

        return null;
    }

    private static function extractLayer(string $path): ?int
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        if (preg_match('/\/\/\s*LAYER\s*(\d+)/i', $contents, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
