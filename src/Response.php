<?php

namespace ThingsDB;

use ThingsDB\enum\ResponseType;

/**
 * Class Response
 * @package ThingsDB
 */
readonly class Response
{
    public function __construct(public ResponseType $type, public mixed $data = "")
    {
    }

}
