<?php

namespace ThingsDB\tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Depends;
use ThingsDB\ThingsDB;

/**
 * Class ThingsDBTest
 * @package test
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
    public function testCollection(): void
    {
        $exists = self::$thingsDB->query('@thingsdb', 'has_collection("stuff");');
        $this->assertIsBool($exists);

        if (!$exists) {
            $name = self::$thingsDB->query('@thingsdb', 'new_collection("stuff");');
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
        $token = self::$thingsDB->query('@thingsdb', 'new_token("' . $_ENV['THINGSDB_USERNAME'] . '", datetime().move("minutes", 1));');
        $this->assertIsString($token);
        $this->assertTrue(self::$thingsDB->authToken($token));
    }

    #[Depends('testAuthToken')]
    public function testProcedure(): void
    {
        $exists = self::$thingsDB->query('@:stuff', 'has_procedure("add_one");');
        $this->assertIsBool($exists);
        if ($exists) {
            $result = self::$thingsDB->query('@:stuff', 'del_procedure("add_one");');
            $this->assertNull($result);
        }

        $name = self::$thingsDB->query('@:stuff', 'new_procedure("add_one", |x| x + 1);');
        $this->assertIsString($name);
        $this->assertEquals('add_one', $name);
    }

    #[Depends('testProcedure')]
    public function testRun(): void
    {
        $result = self::$thingsDB->run('@:stuff', 'add_one', 41);
        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

}
