<?php

declare(strict_types=1);

namespace Tak\Liveproto\Database;

use Tak\Liveproto\Enums\Authentication;
use Tak\Liveproto\Utils\Helper;
use Tak\Liveproto\Utils\Settings;
use Tak\Liveproto\Utils\Tools;

use function Amp\File\getSize;
use function Amp\File\isFile;
use function Amp\File\read;
use function Amp\File\write;

final class Session
{
    protected Content $content;
    protected MySQL $mysql;
    protected SQLite $sqlite;
    private array $servers = [
        ['ip' => '149.154.175.50', 'ipv6' => '2001:b28:f23d:f001:0000:0000:0000:000a', 'port' => 443, 'dc' => 1],
        ['ip' => '149.154.167.51', 'ipv6' => '2001:67c:4e8:f002:0000:0000:0000:000a', 'port' => 443, 'dc' => 2],
        ['ip' => '149.154.175.100', 'ipv6' => '2001:b28:f23d:f003:0000:0000:0000:000a', 'port' => 443, 'dc' => 3],
        ['ip' => '149.154.167.91', 'ipv6' => '2001:67c:4e8:f004:0000:0000:0000:000a', 'port' => 443, 'dc' => 4],
        ['ip' => '91.108.56.180', 'ipv6' => '2001:b28:f23f:f005:0000:0000:0000:000a', 'port' => 443, 'dc' => 5],
    ];
    private array $testservers = [
        ['ip' => '149.154.175.40', 'ipv6' => '2001:b28:f23d:f001:0000:0000:0000:000e', 'port' => 80, 'dc' => 1],
        ['ip' => '149.154.167.40', 'ipv6' => '2001:67c:4e8:f002:0000:0000:0000:000e', 'port' => 80, 'dc' => 2],
        ['ip' => '149.154.175.117', 'ipv6' => '2001:b28:f23d:f003:0000:0000:0000:000e', 'port' => 80, 'dc' => 3],
    ];
    public readonly bool $ipv6;
    public readonly bool $testmode;
    public readonly int $dc;
    public readonly float $savetime;
    public readonly string $server;
    public readonly string $username;
    public readonly string $password;
    public readonly string $database;

    public function __construct(public readonly ?string $name, private ?string $mode, Settings $settings)
    {
        $this->ipv6 = is_bool($settings->ipv6) ? $settings->ipv6 : false;
        $this->testmode = is_bool($settings->testmode) ? $settings->testmode : false;
        $this->dc = is_int($settings->dc) ? $settings->dc : 0;
        $this->savetime = is_numeric($settings->savetime) ? (float) $settings->savetime : 3.0;
        $this->server = is_string($settings->server) ? $settings->server : 'localhost';
        $this->username = is_string($settings->username) ? $settings->username : '';
        $this->password = is_string($settings->password) ? $settings->password : '';
        $this->database = is_string($settings->database) ? $settings->database : $this->username;
    }

    public function generate(): object
    {
        if ($this->dc > 0) {
            if ($this->testmode) {
                if ($this->dc <= count($this->testservers)) {
                    $server = $this->testservers[$this->dc - 1];
                } else {
                    throw new \Exception('The ID of the test data center must be less than ' . count($this->testservers));
                }
            } else {
                if ($this->dc <= count($this->servers)) {
                    $server = $this->servers[$this->dc - 1];
                } else {
                    throw new \Exception('The ID of the data center must be less than ' . count($this->servers));
                }
            }
        } else {
            $server = $this->testmode ? $this->testservers[array_rand($this->testservers)] : $this->servers[array_rand($this->servers)];
        }

        return new Content([
            'id' => 0,
            'api_id' => 0,
            'api_hash' => null,
            'dc' => $server['dc'],
            'ip' => ($this->ipv6 ? $server['ipv6'] : $server['ip']),
            'port' => $server['port'],
            'auth_key' => new \stdClass(),
            'salt' => 0,
            'sequence' => 0,
            'time_offset' => 0,
            'last_msg_id' => 0,
            'logout_tokens' => [],
            'peers' => new CachedPeers($this->name),
            'state' => (object) ['pts' => 1, 'qts' => -1, 'date' => 1, 'seq' => 0],
            'step' => Authentication::NEED_AUTHENTICATION,
        ], $this->savetime);
    }

    public function load(): object
    {
        if (isset($this->content)) {
            return $this->content;
        }

        if ($this->name === null) {
            $this->content = $this->generate();
            $this->content->setSession($this);

            return $this->content;
        }

        switch (strtolower((string) $this->mode)) {
            case 'string':
                $file = '.' . DIRECTORY_SEPARATOR . $this->name . '.session';
                if (isFile($file) && getSize($file)) {
                    $this->content = unserialize(gzinflate(base64_decode(read($file))))->setSession($this);
                } else {
                    $content = $this->generate();
                    write($file, base64_encode(gzdeflate(serialize($content))));
                    $this->content = $content->setSession($this);
                }
                break;

            case 'sqlite':
                $path = $this->database === '' ? $this->name : $this->database;
                $this->sqlite = new SQLite($path . '.db');
                if ($this->sqlite->init($this->name)) {
                    $content = $this->generate();
                } else {
                    $content = new Content(Tools::marshal($this->sqlite->get($this->name)), $this->savetime);
                }
                $this->content = $content->setSession($this);
                $this->content->peers->init($this->sqlite);
                $this->content->save(true);
                break;

            case 'mysql':
                $this->assertMysqlSettings();

                $adapterClass = Tools::get('mysql') ?? MySQL::class;
                if (!is_a($adapterClass, MySQL::class, true)) {
                    throw new \RuntimeException(sprintf('Invalid MySQL adapter registered: %s', $adapterClass));
                }

                $this->mysql = new $adapterClass($this->buildMysqlConfig());
                if ($this->mysql->init($this->name)) {
                    $content = $this->generate();
                } else {
                    $content = new Content(Tools::marshal($this->mysql->get($this->name)), $this->savetime);
                }

                $this->content = $content->setSession($this);
                $this->content->peers->init($this->mysql);
                $this->content->save(true);
                break;

            case 'text':
                $this->content = unserialize(gzinflate(base64_decode($this->name)))->setSession($this);
                break;

            default:
                throw new \Exception('The database mode is invalid ! It must be one of the modes of string , sqlite , mysql , text !');
        }

        return $this->content;
    }

    public function save(): void
    {
        if ($this->name === null || $this->mode === 'text') {
            return;
        }

        switch ($this->mode) {
            case 'string':
                $file = '.' . DIRECTORY_SEPARATOR . $this->name . '.session';
                if (isFile($file)) {
                    write($file, base64_encode(gzdeflate(serialize($this->content))));
                }
                break;

            case 'sqlite':
                $data = Tools::marshal($this->content->toArray());
                array_walk($data, fn (mixed $value, string $key): mixed => $this->sqlite->set($this->name, $key, $value, Tools::inferType($value)));
                break;

            case 'mysql':
                $data = Tools::marshal($this->content->toArray());
                array_walk($data, fn (mixed $value, string $key): mixed => $this->mysql->set($this->name, $key, $value, Tools::inferType($value)));
                break;

            default:
                throw new \Exception('The database mode is invalid ! It must be one of the modes of string , sqlite , mysql , text !');
        }
    }

    public function reset(?int $id = null): void
    {
        $this->content['id'] = $id === null ? Helper::generateRandomLong() : $id;
        $this->content['sequence'] = 0;
        $this->content['last_msg_id'] = 0;
        gc_collect_cycles();
    }

    public function getServerTime(): int
    {
        return (int) (time() + $this->content['time_offset']);
    }

    public function updateTimeOffset(int $msgId): void
    {
        $old = $this->content['time_offset'];
        $sec = (int) ($msgId >> 32);
        $this->content['time_offset'] = $sec - time();
        if ($this->content['time_offset'] !== $old) {
            $this->content['last_msg_id'] = 0;
        }
    }

    public function getNewMsgId(): int
    {
        $now = $this->getServerTime();
        $msgId = (int) ($now << 32);
        $msgId = $msgId & (~0x3);
        $msgId = $msgId <= $this->content['last_msg_id'] ? (int) ($this->content['last_msg_id'] + 4) : $msgId;
        $this->content['last_msg_id'] = $msgId;

        return $msgId;
    }

    public function generateSequence(bool $contentRelated = true): int
    {
        if ($contentRelated) {
            $seqno = $this->content['sequence'] * 2 + 1;
            $this->content['sequence'] += 1;

            return $seqno;
        }

        return $this->content['sequence'] * 2;
    }

    public function getStringSession(): string
    {
        $session = clone $this;
        $content = $session->load();

        return base64_encode(gzdeflate(serialize($content)));
    }

    public function __debugInfo(): array
    {
        return [
            'content' => $this->content ?? new \stdClass(),
            'ipv6' => $this->ipv6,
            'testmode' => $this->testmode,
            'dc' => $this->dc,
            'savetime' => $this->savetime,
            'server' => $this->server,
            'username' => $this->username,
            'password' => $this->password,
            'database' => $this->database,
        ];
    }

    public function __clone(): void
    {
        $this->content = clone $this->content;
        $this->reset(id: 0);
    }

    public function __sleep(): array
    {
        return ['ipv6', 'testmode', 'dc', 'savetime', 'server', 'username', 'password', 'database'];
    }

    private function assertMysqlSettings(): void
    {
        if ($this->server === '') {
            throw new \Exception('Server parameter for mysql database is empty !');
        }
        if ($this->username === '') {
            throw new \Exception('Username parameter for mysql database is empty !');
        }
        if ($this->password === '') {
            throw new \Exception('Password parameter for mysql database is empty !');
        }
        if ($this->database === '') {
            throw new \Exception('Database parameter for mysql database is empty !');
        }
    }

    private function buildMysqlConfig(): object
    {
        return (object) [
            'server' => $this->server,
            'username' => $this->username,
            'password' => $this->password,
            'database' => $this->database,
        ];
    }
}
