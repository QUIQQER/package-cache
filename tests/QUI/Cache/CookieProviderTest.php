<?php

namespace QUITests\Cache;

use PHPUnit\Framework\TestCase;
use QUI\Cache\CookieProvider;
use QUI\GDPR\CookieCollection;
use QUI\GDPR\CookieInterface;

class CookieProviderTest extends TestCase
{
    public function testGetCookiesReturnsCollectionWithLoggedInCookie(): void
    {
        $cookies = CookieProvider::getCookies();

        $this->assertInstanceOf(CookieCollection::class, $cookies);

        $list = $cookies->toArray();
        $this->assertNotEmpty($list);
        $this->assertInstanceOf(CookieInterface::class, $list[0]);
    }
}
