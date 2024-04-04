<?php

namespace ThingsDB;

use MessagePack\MessagePack;
use ThingsDB\enum\RequestType;
use ThingsDB\enum\ResponseType;
use ThingsDB\error\{ConnectException, PackageException, ThingsException};

/**
 * Main class ThingsDB
 *
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/thingsdb-php
 * @package ThingsDB
 */
class ThingsDB
{
    /** @var resource */
    private $socket = null;

    /**
     * Internal package ID counter
     * @var int
     */
    private int $id = 1;

    /**
     * Internal buffer for emitted packages from ThingsDB which were not consumed because they were emitted while waiting for specific package
     * @var Response[]
     */
    private array $responses = [];

    /**
     * @param string $uri
     * @param float $timeout
     * @param array $context Array provided to stream_context_create https://www.php.net/manual/en/function.stream-context-create.php
     * @throws ConnectException
     */
    public function __construct(
        public readonly string $uri = '127.0.0.1:9200',
        public readonly float  $timeout = 15,
        public readonly array  $context = ['socket' => ['tcp_nodelay' => true]]
    )
    {
        if (intval(ini_get('max_execution_time')) > 0 && $this->timeout > intval(ini_get('max_execution_time')))
            throw new ConnectException('Timeout can\'t be bigger than php max_execution_time');
        $this->connect();
    }

    /*
     * ThingsDB socket methods
     */

    /**
     * Ping, useful as keep-alive.
     * @link https://docs.thingsdb.io/v1/connect/socket/ping/
     * @return bool
     */
    public function ping(): bool
    {
        $id = $this->getNextId();
        try {
            $response = $this->send($id, RequestType::PING);
            return $response->type === ResponseType::PONG;
        } catch (ThingsException) {
            return false;
        }
    }

    /**
     * Authorization with username and password.
     * @link https://docs.thingsdb.io/v1/connect/socket/auth/
     * @param string $username
     * @param string $password
     * @return bool
     * @throws ThingsException
     */
    public function auth(string $username = 'admin', string $password = 'pass'): bool
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::AUTH, [$username, $password]);
        return $response->type == ResponseType::OK;
    }

    /**
     * Authorization with token.
     * @link https://docs.thingsdb.io/v1/connect/socket/auth/
     * @param string $token
     * @return bool
     * @throws ThingsException
     */
    public function authToken(string $token): bool
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::AUTH, $token);
        return $response->type == ResponseType::OK;
    }

    /**
     * Query ThingsDB.
     * @link https://docs.thingsdb.io/v1/connect/socket/query/
     * @param string $scope
     * @param string $code
     * @param array $vars
     * @return mixed
     * @throws ThingsException
     */
    public function query(string $scope, string $code, array $vars = []): mixed
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::QUERY, [$scope, $code, $vars]);
        return $response->data;
    }

    /**
     * Run a procedure.
     * @link https://docs.thingsdb.io/v1/connect/socket/run/
     * @param string $scope
     * @param string $procedure
     * @param array $args
     * @return mixed
     * @throws ThingsException
     */
    public function run(string $scope, string $procedure, array $args = []): mixed
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::RUN, [$scope, $procedure, $args]);
        return $response->data;
    }

    /**
     * Join one or more room(s).
     * @link https://docs.thingsdb.io/v1/connect/socket/join/
     * @param string $scope
     * @param int[] $ids List of room(s) to join
     * @return int[] List of left room(s)
     * @throws ThingsException
     */
    public function join(string $scope, array $ids): array
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::JOIN, [$scope, ...array_map('intval', $ids)]);
        return $response->data;
    }

    /**
     * Leave one or more room(s).
     * @param string $scope
     * @param int[] $ids List of room(s) to leave
     * @return int[] List of leaved room(s)
     * @throws ThingsException
     */
    public function leave(string $scope, array $ids): array
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::LEAVE, [$scope, ...array_map('intval', $ids)]);
        return $response->data;
    }

    /**
     * Emit an event to a room.
     * @link https://docs.thingsdb.io/v1/connect/socket/emit/
     * @param string $scope
     * @param int $roomId
     * @param string $event
     * @param array $args
     * @return bool
     * @throws ThingsException
     */
    public function emit(string $scope, int $roomId, string $event, array $args = []): bool
    {
        $id = $this->getNextId();
        $response = $this->send($id, RequestType::EMIT, [$scope, $roomId, $event, ...$args]);
        return $response->type === ResponseType::OK;
    }

    /*
     * Listening
     */

    /**
     * Listen for incoming packages
     * @link https://docs.thingsdb.io/v1/listening/
     * @param float $timeLimit How long to wait in seconds <br/>
     * 0 - use timeout value from ThingsDB constructor<br/>
     * -1 - wait indefinitely
     * @return Response|null
     * @throws ThingsException
     */
    public function listening(float $timeLimit = 0): ?Response
    {
        if (!empty($this->responses))
            return array_shift($this->responses);

        if (!is_resource($this->socket))
            throw new ConnectException('Not connected');

        if ($timeLimit > 0 && $timeLimit > intval(ini_get('max_execution_time')))
            throw new ConnectException('Time limit can\'t be bigger than php max_execution_time');
        if ($timeLimit == 0)
            $timeLimit = $this->timeout;

        $read = [$this->socket];
        $write = null;
        $except = null;
        $result = stream_select($read, $write, $except, $timeLimit > 0 ? intval($timeLimit) : null, $timeLimit > 0 ? intval(($timeLimit - floor($timeLimit)) * 1_000_000) : null);
        return $result == 1 ? $this->read() : null;
    }

    /*
     * Connection methods
     */

    /**
     * Create new socket connection
     * @throws ConnectException
     */
    private function connect(): void
    {
        $this->socket = stream_socket_client($this->uri, $errno, $errstr, $this->timeout, context: !empty($this->context) ? stream_context_create($this->context) : null);
        if ($this->socket === false)
            throw new ConnectException($errstr, $errno);

        if (!stream_set_blocking($this->socket, false))
            throw new ConnectException('Cannot set socket into non-blocking mode');

        if (!stream_set_timeout($this->socket, $this->timeout))
            throw new ConnectException('Cannot set timeout on connection');

        if (array_key_exists('ssl', $this->context) && !empty($this->context['ssl'])) {
            while (true) {
                $result = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_ANY_CLIENT);
                if ($result)
                    break;
                elseif ($result === 0)
                    usleep(200_000);
                elseif ($result === false)
                    throw new ConnectException('Enable encryption error');
            }
        }
    }

    /**
     * Send package and immediately get the right response
     * @param int $id
     * @param RequestType $type
     * @param mixed|null $data
     * @return Response
     * @throws ThingsException
     */
    private function send(int $id, RequestType $type, mixed $data = null): Response
    {
        $this->write($id, $type, $data);

        do {
            $read = [$this->socket];
            $write = null;
            $except = null;
            $result = stream_select($read, $write, $except, 0, 200000);

            $response = null;
            switch ($result) {
                case 0:
                    continue 2;
                case 1:
                    $response = $this->read();
                    if ($response->id != $id)
                        $this->responses[$response->id] = $response;
                    break;
                default: //false
                    throw new ConnectException('Nothing to read available');
            }
        } while (is_null($response) || $response->id != $id);

        return $response;
    }

    /**
     * Write package into socket connection
     * @param int $id
     * @param RequestType $type
     * @param mixed|null $data
     * @return void
     * @throws ConnectException
     */
    private function write(int $id, RequestType $type, mixed $data = null): void
    {
        if (!is_resource($this->socket))
            throw new ConnectException('Not connected');

        $header = '';
        $packed = empty($data) ? '' : MessagePack::pack($data);

        $header .= pack('V', mb_strlen($packed, '8bit'));
        $header .= pack('v', $id);
        $header .= pack('c', $type->value);
        $header .= pack('c', ~$type->value);

        $buffer = $header . $packed;
        $size = mb_strlen($buffer, '8bit');
        while (0 < $size) {
            $sent = fwrite($this->socket, $buffer, $size);
            if ($sent === false)
                throw new ConnectException('Write into connection was not successful');
            $buffer = mb_strcut($buffer, $sent, null, '8bit');
            $size -= $sent;
        }
    }

    /**
     * Read package from socket connection
     * @return Response
     * @throws ThingsException
     */
    private function read(): Response
    {
        if (!is_resource($this->socket))
            throw new ConnectException('Not connected');

        $header = '';
        do {
            $read = stream_get_contents($this->socket, 8 - mb_strlen($header, '8bit'));
            if ($read === false)
                throw new ConnectException('Read from connection was not successful');
            $header .= $read;
        } while (mb_strlen($header, '8bit') < 8);

        if (mb_strlen($header, '8bit') !== 8)
            throw new ConnectException('Insufficient header length for received package: ' . mb_strlen($header, '8bit'));

        $length = (int)unpack('V', mb_strcut($header, 0, 4))[1];
        $id = (int)unpack('v', mb_strcut($header, 4, 2))[1];
        $type = (int)unpack('c', mb_strcut($header, 6, 1))[1];
        $check = (int)unpack('c', mb_strcut($header, 7, 1))[1];

        if ($type != ~$check)
            throw new ConnectException('Received package type mismatch: ' . $type . ' != ' . (~$check));

        if ($length == 0)
            return new Response($id, ResponseType::from($type));

        $buffer = '';
        do {
            $read = stream_get_contents($this->socket, $length - mb_strlen($buffer, '8bit'));
            if ($read === false)
                throw new ConnectException('Read from connection was not successful');
            $buffer .= $read;
        } while (mb_strlen($buffer, '8bit') < $length);

        $message = MessagePack::unpack($buffer);
        if (ResponseType::from($type) === ResponseType::ERROR)
            throw new PackageException($message['error_msg'], $message['error_code']);

        return new Response($id, ResponseType::from($type), $message);
    }

    /*
     * Other methods
     */

    /**
     * Get next available ID for package
     * @return int
     */
    private function getNextId(): int
    {
        $id = $this->id++;
        if ($this->id > 32767)
            $this->id = 1;
        return $id;
    }

    public function __destruct()
    {
        if (is_resource($this->socket)) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            $this->socket = null;
        }
    }
}
