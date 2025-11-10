<?php

declare(strict_types=1);

namespace MadelinePlugin\Selfiq;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;

final class StatusPlugin extends PluginEventHandler
{
    #[FilterCommand('status')]
    public function handleStatus(Incoming&Message $message): void
    {
        $message->reply('SelfIQ bot is running.');
    }
}
