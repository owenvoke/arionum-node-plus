<?php

namespace App\Helpers;

use StephenHill\Base58;

/**
 * Class EllipticCurve
 */
class EllipticCurve
{
    /**
     * @param string $data
     * @param string $key
     * @return string
     */
    public static function sign(string $data, string $key): string
    {
        $privateKey = Key::aroBase58ToPem($key, true);
        $privateKeyId = openssl_pkey_get_private($privateKey);
        openssl_sign($data, $signature, $privateKeyId, OPENSSL_ALGO_SHA256);

        return app(Base58::class)->encode($signature);
    }

    /**
     * @param string $data
     * @param string $signature
     * @param string $key
     * @return bool
     */
    public static function verify(string $data, string $signature, string $key): bool
    {
        $publicKey = Key::aroBase58ToPem($key);
        $signature = app(Base58::class)->encode($signature);
        $publicKeyId = openssl_pkey_get_public($publicKey);

        return (bool)openssl_verify($data, $signature, $publicKeyId, OPENSSL_ALGO_SHA256);
    }
}
