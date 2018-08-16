<?php

namespace Tests\Unit\Validators;

use App\Rules\WalletAddress;
use Tests\TestCase;

/**
 * Class WalletAddressTest
 */
class WalletAddressTest extends TestCase
{
    /**
     * @test
     * @return void
     */
    public function itValidatesSuccessfullyForAValidAddress()
    {
        $this->assertTrue(\Validator::make([
            '51sJ4LbdKzhyGy4zJGqodNLse9n9JsVT2rdeH92w7cf3qQuSDJupvjbUT1UBr7r1SCUAXG97saxn7jt2edKb4v4J',
        ], [new WalletAddress()])->passes());
    }

    /**
     * @test
     * @return void
     */
    public function itFailsToValidateOnAnInvalidAddress()
    {
        $this->assertTrue(\Validator::make(['fake_wallet_address'], [new WalletAddress()])->fails());
    }
}
