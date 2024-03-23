<?php

namespace ThingsDB;

use MessagePack\MessagePack;
use Revolt\EventLoop;
use ThingsDB\enum\RequestType;
use ThingsDB\enum\ResponseType;

/**
 * Class ThingsDB
 * @package ThingsDB
 */
class ThingsDB
{
    private $socket;
    private int $id = 1;

    public function __construct(public readonly string $uri = '127.0.0.1:9200', public readonly float $timeout = 15)
    {
        $this->connect();
    }

    public function listening(float $timelimit = 3): ?Response
    {
        $suspension = EventLoop::getSuspension();

        $readableId = EventLoop::onReadable($this->socket, function ($id) use ($suspension): void {
            EventLoop::cancel($id);
            try {
                $suspension->resume($this->read());
            } catch (Exception $e) {
                $suspension->throw($e);
            }
        });

        $delayId = EventLoop::delay($timelimit, function () use ($readableId, $suspension): void {
            EventLoop::cancel($readableId);
            $suspension->resume();
        });

        $response = $suspension->suspend();
        EventLoop::cancel($readableId);
        EventLoop::cancel($delayId);
        return $response;
    }

    /*
     * ThingsDB socket methods
     */

    public function ping(): bool
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::PING);
        return $response->id == $id && $response->type === ResponseType::PONG;
    }

    public function auth(string $username = 'admin', string $password = 'pass'): bool
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::AUTH, [$username, $password]);
        return $response->type == ResponseType::OK;
    }

    public function authToken(string $token): bool
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::AUTH, $token);
        return $response->type == ResponseType::OK;
    }

    public function query(string $scope, string $code, array $vars = []): mixed
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::QUERY, empty($vars) ? [$scope, $code] : [$scope, $code, $vars]);
        return $response->data;
    }

    public function run(string $scope, string $procedure, mixed ...$args): mixed
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::RUN, [$scope, $procedure, $args]);
        return $response->data;
    }

    public function join(string $scope, int ...$ids): array
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::JOIN, [$scope, ...$ids]);
        return $response->data;
    }

    public function leave(string $scope, int ...$ids): array
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::LEAVE, [$scope, ...$ids]);
        if ($response->type === ResponseType::ON_LEAVE)
            $response = $this->listening();
        return $response->data;
    }

    public function emit(string $scope, int $roomId, string $event, mixed ...$args): bool
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::EMIT, empty($args) ? [$scope, $roomId, $event] : [$scope, $roomId, $event, ...$args]);
        if ($response->type === ResponseType::ON_EMIT)
            $response = $this->listening();
        return $response->type === ResponseType::OK;
    }

    /*
     * Connection methods
     */

    private function connect(): void
    {
        $this->socket = @stream_socket_client($this->uri, $errno, $errstr, 3);
        if ($this->socket === false)
            throw new Exception($errstr, $errno);

        if (!stream_set_blocking($this->socket, false))
            throw new Exception('Cannot set socket into non-blocking mode');

        if (!stream_set_timeout($this->socket, $this->timeout))
            throw new Exception('Cannot set timeout on connection');
    }

    private function send(int $id, RequestType $type, mixed $data = null): Response
    {
        $suspension = EventLoop::getSuspension();

        EventLoop::onReadable($this->socket, function ($id) use ($suspension): void {
            EventLoop::cancel($id);
            try {
                $suspension->resume($this->read());
            } catch (Exception $e) {
                $suspension->throw($e);
            }
        });

        $this->write($id, $type, $data);
        return $suspension->suspend();
    }

    private function write(int $id, RequestType $type, mixed $data = null): void
    {
        $header = "";
        $packed = empty($data) ? "" : MessagePack::pack($data);

        $header .= pack('V', mb_strlen($packed, '8bit'));
        $header .= pack('v', $id);
        $header .= pack('c', $type->value);
        $header .= pack('c', ~$type->value);

        // todo add buffering
        if (!fwrite($this->socket, $header . $packed))
            throw new Exception('Write into connection was not successful');
    }

    private function read(): Response
    {
        $header = stream_get_contents($this->socket, 8);

        $length = (int)unpack('V', mb_strcut($header, 0, 4))[1];
        $id = (int)unpack('v', mb_strcut($header, 4, 2))[1];
        $type = (int)unpack('c', mb_strcut($header, 6, 1))[1];
        $check = (int)unpack('c', mb_strcut($header, 7, 1))[1];

        if ($type != ~$check)
            throw new Exception('Received package type mismatch: ' . $type . ' != ' . (~$check));

        if ($length == 0)
            return new Response($id, ResponseType::from($type));

        $buffer = stream_get_contents($this->socket, $length);

        $message = MessagePack::unpack($buffer);
        if (ResponseType::from($type) === ResponseType::ERROR)
            throw new Exception($message['error_msg'], $message['error_code']);

        return new Response($id, ResponseType::from($type), $message);
    }

    /*
     * Other methods
     */

    private function getNextId(): int
    {
        $id = $this->id++;
        if ($this->id > 65535)
            $this->id = 1;
        return $id;
    }

    public function __destruct()
    {
        if (is_resource($this->socket)) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            unset($this->socket);
        }
    }
}
