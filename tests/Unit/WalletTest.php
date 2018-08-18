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
        $this->assertInternalType('string', Wallet::generate()->getPrivateKey());
    }

    /**
     * @test
     * @return void
     */
    public function itCanRetrieveAPublicKeyFromANewWalletInstance(): void
    {
        $this->assertInternalType('string', Wallet::generate()->getPublicKey());
    }
}
