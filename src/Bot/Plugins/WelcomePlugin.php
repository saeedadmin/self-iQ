<?php

declare(strict_types=1);

namespace App\Bot\Plugins;

use MadelinePlugin\Selfiq\OwnerOnlyPlugin;

final class WelcomePlugin extends OwnerOnlyPlugin
{
    public function onUpdateNewMessage(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!is_array($message) || ($message['out'] ?? false)) {
            return;
        }

        if (!$this->isOwnerMessage($message)) {
            return;
        }

        $text = $message['message'] ?? '';
        if ($text !== '/start') {
            return;
        }

        $peer = $message['peer_id'] ?? null;
        if ($peer === null) {
            return;
        }

        $this->messages->sendMessage(
            peer: $peer,
            message: "سلام! ربات فعال است."
        );
    }
}
