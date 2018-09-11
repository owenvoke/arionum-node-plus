<?php

namespace Tests\Unit\Helpers;

use App\Helpers\Blacklist;
use Tests\TestCase;

/**
 * Class BlacklistTest
 */
class BlacklistTest extends TestCase
{
    /**
     * @test
     * @return void
     */
    public function itReturnsFalseForAKeyThatIsNotBlacklisted()
    {
        $result = Blacklist::checkPublicKey('ok-key');

        $this->assertInternalType('bool', $result);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @return void
     */
    public function itReturnsTrueForAKeyThatIsBlacklisted()
    {
        $result = Blacklist::checkPublicKey(key(Blacklist::PUBLIC_KEYS));

        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }
}
