<?php

namespace ThingsDB\tests;

use PHPUnit\Framework\Attributes\Depends;
use ThingsDB\enum\ResponseType;
use ThingsDB\ThingsDB;

/**
 * Class ListeningTest
 *
 * @author Michal Stefanak
 * @link https://github.com/stefanak-michal/thingsdb-php
 * @package ThingsDB\tests
 */
class ListeningTest extends ATest
{
    private static int $roomId;

    private static ?ThingsDB $thingsDB;

    public static function setUpBeforeClass(): void
    {
        self::$thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 3);
    }

    public static function tearDownAfterClass(): void
    {
        self::$thingsDB = null;
    }

    public function testAuth(): void
    {
        $this->authUser(self::$thingsDB);
    }

    #[Depends('testAuth')]
    public function testCollection(): void
    {
        $this->stuffCollection(self::$thingsDB);
    }

    #[Depends('testCollection')]
    public function testCreateRoom(): void
    {
        self::$roomId = self::$thingsDB->query('@:stuff', '.my_room = room(); .my_room.id();');
        $this->assertIsInt(self::$roomId);
    }

    #[Depends('testCreateRoom')]
    public function testJoin(): void
    {
        $response = self::$thingsDB->join('@:stuff', [self::$roomId]);
        $this->assertEquals([self::$roomId], $response);

        $response = self::$thingsDB->listening();
        $this->assertEquals(ResponseType::ON_JOIN, $response->type);
        $this->assertEquals(self::$roomId, $response->data['id']);
    }

    #[Depends('testJoin')]
    public function testEmitWithArgument(): void
    {
        $success = self::$thingsDB->emit('@:stuff', self::$roomId, 'test-event', ['Testing event 1']);
        $this->assertTrue($success);

        $response = self::$thingsDB->listening();
        $this->assertEquals(ResponseType::ON_EMIT, $response->type);
        $this->assertEquals('test-event', $response->data['event']);
        $this->assertEquals('Testing event 1', $response->data['args'][0]);
    }

    #[Depends('testEmitWithArgument')]
    public function testEmitWithoutArgument(): void
    {
        $success = self::$thingsDB->emit('@:stuff', self::$roomId, 'test-event');
        $this->assertTrue($success);

        $response = self::$thingsDB->listening();
        $this->assertEquals(ResponseType::ON_EMIT, $response->type);
        $this->assertEquals('test-event', $response->data['event']);
        $this->assertEmpty($response->data['args']);
    }

    #[Depends('testEmitWithoutArgument')]
    public function testWaitForEmit(): void
    {
        $taskId = self::$thingsDB->query('@:stuff', 'task(
            datetime().move("seconds", 2), 
            || .my_room.emit("test-event", "Testing event 2")
        ).id();');
        $this->assertIsInt($taskId);

        $response = self::$thingsDB->listening();
        $this->assertEquals(ResponseType::ON_EMIT, $response->type);
        $this->assertEquals('test-event', $response->data['event']);
        $this->assertEquals('Testing event 2', $response->data['args'][0]);
    }

    #[Depends('testWaitForEmit')]
    public function testLeave(): void
    {
        $response = self::$thingsDB->leave('@:stuff', [self::$roomId]);
        $this->assertEquals([self::$roomId], $response);

        $response = self::$thingsDB->listening();
        $this->assertEquals(ResponseType::ON_LEAVE, $response->type);
        $this->assertEquals(self::$roomId, $response->data['id']);
    }

    #[Depends('testLeave')]
    public function testOnDelete(): void
    {
        $this->testJoin();
        $this->assertNotEmpty(self::$thingsDB->query('@:stuff', '.del("my_room");'));

        $response = self::$thingsDB->listening();
        $this->assertEquals(ResponseType::ON_DELETE, $response->type);
        $this->assertEquals(self::$roomId, $response->data['id']);
    }

}
