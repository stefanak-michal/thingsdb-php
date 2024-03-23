<?php

namespace ThingsDB\connection;

use MessagePack\MessagePack;
use ThingsDB\enum\PackageType;
use ThingsDB\enum\ResponseType;
use ThingsDB\Exception;
use ThingsDB\Response;

/**
 * Class StreamSocket
 * @package ThingsDB\connection
 */
class StreamSocket implements IConnection
{
    private $socket;
    private int $id = 1;

    public function __construct(public readonly string $uri = '127.0.0.1:9200', public readonly float $timeout = 15)
    {
    }

    public function connect(): bool
    {
        $this->socket = @stream_socket_client($this->uri, $errno, $errstr, 3);
        if ($this->socket === false)
            throw new Exception($errstr, $errno);

        if (!stream_set_blocking($this->socket, true))
            throw new Exception('Cannot set socket into blocking mode');

        if (!stream_set_timeout($this->socket, $this->timeout))
            throw new Exception('Cannot set timeout on connection');

        var_dump(stream_get_meta_data($this->socket));
        return is_resource($this->socket);
    }

    public function write(PackageType $type, mixed $data = null): int
    {
        $header = "";
        $packed = empty($data) ? "" : MessagePack::pack($data);

        $header .= pack('V', mb_strlen($packed, '8bit'));

        $id = $this->getNextId();
        $header .= pack('v', $id);

        $header .= pack('c', $type->value);
        $header .= pack('c', ~ $type->value);

        // todo add buffering
        $this->printHex($header . $packed);
        if (!fwrite($this->socket, $header . $packed))
            throw new Exception('Write into connection was not successful');
        return $id;
    }

    public function read(int $expectId): Response
    {
        $header = stream_get_contents($this->socket, 8);
        $this->printHex($header, 'S: ');

        $length = (int)unpack('V', mb_strcut($header, 0, 4))[1];
        $id = (int)unpack('v', mb_strcut($header, 4, 2))[1];
        $type = (int)unpack('c', mb_strcut($header, 6, 1))[1];
        $check = (int)unpack('c', mb_strcut($header, 7, 1))[1];

        if ($expectId == $id && $type == ~$check) {
            if ($length == 0) return new Response(ResponseType::from($type));
            $buffer = stream_get_contents($this->socket, $length);
            $this->printHex($buffer, 'S: ');

            $message = MessagePack::unpack($buffer);
            if (ResponseType::from($type) === ResponseType::ERROR)
                throw new Exception($message['error_msg'], $message['error_code']);

            return new Response(ResponseType::from($type), MessagePack::unpack($message));
        }

        throw new Exception('Received message mismatch [expected, received]: [' . $expectId . ', ' . $id . '] [' . $type . ', ' . (~$check) . ']');
    }

    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            unset($this->socket);
        }
    }

    private function getNextId(): int
    {
        $id = $this->id++;
        if ($this->id > 65535)
            $this->id = 1;
        return $id;
    }

    protected function printHex(string $str, string $prefix = 'C: '): void
    {
        $str = implode(unpack('H*', $str));
        echo '<pre>' . $prefix;
        foreach (str_split($str, 8) as $chunk) {
            echo implode(' ', str_split($chunk, 2));
            echo '    ';
        }
        echo '</pre>' . PHP_EOL;
    }
}
