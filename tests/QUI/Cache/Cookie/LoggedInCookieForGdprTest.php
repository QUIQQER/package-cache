<?php

namespace QUITests\Cache\Cookie;

use PHPUnit\Framework\TestCase;
use QUI\Cache\Cookie\LoggedInCookie;
use QUI\Cache\Cookie\LoggedInCookieForGdpr;
use QUI\GDPR\CookieInterface;

class LoggedInCookieForGdprTest extends TestCase
{
    public function testWrapperExposesCookieData(): void
    {
        $cookie = new LoggedInCookie('user-is-logged-in');
        $wrapper = new LoggedInCookieForGdpr($cookie);

        $this->assertSame(CookieInterface::COOKIE_CATEGORY_ESSENTIAL, $wrapper->getCategory());
        $this->assertSame('user-is-logged-in', $wrapper->getName());
        $this->assertIsString($wrapper->getOrigin());
        $this->assertIsString($wrapper->getPurpose());
        $this->assertStringContainsString('31536000', $wrapper->getLifetime());
    }
}
