<?php

declare(strict_types=1);

namespace Tak\Liveproto\Utils;

use function Amp\ByteStream\getStdin;
use function Amp\ByteStream\getStdout;

final class Tools
{
    public static function readLine(?string $prompt = null, ?object $cancellation = null): string
    {
        try {
            $stdin = getStdin();
            $stdout = getStdout();
            if ($prompt !== null) {
                $stdout->write($prompt);
            }

            static $lines = [null];
            while (count($lines) < 2 && ($chunk = $stdin->read($cancellation)) !== null) {
                $chunk = explode("\n", str_replace(["\r", "\r\n"], "\n", $chunk));
                $lines[count($lines) - 1] .= array_shift($chunk);
                $lines = array_merge($lines, $chunk);
            }
        } catch (\Throwable $error) {
            Logging::log('Tools', $error->getMessage(), E_WARNING);
        }

        return (string) array_shift($lines);
    }

    public static function snakeTocamel(string $str): string
    {
        return str_replace('_', '', ucwords($str, '_'));
    }

    public static function camelTosnake(string $str): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
    }

    public static function isCli(): bool
    {
        return in_array(PHP_SAPI, ['cli', 'cli-server', 'phpdbg', 'embed'], true);
    }

    public static function marshal(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_object($value) || is_array($value) || is_bool($value) || mb_check_encoding(var_export($value, true), 'UTF-8') === false) {
                $data[$key] = 'serialize:' . base64_encode(serialize($value));
            } elseif (is_string($value) && str_starts_with($value, 'serialize:')) {
                $serialize = substr($value, 10);
                $data[$key] = unserialize(base64_decode($serialize));
            }
        }

        return $data;
    }

    public static function inferType(mixed $data): string
    {
        if ($data === null) {
            return 'TEXT';
        }

        return match (gettype($data)) {
            'boolean' => 'BOOLEAN',
            'object', 'array' => 'LONGTEXT',
            'integer' => 'BIGINT',
            'double' => 'REAL',
            'string' => 'TEXT',
            default => 'VARCHAR (' . mb_strlen((string) $data) . ')',
        };
    }

    public static function base64_url_encode(string $string): string
    {
        return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
    }

    public static function base64_url_decode(string $string): string|false
    {
        return base64_decode(strtr($string, '-_', '+/'));
    }

    public static function inDestructor(?array $stack = null): bool
    {
        $stack ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($stack as $frame) {
            if (($frame['function'] ?? null) === '__destruct') {
                return true;
            }
        }

        return false;
    }
}
