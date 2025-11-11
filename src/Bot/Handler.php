<?php

declare(strict_types=1);

namespace App\Bot;

use Revolt\EventLoop;
use Tak\Liveproto\Filters\Events\NewMessage;
use Tak\Liveproto\Filters\Filter;
use Tak\Liveproto\Filters\Filter\Command;
use Tak\Liveproto\Filters\Interfaces\Incoming;
use Tak\Liveproto\Filters\Interfaces\IsPrivate;
use Tak\Liveproto\Enums\CommandType;
use Tak\Liveproto\Network\Client;

final class Handler
{
    private int $ownerId;

    public function __construct(private Client $client, int $ownerId)
    {
        $this->ownerId = $ownerId;

        EventLoop::repeat(300, function (): void {
            if (!$this->client->isAuthorized()) {
                return;
            }

            try {
                $this->client->getSelf();
            } catch (\Throwable $e) {
                // Ignore keep-alive errors
            }
        });
    }

    #[Filter(new NewMessage(new Command(start: [CommandType::SLASH, CommandType::DOT, CommandType::EXCLAMATION])))]
    public function start(Incoming & IsPrivate $update): void
    {
        if (!$this->isOwner($update)) {
            return;
        }

        $update->reply('سلام! ربات فعال است.');
    }

    #[Filter(new NewMessage(new Command(ping: [CommandType::SLASH, CommandType::EXCLAMATION])))]
    public function ping(Incoming & IsPrivate $update): void
    {
        if (!$this->isOwner($update)) {
            return;
        }

        $update->reply('pong');
    }

    #[Filter(new NewMessage())]
    public function echoOwner(Incoming & IsPrivate $update): void
    {
        if (!$this->isOwner($update)) {
            return;
        }

        if (!isset($update->message) || $update->message->message === '') {
            return;
        }

        $text = $update->message->message;

        if (in_array($text, ['/start', '.start', '!start', '/ping', '!ping'], true)) {
            return;
        }

        $update->reply('Echo: ' . $text);
    }

    private function isOwner(object $update): bool
    {
        $userId = $this->extractUserId($update);

        return $this->ownerId > 0 && $userId === $this->ownerId;
    }

    private function extractUserId(object $update): ?int
    {
        if (isset($update->message)) {
            $message = $update->message;
            if (isset($message->from_id) && isset($message->from_id->user_id)) {
                return (int) $message->from_id->user_id;
            }
            if (isset($message->peer_id) && isset($message->peer_id->user_id)) {
                return (int) $message->peer_id->user_id;
            }
        }

        if (isset($update->sender) && isset($update->sender->id)) {
            return (int) $update->sender->id;
        }

        if (method_exists($update, 'getPeerId')) {
            $peerId = $update->getPeerId();
            return is_numeric($peerId) ? (int) $peerId : null;
        }

        return null;
    }
}
