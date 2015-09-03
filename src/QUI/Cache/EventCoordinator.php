<?php

/**
 * This file contains the \QUI\Cache\EventCoordinator
 */
namespace QUI\Cache;

use QUI;

/**
 * Class Events
 * Event handling for the cache
 *
 * @package QUI\Cache
 */
class EventCoordinator
{
    /**
     * event : on request
     *
     * @param QUI\Rewrite $Rewrite
     * @param string $url
     */
    static function onRequest($Rewrite, $url)
    {
        try {

            echo QUI\Cache\Handler::init()->getCacheFromRequest();
            exit;

        } catch (QUI\Exception $Exception) {

        }
    }

    /**
     * event : on request output
     *
     * @param string $output
     */
    static function onRequestOutput($output)
    {
        try {
            QUI\Cache\Handler::init()->generatCacheFromRequest($output);
        } catch (QUI\Exception $Exception) {

        }
    }

    /**
     * Clear the cache -> onSiteSave ...
     * look at <!-- clear cache --> in events.xml
     */
    static function clearCache()
    {
        QUI\Cache\Handler::init()->clearCache();
    }
}