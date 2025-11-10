<?php

declare(strict_types=1);

namespace App\Bot;

use danog\MadelineProto\SimpleEventHandler;

final class Handler extends SimpleEventHandler
{
    public function onUpdateNewMessage(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!is_array($message) || ($message['out'] ?? false)) {
            return;
        }

        $text = $message['message'] ?? '';
        if ($text === '') {
            return;
        }

        $peer = $message['peer_id'] ?? null;
        if ($peer === null) {
            return;
        }

        $this->messages->sendMessage(
            peer: $peer,
            message: "Echo: {$text}"
        );
    }
}
