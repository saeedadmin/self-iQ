<?php

declare(strict_types=1);

namespace MadelinePlugin\Selfiq;

use danog\MadelineProto\PluginEventHandler;

abstract class OwnerOnlyPlugin extends PluginEventHandler
{
    protected function isOwnerMessage(array $message): bool
    {
        $owner = getenv('OWNER_USER_ID');
        if ($owner === false) {
            return false;
        }

        $ownerId = (int) $owner;
        if ($ownerId <= 0) {
            return false;
        }

        $from = $message['from_id'] ?? ($message['peer_id'] ?? null);
        $userId = $this->extractUserId($from);

        if ($userId === null && isset($message['peer_id'])) {
            $userId = $this->extractUserId($message['peer_id']);
        }

        if ($userId === null && isset($message['from_id'])) {
            $userId = $this->extractUserId($message['from_id']);
        }

        return $userId === $ownerId;
    }

    private function extractUserId(mixed $peer): ?int
    {
        if (is_int($peer)) {
            return $peer;
        }

        if (!is_array($peer)) {
            return null;
        }

        if (isset($peer['user_id'])) {
            return (int) $peer['user_id'];
        }

        if (isset($peer['id'])) {
            return (int) $peer['id'];
        }

        return null;
    }
}
