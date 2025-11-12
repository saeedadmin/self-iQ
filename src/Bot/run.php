<?php

declare(strict_types=1);

use App\Bot\Handler;
use App\Config\SettingsFactory;
use App\Support\SessionBootstrapper;
use Tak\Liveproto\Network\Client;

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

$sessionName = getenv('SESSION_NAME');
if ($sessionName === false || $sessionName === '') {
    throw new RuntimeException('SESSION_NAME env variable is not set.');
}

$settings = SettingsFactory::make();

$tablePrefix = preg_replace('/[^A-Za-z0-9_]/', '_', $sessionName);
SessionBootstrapper::bootstrap($tablePrefix);

$client = new Client($sessionName, 'mysql', $settings);

$ownerId = (int) getenv('OWNER_USER_ID');
$client->addHandler(new Handler($client, $ownerId));

$client->start();
