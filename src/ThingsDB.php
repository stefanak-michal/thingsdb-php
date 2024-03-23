<?php

namespace ThingsDB;

use ThingsDB\connection\IConnection;
use ThingsDB\enum\PackageType;
use ThingsDB\enum\ResponseType;

/**
 * Class ThingsDB
 * @package ThingsDB
 */
class ThingsDB
{

    public function __construct(private readonly IConnection $connection)
    {
        $this->connection->connect();
    }

    public function ping(): bool
    {
        $id = $this->connection->write(PackageType::PING);
        $response = $this->connection->read($id);
        return $response->type === ResponseType::PONG;
    }

    public function auth(string $username = 'admin', string $password = 'pass'): bool
    {
        $id = $this->connection->write(PackageType::AUTH, [$username, $password]);
        $response = $this->connection->read($id);
        return $response->type == ResponseType::OK;
    }

    public function authToken(string $token): bool
    {
        $id = $this->connection->write(PackageType::AUTH, $token);
        $response = $this->connection->read($id);
        return $response->type == ResponseType::OK;
    }

    public function query(string $scope, string $code, array $vars = [])
    {
        $id = $this->connection->write(PackageType::QUERY, empty($vars) ? [$scope, $code] : [$scope, $code, $vars]);
        $response = $this->connection->read($id);
        return $response->data;
    }

    public function run(string $scope, string $procedure, mixed ...$args)
    {
        $id = $this->connection->write(PackageType::RUN, [$scope, $procedure, ...$args]);
        $response = $this->connection->read($id);
        return $response->data;
    }

    public function join(string $scope, int ...$ids)
    {
        $id = $this->connection->write(PackageType::JOIN, [$scope, ...$ids]);
        $response = $this->connection->read($id);
        return $response->data;
    }

    public function leave(string $scope, int ...$ids)
    {
        $id = $this->connection->write(PackageType::LEAVE, [$scope, ...$ids]);
        $response = $this->connection->read($id);
        return $response->data;
    }

    public function emit(string $scope, int $roomId, string $event, mixed ...$args)
    {
        $id = $this->connection->write(PackageType::EMIT, [$scope, $roomId, $event, ...$args]);
        $response = $this->connection->read($id);
        return $response->type == ResponseType::OK;
    }
}
