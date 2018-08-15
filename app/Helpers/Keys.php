<?php

namespace App\Helpers;

use StephenHill\Base58;

/**
 * Class Keys
 */
class Keys
{
    public const EC_PUBLIC_START = '-----BEGIN PUBLIC KEY-----';
    public const EC_PUBLIC_END = '-----END PUBLIC KEY-----';
    public const EC_PRIVATE_START = '-----BEGIN EC PRIVATE KEY-----';
    public const EC_PRIVATE_END = '-----END EC PRIVATE KEY-----';

    /**
     * @param string $data
     * @return string
     */
    public static function pemToHexadecimal(string $data): string
    {
        return bin2hex(self::pemToBase64($data));
    }

    /**
     * @param string $data
     * @param bool   $isPrivateKey
     * @return string
     */
    public static function hexadecimalToPem(string $data, bool $isPrivateKey = false): string
    {
        $data = hex2bin($data);
        $data = base64_encode($data);

        return ($isPrivateKey) ?
            self::EC_PRIVATE_START.PHP_EOL.$data.PHP_EOL.self::EC_PRIVATE_END :
            self::EC_PUBLIC_START.PHP_EOL.$data.PHP_EOL.self::EC_PUBLIC_END;
    }

    /**
     * @param string $data
     * @return string
     */
    public static function pemToAroBase58(string $data): string
    {
        return app(Base58::class)->encode(self::pemToBase64($data));
    }

    /**
     * @param string $data
     * @param bool   $isPrivateKey
     * @return string
     */
    public static function aroBase58ToPem(string $data, bool $isPrivateKey = false): string
    {
        $data = base64_encode(app(Base58::class)->decode($data));

        $data = str_split($data, 64);
        $data = implode(PHP_EOL, $data);

        return ($isPrivateKey) ?
            self::EC_PRIVATE_START.PHP_EOL.$data.PHP_EOL.self::EC_PRIVATE_END :
            self::EC_PUBLIC_START.PHP_EOL.$data.PHP_EOL.self::EC_PUBLIC_END;
    }

    /**
     * @param string $data
     * @return string
     */
    private static function pemToBase64(string $data): string
    {
        return base64_decode(
            str_replace(
                [
                    self::EC_PUBLIC_START,
                    self::EC_PUBLIC_END,
                    self::EC_PRIVATE_START,
                    self::EC_PRIVATE_END,
                ],
                '',
                $data
            )
        );
    }
}
