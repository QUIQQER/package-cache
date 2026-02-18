<?php

namespace QUITests\Cache;

use PHPUnit\Framework\TestCase;
use QUI\Cache\Config;

class ConfigTest extends TestCase
{
    public function testConfigAccessorsReturnExpectedTypes(): void
    {
        $this->assertIsBool(Config::isLoggedInCookieEnabled());
        $this->assertIsString(Config::getLoggedInCookieName());
        $this->assertIsInt(Config::getHtmlCacheMaxAgeHeaderValue());
    }
}
