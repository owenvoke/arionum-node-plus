<?php

namespace Tests\Unit\Helpers;

use App\Helpers\EllipticCurve;
use Tests\TestCase;

/**
 * Class EllipticCurveTest
 */
class EllipticCurveTest extends TestCase
{
    /**
     * @test
     * @return void
     */
    public function itCanSignAStringWithAPrivateKey()
    {
        $result = EllipticCurve::sign('data', env('TEST_KEY_PRIVATE'));

        $this->assertInternalType('string', $result);
    }

    /**
     * @test
     * @return void
     */
    public function itCanVerifyAStringWithAPublicKey()
    {
        $result = EllipticCurve::verify(
            'data',
            'iKx1CJNmCAJ82aQZjhPfdiiSsXGMZbGBqpBSXV66n7g335fJ9oevVTpxG4BokhnoUpvTD8rKXoVjbWM9rk7KyzW9SADest9Bsq',
            env('TEST_KEY_PUBLIC')
        );

        $this->assertTrue($result);
    }
}
