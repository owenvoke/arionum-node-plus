<?php

namespace Tests\Unit\Helpers;

use App\Helpers\Sanitisation;
use Tests\TestCase;

/**
 * Class SanitisationTest
 */
class SanitisationTest extends TestCase
{
    /**
     * @test
     * @return void
     */
    public function itCanSanitiseANonAlphanumericString()
    {
        $result = Sanitisation::sanitiseAlphanumeric('TEST&*^%$£"!');

        $this->assertEquals('TEST', $result);
    }

    /**
     * @test
     * @return void
     */
    public function itCanSanitiseAnIpAddress()
    {
        $result = Sanitisation::sanitiseIpAddress('127.0.0.1');

        $this->assertEquals('127.0.0.1', $result);
    }

    /**
     * @test
     * @return void
     */
    public function itCanSanitiseAHostname()
    {
        $result = Sanitisation::sanitiseHostname('https://aro.pxgamer.xyz$');

        $this->assertEquals('https://aro.pxgamer.xyz', $result);
    }
}
