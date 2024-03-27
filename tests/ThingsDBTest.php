<?php

namespace ThingsDB\tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Depends;
use ThingsDB\ThingsDB;

/**
 * Class ThingsDBTest
 *
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/thingsdb-php
 * @package ThingsDB\tests
 */
class ThingsDBTest extends TestCase
{
    private static ?ThingsDB $thingsDB;

    public static function setUpBeforeClass(): void
    {
        self::$thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 3);
    }

    public static function tearDownAfterClass(): void
    {
        self::$thingsDB = null;
    }

    public function testInstance(): void
    {
        $this->assertInstanceOf(ThingsDB::class, self::$thingsDB);
    }

    #[Depends('testInstance')]
    public function testPing(): void
    {
        $this->assertTrue(self::$thingsDB->ping());
    }

    #[Depends('testPing')]
    public function testAuth(): void
    {
        $this->assertTrue(self::$thingsDB->auth($_ENV['THINGSDB_USERNAME'], $_ENV['THINGSDB_PASSWORD']));
    }

    #[Depends('testAuth')]
    public function testHello(): void
    {
        $response = self::$thingsDB->query('@thingsdb', '"Hello World!";');
        $this->assertIsString($response);
        $this->assertEquals('Hello World!', $response);
    }

    #[Depends('testAuth')]
    public function testCollection(): void
    {
        $exists = self::$thingsDB->query('@thingsdb', 'has_collection(colName);', ['colName' => 'stuff']);
        $this->assertIsBool($exists);

        if (!$exists) {
            $name = self::$thingsDB->query('@thingsdb', 'new_collection(colName);', ['colName' => 'stuff']);
            $this->assertIsString($name);
            $this->assertEquals('stuff', $name);
        }
    }

    #[Depends('testCollection')]
    public function testQuery(): void
    {
        $id = self::$thingsDB->query('@:stuff', '.id();');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    #[Depends('testQuery')]
    public function testAuthToken(): void
    {
        $token = self::$thingsDB->query('@thingsdb', 'new_token(user, datetime().move("minutes", 1));', ['user' => $_ENV['THINGSDB_USERNAME']]);
        $this->assertIsString($token);
        $this->assertTrue(self::$thingsDB->authToken($token));
    }

    #[Depends('testAuthToken')]
    public function testProcedure(): void
    {
        $exists = self::$thingsDB->query('@:stuff', 'has_procedure(procName);', ['procName' => 'add_one']);
        $this->assertIsBool($exists);
        if ($exists) {
            $result = self::$thingsDB->query('@:stuff', 'del_procedure(procName);', ['procName' => 'add_one']);
            $this->assertNull($result);
        }

        $name = self::$thingsDB->query('@:stuff', 'new_procedure(procName, |x| x + 1);', ['procName' => 'add_one']);
        $this->assertIsString($name);
        $this->assertEquals('add_one', $name);
    }

    #[Depends('testProcedure')]
    public function testRun(): void
    {
        $result = self::$thingsDB->run('@:stuff', 'add_one', [41]);
        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

    #[Depends('testAuthToken')]
    public function testProcedure2(): void
    {
        $exists = self::$thingsDB->query('@:stuff', 'has_procedure(procName);', ['procName' => 'multiply']);
        $this->assertIsBool($exists);
        if ($exists) {
            $result = self::$thingsDB->query('@:stuff', 'del_procedure(procName);', ['procName' => 'multiply']);
            $this->assertNull($result);
        }

        $name = self::$thingsDB->query('@:stuff', 'new_procedure(procName, |x, y| x * y);', ['procName' => 'multiply']);
        $this->assertIsString($name);
        $this->assertEquals('multiply', $name);
    }

    #[Depends('testProcedure2')]
    public function testRun2(): void
    {
        $result = self::$thingsDB->run('@:stuff', 'multiply', [4, 5]);
        $this->assertIsInt($result);
        $this->assertEquals(20, $result);
    }

    #[Depends('testAuthToken')]
    public function testProcedure3(): void
    {
        $exists = self::$thingsDB->query('@:stuff', 'has_procedure(procName);', ['procName' => 'say_hello']);
        $this->assertIsBool($exists);
        if ($exists) {
            $result = self::$thingsDB->query('@:stuff', 'del_procedure(procName);', ['procName' => 'say_hello']);
            $this->assertNull($result);
        }

        $name = self::$thingsDB->query('@:stuff', 'new_procedure(procName, || "Hello World!");', ['procName' => 'say_hello']);
        $this->assertIsString($name);
        $this->assertEquals('say_hello', $name);
    }

    #[Depends('testProcedure3')]
    public function testRun3(): void
    {
        $result = self::$thingsDB->run('@:stuff', 'say_hello');
        $this->assertIsString($result);
        $this->assertEquals('Hello World!', $result);
    }

}
