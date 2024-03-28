<?php

namespace ThingsDB\tests;

use ThingsDB\ThingsDB;

/**
 * Class SecureTest
 *
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/thingsdb-php
 * @package ThingsDB\tests
 */
class SecureTest extends ATest
{
    public function testSsl(): void
    {
        if (!array_key_exists('THINGSDB_TOKEN', $_ENV))
            $this->markTestSkipped('ThingsDB playground access token not provided');

        $thingsDB = new ThingsDB('playground.thingsdb.net:9400', 5, [
            'socket' => ['tcp_nodelay' => true],
            'ssl' => ['verify_peer' => true]
        ]);
        $this->assertTrue($thingsDB->authToken($_ENV['THINGSDB_TOKEN']));
    }
}
