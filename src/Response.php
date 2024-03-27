<?php

namespace ThingsDB;

use ThingsDB\enum\ResponseType;

/**
 * Class Response - wrapped received package from ThingsDB
 *
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/thingsdb-php
 * @package ThingsDB
 */
readonly class Response
{
    public function __construct(public int $id, public ResponseType $type, public mixed $data = null)
    {
    }

}
