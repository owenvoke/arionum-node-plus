<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Class WalletAddress
 */
class WalletAddress implements Rule
{
    /** @var string */
    public const MATCH_WALLET_ADDRESS = '/[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]+/';

    /**
     * Determine if the validation rule passes.
     *
     * @param  string $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (strlen($value) < 70 || strlen($value) > 128) {
            return false;
        }

        if (!preg_match(self::MATCH_WALLET_ADDRESS, $value)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The provided address (:value) is not valid.';
    }
}
