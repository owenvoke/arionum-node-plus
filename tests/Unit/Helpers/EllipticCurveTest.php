<?php

namespace Tests\Unit\Helpers;

use App\Helpers\EllipticCurve;
use Tests\TestCase;

/**
 * Class EllipticCurveTest
 */
class EllipticCurveTest extends TestCase
{
    // phpcs:disable Generic.Files.LineLength
    private const PRIVATE_KEY = 'Lzhp9LopCN52nowTXeeYixvrfHTJA1geEDuc7tg9Xyovg89rkwJ4sAbyhuu9h69n9fcFhN7814oA1QnDaS8wJ8ZPFrSL33ekezantdR9aW3WiHm3wM7ZtGxRTjs5BVzabzJ6AfHmmSBCackXkKBdsKo6swtqVqMUF';
    private const PUBLIC_KEY = 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSD1BMH8hd16tLkJWkpFAKV9UzeroAquAGP7rWzPk18SM5i7aPszwfBA7AaSMJy4i42hq7Bb7D8UZxe2WjTHmqeyVEB';
    // phpcs:enable

    /**
     * @test
     * @return void
     */
    public function itCanSignAStringWithAPrivateKey()
    {
        $result = EllipticCurve::sign('data', self::PRIVATE_KEY);

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
            self::PUBLIC_KEY
        );

        $this->assertTrue($result);
    }
}
