<?php

namespace Tests\Unit\Helpers;

use App\Helpers\Key;
use Tests\TestCase;

/**
 * Class KeyTest
 */
class KeyTest extends TestCase
{
    /**
     * @test
     * @return void
     */
    public function itCanConvertAnAroPrivateKeyToPem()
    {
        $result = Key::aroBase58ToPem(env('TEST_KEY_PRIVATE'), true);

        $this->assertInternalType('string', $result);
        $this->assertRegExp('/^'.Key::EC_PRIVATE_START.'([\s\S]+?)'.Key::EC_PRIVATE_END.'$/m', $result);
    }

    /**
     * @test
     * @return void
     */
    public function itCanConvertAnAroPublicKeyToPem()
    {
        $result = Key::aroBase58ToPem(env('TEST_KEY_PUBLIC'));

        $this->assertInternalType('string', $result);
        $this->assertRegExp('/^'.Key::EC_PUBLIC_START.'([\s\S]+?)'.Key::EC_PUBLIC_END.'$/m', $result);
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

        $result = Key::pemToAroBase58($pem);

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

        $result = Key::pemToAroBase58($pem);

        $this->assertInternalType('string', $result);
        $this->assertEquals(env('TEST_KEY_PUBLIC'), $result);
    }
}
