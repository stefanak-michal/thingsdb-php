<?php

namespace ThingsDB\test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Depends;
use ThingsDB\connection\StreamSocket;
use ThingsDB\ThingsDB;

/**
 * Class ThingsDBTest
 * @package test
 */
class ThingsDBTest extends TestCase
{
    public function testConnect(): ThingsDB
    {
        $thingsDB = new ThingsDB(new StreamSocket(timeout: 3));
        $this->assertInstanceOf(ThingsDB::class, $thingsDB);

        return $thingsDB;
    }

    #[Depends('testConnect')]
    public function testPing(ThingsDB $thingsDB): ThingsDB
    {
        $this->assertTrue($thingsDB->ping());
        return $thingsDB;
    }

    #[Depends('testPing')]
    public function testAuthError(ThingsDB $thingsDB): void
    {
        $this->expectExceptionCode(-56);
        $thingsDB->auth('non-existing-user');
    }

    #[Depends('testPing')]
    public function testAuth(ThingsDB $thingsDB): void
    {
        $this->assertTrue($thingsDB->auth($_ENV['THINGSDB_USERNAME'], $_ENV['THINGSDB_PASSWORD']));
    }



    // todo test for authToken but first, can I call new_token with QUERY (or RUN) ?


}
