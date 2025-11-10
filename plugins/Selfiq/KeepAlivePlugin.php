<?php

declare(strict_types=1);

namespace MadelinePlugin\Selfiq;

use danog\MadelineProto\EventHandler\Attributes\Cron;
use danog\MadelineProto\PluginEventHandler;
use Throwable;

final class KeepAlivePlugin extends PluginEventHandler
{
    #[Cron(period: 300.0)]
    public function ping(): void
    {
        try {
            $this->getSelf();
        } catch (Throwable $e) {
            $this->logger('KeepAlive error: ' . $e->getMessage());
        }
    }
}
