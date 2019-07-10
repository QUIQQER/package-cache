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
    public static function onRequest($Rewrite, $url)
    {
        try {
            $cacheEnabled = QUI::getPackage('quiqqer/cache')->getConfig()->get('settings', 'cache');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $cacheEnabled = false;
        }


        if (!boolval($cacheEnabled)) {
            return;
        }

        $getParams  = $_GET;
        $postParams = $_POST;

        if (isset($getParams['_url'])) {
            unset($getParams['_url']);
        }

        // query strings have no cache
        if (!empty($getParams) || !empty($postParams)) {
            return;
        }

        // loged in users get no cache
        if (QUI::getUsers()->isAuth(QUI::getUserBySession())) {
            return;
        }

        try {
            $Response = QUI::getGlobalResponse();
            $Response->setContent(QUI\Cache\Handler::init()->getCacheFromRequest());
            $Response->send();
            exit;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage(), [
                'trace' => $Exception->getTraceAsString()
            ]);
        }
    }

    /**
     * event : on request output
     *
     * @param string $output
     */
    public static function onRequestOutput($output)
    {
        $getParams  = $_GET;
        $postParams = $_POST;

        if (isset($getParams['_url'])) {
            unset($getParams['_url']);
        }

        // query strings have no cache
        if (!empty($getParams) || !empty($postParams)) {
            return;
        }

        // logged in users get no cache
        if (QUI::getUsers()->isAuth(QUI::getUserBySession())) {
            return;
        }

        $Response = QUI::getGlobalResponse();

        if ($Response->getStatusCode() !== 200) {
            return;
        }

        try {
            if (QUI::getRewrite()->getSite()->getAttribute('nocache')) {
                return;
            }

            $Package      = QUI::getPackage('quiqqer/cache');
            $cacheSetting = $Package->getConfig()->get('settings', 'cache');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        if (!$cacheSetting) {
            return;
        }

        try {
            QUI\Cache\Handler::init()->generateCacheFromRequest($output);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage(), [
                'trace' => $Exception->getTraceAsString()
            ]);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * event : on package config save
     *
     * @param QUI\Package\Package $Package
     */
    public static function onPackageConfigSave(QUI\Package\Package $Package)
    {
        if ($Package->getName() !== 'quiqqer/cache') {
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
    public static function onTemplateGetHeader(QUI\Template $Template)
    {
        try {
            $Package      = QUI::getPackage('quiqqer/cache');
            $cacheSetting = $Package->getConfig()->get('settings', 'cache');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        $Template->extendHeader(
            "<script>
                var QUIQQER_CACHE_CACHESETTING = {$cacheSetting};
            </script>"
        );

//        $Template->extendHeaderWithJavaScriptFile(
//            URL_OPT_DIR . 'quiqqer/cache/bin/sw/html/init.js'
//        );

        $Template->extendHeaderWithJavaScriptFile(
            URL_OPT_DIR.'quiqqer/cache/bin/requireBundler.js'
        );
    }

    /**
     * Clear the cache -> onSiteSave ...
     * look at <!-- clear cache --> in events.xml
     */
    public static function clearCache()
    {
        QUI\Cache\Handler::init()->clearCache();
    }

    /**
     * event : on image create size cache
     *
     * @param QUI\Projects\Media\Item $Image
     * @param \Intervention\Image\Image $Cache
     */
    public static function onMediaCreateSizeCache(QUI\Projects\Media\Item $Image, \Intervention\Image\Image $Cache)
    {
        try {
            switch ($Cache->extension) {
                case 'jpg':
                    Optimizer::optimizeJPG($Cache->basePath());
                    break;

                case 'png':
                    Optimizer::optimizePNG($Cache->basePath());
                    break;
            }

            return;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addWarning($Exception->getMessage());
        }

        $Cache->save(null, 70);
    }
}
