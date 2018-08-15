<?php

namespace Tests\Unit\Helpers;

use App\Helpers\Keys;
use Tests\TestCase;

/**
 * Class EllipticCurveTest
 */
class KeysTest extends TestCase
{
    /**
     * @test
     * @return void
     */
    public function itCanConvertAnAroPrivateKeyToPem()
    {
        $result = Keys::aroBase58ToPem(env('TEST_KEY_PRIVATE'), true);

        $this->assertInternalType('string', $result);
        $this->assertRegExp('/^'.Keys::EC_PRIVATE_START.'([\s\S]+?)'.Keys::EC_PRIVATE_END.'$/m', $result);
    }

    /**
     * @test
     * @return void
     */
    public function itCanConvertAnAroPublicKeyToPem()
    {
        $result = Keys::aroBase58ToPem(env('TEST_KEY_PUBLIC'));

        $this->assertInternalType('string', $result);
        $this->assertRegExp('/^'.Keys::EC_PUBLIC_START.'([\s\S]+?)'.Keys::EC_PUBLIC_END.'$/m', $result);
    }

    /**
     * @test
     * @return void
     */
    public function itCanConvertAPrivatePemKeyToAro()
    {
        $pem = <<<PEM
-----BEGIN PUBLIC KEY-----
MHQCAQEEIPSunO6lEx8ngkjYxlrjqdnJfT5zwwQAmeiT4kh7ITCboAcGBSuBBAAK
oUQDQgAE8SMMdRo/IOutZaJVL7wYavPkdZDhP/V4EIf4FyedWfQFGpNyyRxt/ydU
KF5JE9DN5Q5lp5wyEbvobAfWC0bB/A==
-----END PUBLIC KEY-----
PEM;

        $result = Keys::pemToAroBase58($pem);

        $this->assertInternalType('string', $result);
        $this->assertEquals(env('TEST_KEY_PRIVATE'), $result);
    }

    /**
     * @test
     * @return void
     */
    public function itCanConvertAPublicPemKeyToAro()
    {
        $pem = <<<PEM
-----BEGIN PUBLIC KEY-----
MFYwEAYHKoZIzj0CAQYFK4EEAAoDQgAE8SMMdRo/IOutZaJVL7wYavPkdZDhP/V4
EIf4FyedWfQFGpNyyRxt/ydUKF5JE9DN5Q5lp5wyEbvobAfWC0bB/A==
-----END PUBLIC KEY-----
PEM;

        $result = Keys::pemToAroBase58($pem);

        $this->assertInternalType('string', $result);
        $this->assertEquals(env('TEST_KEY_PUBLIC'), $result);
    }
}
