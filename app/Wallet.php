<?php

namespace App;

/**
 * Class Wallet
 */
class Wallet
{
    /**
     * The wallet configuration parameters.
     */
    public const CONFIGURATION = [
        'curve_name'       => 'secp256k1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ];

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
     * @return self
     */
    public static function make(): self
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

        return new static($properties);
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
}
