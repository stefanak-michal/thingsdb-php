<?php

namespace ThingsDB\tests;

use PHPUnit\Framework\Attributes\Depends;
use ThingsDB\ThingsDB;

/**
 * Class ThingsDBTest
 *
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/thingsdb-php
 * @package ThingsDB\tests
 */
class ThingsDBTest extends ATest
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
        $this->authUser(self::$thingsDB);
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
        $this->stuffCollection(self::$thingsDB);
    }

    #[Depends('testAuth')]
    public function testAuthToken(): void
    {
        $token = self::$thingsDB->query('@thingsdb', 'new_token(user, datetime().move("minutes", 1));', ['user' => $_ENV['THINGSDB_USERNAME']]);
        $this->assertIsString($token);
        $this->assertTrue(self::$thingsDB->authToken($token));
    }

    #[Depends('testCollection')]
    public function testProcedure(): void
    {
        $this->procedure(self::$thingsDB, 'add_one', '|x| x + 1');

        $result = self::$thingsDB->run('@:stuff', 'add_one', [41]);
        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

    #[Depends('testCollection')]
    public function testProcedure2(): void
    {
        $this->procedure(self::$thingsDB, 'multiply', '|x, y| x * y');

        $result = self::$thingsDB->run('@:stuff', 'multiply', [4, 5]);
        $this->assertIsInt($result);
        $this->assertEquals(20, $result);
    }

    #[Depends('testCollection')]
    public function testProcedure3(): void
    {
        $this->procedure(self::$thingsDB, 'say_hello', '|| "Hello World!"');

        $result = self::$thingsDB->run('@:stuff', 'say_hello');
        $this->assertIsString($result);
        $this->assertEquals('Hello World!', $result);
    }

}
