<?php

namespace ThingsDB\connection;

use ThingsDB\enum\PackageType;
use ThingsDB\Response;

interface IConnection
{
    public function __construct(string $uri = '127.0.0.1:9200', float $timeout = 15);

    public function connect(): bool;

    public function write(PackageType $type, mixed $data = null): int;

    public function read(int $expectId): Response;

    public function disconnect(): void;
}
