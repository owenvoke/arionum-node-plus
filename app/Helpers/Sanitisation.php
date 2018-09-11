<?php

namespace App\Helpers;

/**
 * Class Sanitisation
 */
final class Sanitisation
{
    /**
     * @param string $input
     * @return string
     */
    public static function sanitiseAlphanumeric(string $input): string
    {
        return preg_replace('/[^a-z0-9]/i', '', $input);
    }

    /**
     * @param string $address
     * @return string
     */
    public static function sanitiseIpAddress(string $address): string
    {
        return preg_replace('/[^a-f0-9\[\]\.\:]/i', '', $address);
    }

    /**
     * @param string $address
     * @return string
     */
    public static function sanitiseHostname(string $address): string
    {
        return preg_replace('/[^a-z0-9\.\-\:\/]/i', '', $address);
    }
}
