<?php

namespace QUI\Cache;

use QUI;
use QUI\Cache\Cookie\LoggedInCookie;
use QUI\GDPR\CookieCollection;
use QUI\GDPR\CookieInterface;
use QUI\GDPR\CookieProviderInterface;

class CookieProvider implements CookieProviderInterface
{
    public static function getCookies(): CookieCollection
    {
        return new CookieCollection([static::provideLoggedInCookieForGdpr()]);
    }

    /**
     * Provides a class that implements quiqqer/gdpr CookieInterface based on the LoggedInCookie class
     *
     * The LoggedInCookie class itself does not implement CookieInterface.
     * It can't because otherwise it would depend on quiqqer/gdpr.
     */
    protected static function provideLoggedInCookieForGdpr(): CookieInterface
    {
        return new class implements CookieInterface {
            private LoggedInCookie $loggedInCookie;

            public function __construct()
            {
                $this->loggedInCookie = new LoggedInCookie(Config::getLoggedInCookieName());
            }

            public function getCategory(): string
            {
                return static::COOKIE_CATEGORY_ESSENTIAL;
            }

            public function getOrigin(): string
            {
                return QUI::getRequest()->getHost();
            }

            public function getPurpose(): string
            {
                return QUI::getLocale()->get(
                    'quiqqer/cache',
                    'cookie.logged_in.purpose'
                );
            }

            public function getLifetime(): string
            {
                return \sprintf(
                    '%d %s',
                    $this->loggedInCookie->getLifetimeInSeconds(),
                    QUI::getLocale()->get('quiqqer/quiqqer', 'seconds')
                );
            }

            public function getName(): string
            {
                return $this->loggedInCookie->getName();
            }
        };
    }
}
