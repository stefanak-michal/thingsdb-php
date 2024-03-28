<?php

namespace ThingsDB\tests;

use ThingsDB\error\{PackageException, ConnectException};
use ThingsDB\ThingsDB;

/**
 * Class ErrorsTest
 *
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/thingsdb-php
 * @package ThingsDB\tests
 */
class ErrorsTest extends ATest
{
    public function testWrongUri(): void
    {
        $this->expectException(ConnectException::class);
        new ThingsDB('this-is-not-uri', 3);
    }

    public function testPingError(): void
    {
        $thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 3);
        $rc = new \ReflectionClass($thingsDB);
        $rp = $rc->getProperty('socket');
        stream_socket_shutdown($rp->getValue($thingsDB), STREAM_SHUT_RDWR);
        $rp->setValue($thingsDB, null);

        $this->assertFalse($thingsDB->ping());
    }

    //todo https://docs.thingsdb.io/v1/errors/

    public function testAuthError(): void
    {
        $thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 3);
        $this->expectException(PackageException::class);
        $this->expectExceptionCode(-56);
        $thingsDB->auth('non-existing-user');
    }

    public function testTimeLimit(): void
    {
        set_time_limit(5);

        $thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 5);
        $this->expectException(ConnectException::class);
        $thingsDB->listening(15);

        set_time_limit(0);
    }

    public function testAssertErr(): void
    {
        $thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 3);
        $this->authUser($thingsDB);

        $this->expectException(PackageException::class);
        $this->expectExceptionCode(-50);
        $this->expectExceptionMessage('one is still smaller than two');
        $thingsDB->query('@thingsdb', 'assert(1 > 2, "one is still smaller than two");');
    }

    public function testSyntaxErr(): void
    {
        $thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 3);
        $this->authUser($thingsDB);

        $this->expectException(PackageException::class);
        $this->expectExceptionCode(-52);
        $thingsDB->query('@thingsdb', '| ;');
    }

    public function testLookupErr(): void
    {
        $thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 3);
        $this->authUser($thingsDB);

        $this->expectException(PackageException::class);
        $this->expectExceptionCode(-54);
        $thingsDB->query('@thingsdb', 'a[2];', ['a' => [1, 2]]);
    }

    public function testZeroDivErr(): void
    {
        $thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 3);
        $this->authUser($thingsDB);

        $this->expectException(PackageException::class);
        $this->expectExceptionCode(-58);
        $thingsDB->query('@thingsdb', '1/0;');
    }

    public function testOverflowErr(): void
    {
        $thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 3);
        $this->authUser($thingsDB);

        $this->expectException(PackageException::class);
        $this->expectExceptionCode(-59);
        $thingsDB->query('@thingsdb', 'int("' . PHP_INT_MAX . '1");');
    }

    public function testNumArgumentsErr(): void
    {
        $thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 3);
        $this->authUser($thingsDB);

        $this->expectException(PackageException::class);
        $this->expectExceptionCode(-62);
        $thingsDB->query('@thingsdb', 'room(1, 2);');
    }

}
