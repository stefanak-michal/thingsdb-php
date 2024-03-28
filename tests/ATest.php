<?php

namespace ThingsDB\tests;

use PHPUnit\Framework\TestCase;
use ThingsDB\ThingsDB;

/**
 * Abstract class ATest
 *
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/thingsdb-php
 * @package ThingsDB\tests
 */
abstract class ATest extends TestCase
{
    protected function authUser(ThingsDB $thingsDB): void
    {
        $this->assertTrue($thingsDB->auth($_ENV['THINGSDB_USERNAME'], $_ENV['THINGSDB_PASSWORD']));
    }

    protected function stuffCollection(ThingsDB $thingsDB): void
    {
        $exists = $thingsDB->query('@thingsdb', 'has_collection(colName);', ['colName' => 'stuff']);
        $this->assertIsBool($exists);

        if (!$exists) {
            $name = $thingsDB->query('@thingsdb', 'new_collection(colName);', ['colName' => 'stuff']);
            $this->assertIsString($name);
            $this->assertEquals('stuff', $name);
        }

        $id = $thingsDB->query('@:stuff', '.id();');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    protected function procedure(ThingsDB $thingsDB, string $procName, string $content): void
    {
        $exists = $thingsDB->query('@:stuff', 'has_procedure(procName);', ['procName' => $procName]);
        $this->assertIsBool($exists);
        if ($exists) {
            $result = $thingsDB->query('@:stuff', 'del_procedure(procName);', ['procName' => $procName]);
            $this->assertNull($result);
        }

        $name = $thingsDB->query('@:stuff', 'new_procedure(procName, ' . $content . ');', ['procName' => $procName]);
        $this->assertIsString($name);
        $this->assertEquals($procName, $name);
    }
}
