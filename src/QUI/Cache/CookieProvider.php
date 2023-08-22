<?php

namespace QUI\Cache;

use QUI\Cache\Cookie\LoggedInCookie;
use QUI\GDPR\CookieCollection;
use QUI\GDPR\CookieProviderInterface;
use QUI\Cache\Cookie\LoggedInCookieForGdpr;

class CookieProvider implements CookieProviderInterface
{
    public static function getCookies(): CookieCollection
    {
        $loggedInCookie = new LoggedInCookie(Config::getLoggedInCookieName());
        $loggedInCookieForGdpr = new LoggedInCookieForGdpr($loggedInCookie);

        return new CookieCollection([$loggedInCookieForGdpr]);
    }
}
