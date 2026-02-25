<?php

namespace QUITests\Cache\Cookie;

use PHPUnit\Framework\TestCase;
use QUI\Cache\Cookie\LoggedInCookie;

class LoggedInCookieTest extends TestCase
{
    public function testNameAndLifetime(): void
    {
        $cookie = new LoggedInCookie('user-is-logged-in');

        $this->assertSame('user-is-logged-in', $cookie->getName());
        $this->assertSame(31536000, $cookie->getLifetimeInSeconds());
    }
}
