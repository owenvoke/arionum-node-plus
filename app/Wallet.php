<?php

namespace App;

use StephenHill\Base58;

/**
 * Class Wallet
 */
final class Wallet
{
    /**
     * The wallet configuration parameters.
     */
    public const CONFIGURATION = [
        'curve_name'       => 'secp256k1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ];

    /**
     * The number of times to SHA512 hash a public key for addresses.
     */
    private const ADDRESS_SHA512_MULTIPLIER = 9;

    /**
     * @var null|string
     */
    private $address;

    /**
     * @var string
     */
    private $privateKey;
    /**
     * @var string
     */
    private $publicKey;

    /**
     * Wallet constructor.
     * @param array $properties
     */
    public function __construct(array $properties = [])
    {
        foreach ($properties as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }

    /**
     * @param array $properties
     * @return self
     */
    private static function make(array $properties)
    {
        return new static($properties);
    }

    /**
     * @return self
     */
    public static function generate(): self
    {
        $properties = [];

        // Generate a new key pair
        $keySet = openssl_pkey_new(static::CONFIGURATION);

        // Export the private key encoded as a PEM string and convert to a Base58 format
        openssl_pkey_export($keySet, $exportedKey);
        $properties['privateKey'] = Helpers\Key::pemToAroBase58($exportedKey);

        // Export the public key encoded as a PEM array and convert to a Base58 format
        $pemKeyDetails = openssl_pkey_get_details($keySet);
        $properties['publicKey'] = Helpers\Key::pemToAroBase58($pemKeyDetails['key']);

        return static::make($properties);
    }

    /**
     * @return string
     */
    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    /**
     * @return string
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @return string
     */
    public function getAddress(): string
    {
        if (!$this->address) {
            $tempHash = $this->publicKey;
            for ($i = 0; $i < self::ADDRESS_SHA512_MULTIPLIER; $i++) {
                $tempHash = hash('sha512', $tempHash, true);
            }

            $this->address = app(Base58::class)->encode($tempHash);
        }

        return $this->address;
    }
}
