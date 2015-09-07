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
        $Request = QUI::getRequest();
        $query   = $Request->getQueryString();

        // query strings have no cache
        if (!is_null($query)) {
            return;
        }

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
        $Request = QUI::getRequest();
        $query   = $Request->getQueryString();

        // query strings have no cache
        if (!is_null($query)) {
            return;
        }

        $Package      = QUI::getPackage('quiqqer/cache');
        $cacheSetting = $Package->getConfig()->get('settings', 'cache');

        if (!$cacheSetting) {
            return;
        }

        try {
            QUI\Cache\Handler::init()->generatCacheFromRequest($output);
        } catch (QUI\Exception $Exception) {

        }
    }

    /**
     * event : on package config save
     *
     * @param QUI\Package\Package $Package
     */
    static function onPackageConfigSave(QUI\Package\Package $Package)
    {
        if ($Package->getName() != 'quiqqer/cache') {
            return;
        }

        // clear the cache
        QUI\Cache\Handler::init()->clearCache();
    }

    /**
     * event : on template get header
     * Extend the header with the require js php bundler
     *
     * @param QUI\Template $Template
     */
    static function onTemplateGetHeader(QUI\Template $Template)
    {
        $Package          = QUI::getPackage('quiqqer/cache');
        $cacheSetting     = $Package->getConfig()->get('settings', 'cache');

        $Template->extendHeader(
            "<script>
                var QUIQQER_CACHE_CACHESETTING = {$cacheSetting}
            </script>"
        );

        $Template->extendHeaderWithJavaScriptFile(
            URL_OPT_DIR . 'quiqqer/cache/bin/requireBundler.js'
        );
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