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
        $thingsDB = new ThingsDB($_ENV['THINGSDB_URI'], 5, [
            'socket' => ['tcp_nodelay' => true],
            'ssl' => [
                'allow_self_signed' => true,
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        $this->assertTrue($thingsDB->ping());
        $this->authUser($thingsDB);

        $rc = new \ReflectionClass($thingsDB);
        $rp = $rc->getProperty('socket');
        $socket = $rp->getValue($thingsDB);
        $meta = stream_get_meta_data($socket);
        $this->assertArrayHasKey('crypto', $meta);
        $this->assertStringStartsWith('TLS', $meta['crypto']['protocol']);
    }
}
