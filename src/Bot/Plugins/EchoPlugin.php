<?php

declare(strict_types=1);

namespace App\Bot\Plugins;

use danog\MadelineProto\API;
use danog\MadelineProto\TL\Types\Messages\Messages;
use danog\MadelineProto\SimpleEventHandler;

final class EchoPlugin extends SimpleEventHandler
{
    public static function getPluginPaths(): array|string|null
    {
        return null;
    }

    public function onUpdateNewMessage(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!is_array($message) || ($message['out'] ?? false)) {
            return;
        }

        if (($message['message'] ?? '') === '') {
            return;
        }

        $peer = $message['peer_id'] ?? null;
        if ($peer === null) {
            return;
        }

        $this->messages->sendMessage(
            peer: $peer,
            message: 'Echo: ' . $message['message']
        );
    }
}
