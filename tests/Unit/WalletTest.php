<?php

namespace Tests\Unit;

use App\Wallet;
use Tests\TestCase;

/**
 * Class WalletTest
 */
class WalletTest extends TestCase
{
    /**
     * @test
     * @return void
     */
    public function itCanGenerateANewWalletInstance(): void
    {
        $this->assertInstanceOf(Wallet::class, Wallet::generate());
    }

    /**
     * @test
     * @return void
     */
    public function itCanRetrieveAPrivateKeyFromANewWalletInstance(): void
    {
        $data = Wallet::generate()->getPrivateKey();

        $this->assertInternalType('string', $data);
        $this->assertStringStartsWith('Lzhp9LopC', $data);
    }

    /**
     * @test
     * @return void
     */
    public function itCanRetrieveAPublicKeyFromANewWalletInstance(): void
    {
        $data = Wallet::generate()->getPublicKey();

        $this->assertInternalType('string', $data);
        $this->assertStringStartsWith('PZ8Tyr4Nx8', $data);
    }
}
