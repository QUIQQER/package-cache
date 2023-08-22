<?php

namespace QUI\Cache\Cookie;

use QUI;
use QUI\GDPR\CookieInterface;

/**
 * A class that implements quiqqer/gdpr CookieInterface and wraps the LoggedInCookie class
 *
 * The LoggedInCookie class itself can not implement CookieInterface.
 * It can't because otherwise it would depend on quiqqer/gdpr.
 *
 * But the LoggedInCookie class is required at another place to set the cookie.
 */
final class LoggedInCookieForGdpr implements CookieInterface
{
    private LoggedInCookie $loggedInCookie;

    public function __construct(LoggedInCookie $loggedInCookie)
    {
        $this->loggedInCookie = $loggedInCookie;
    }

    public function getCategory(): string
    {
        return CookieInterface::COOKIE_CATEGORY_ESSENTIAL;
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
}
