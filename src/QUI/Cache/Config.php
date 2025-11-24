<?php

namespace QUI\Cache;

use QUI;

class Config
{
    public static function isLoggedInCookieEnabled(): bool
    {
        $isCookieEnabled = false;

        try {
            $package = QUI::getPackage('quiqqer/cache');
            $config = $package->getConfig();
            $isCookieEnabled = $config?->get('settings', 'login_cookie_is_enabled');
        } catch (QUI\Exception $exception) {
            QUI\System\Log::writeException($exception);
        }

        return $isCookieEnabled;
    }

    public static function getLoggedInCookieName(): string
    {
        $name = 'user-is-logged-in';

        try {
            $package = QUI::getPackage('quiqqer/cache');
            $config = $package->getConfig();
            $name = $config?->get('settings', 'login_cookie_name');
        } catch (QUI\Exception $exception) {
            QUI\System\Log::writeException($exception);
        }

        return $name;
    }

    public static function getHtmlCacheMaxAgeHeaderValue(): int
    {
        $maxAge = 3600;

        try {
            $package = QUI::getPackage('quiqqer/cache');
            $config = $package->getConfig();
            $maxAge = $config?->get('settings', 'html_cache_max_age_header');
        } catch (QUI\Exception $exception) {
            QUI\System\Log::writeException($exception);
        }

        return $maxAge;
    }
}
