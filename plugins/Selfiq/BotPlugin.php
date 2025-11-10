<?php

declare(strict_types=1);

namespace MadelinePlugin\Selfiq;

final class BotPlugin extends OwnerOnlyPlugin
{
    public function onStart(): void
    {
        $this->logger('SelfIQ bot plugin started.');
    }

    public function onUpdateNewMessage(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!is_array($message) || ($message['out'] ?? false)) {
            return;
        }

        if (!$this->isOwnerMessage($message)) {
            return;
        }

        $text = trim($message['message'] ?? '');
        if ($text === '') {
            return;
        }

        if ($text === '/ping') {
            $peer = $message['peer_id'] ?? null;
            if ($peer === null) {
                return;
            }

            $this->messages->sendMessage(
                peer: $peer,
                message: 'pong'
            );
        }
    }
}
