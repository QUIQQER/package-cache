<?php

/**
 * This file contains the \QUI\Cache\EventCoordinator
 */

namespace QUI\Cache;

use QUI;
use QUI\Utils\System\File;

use function boolval;
use function count;
use function define;
use function defined;
use function explode;
use function file_exists;
use function ltrim;
use function pathinfo;
use function preg_match_all;
use function str_replace;
use function strlen;
use function substr;

use const FILEINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * Class Events
 * Event handling for the cache
 *
 * @package QUI\Cache
 */
class EventCoordinator
{
    /**
     * @param $url
     */
    public static function onRequestImageNotFound($url)
    {
        $ext = pathinfo($url, PATHINFO_EXTENSION);

        if ($ext !== 'webp') {
            return;
        }

        $project = explode('/', $url)[2];

        if (!QUI::getProjectManager()::existsProject($project)) {
            return;
        }

        $file    = CMS_DIR . $url;
        $parts   = pathinfo($file);

        $filenameParts = explode('__', $parts['filename']);
        $filename      = $filenameParts[0];

        $filenameDir = str_replace(
            CMS_DIR . 'media/cache/' . $project,
            '',
            $parts['dirname']
        );

        if (!empty($filenameDir)) {
            $filenameDir = $filenameDir . DIRECTORY_SEPARATOR;
        }

        $filenameDir = ltrim($filenameDir, DIRECTORY_SEPARATOR);

        // wanted sizes
        $height = false;
        $width  = false;

        if (isset($filenameParts[1])) {
            $sizeParts = explode('x', $filenameParts[1]);

            if (isset($sizeParts[0])) {
                $width = $sizeParts[0];
            }

            if (isset($sizeParts[1])) {
                $height = $sizeParts[1];
            }
        }

        // look after the original image
        try {
            $result = QUI::getDataBase()->fetch([
                'from'  => QUI::getDBTableName($project . '_media'),
                'where' => [
                    'file' => [
                        'type'  => 'LIKE%',
                        'value' => $filenameDir . $filename . '.'
                    ]
                ],
                'limit' => 1
            ]);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());

            return;
        }

        if (!count($result)) {
            return;
        }

        $originalFile  = CMS_DIR . 'media/sites/' . $project . '/' . $result[0]['file'];
        $originalCache = CMS_DIR . 'media/cache/' . $project . '/' . $result[0]['file'];

        if (defined('FILEINFO_EXTENSION')) {
            $originalExtension = pathinfo($originalFile, FILEINFO_EXTENSION);
        } else {
            /* @deprecated */
            $pathInfo          = pathinfo($originalFile);
            $originalExtension = $pathInfo['extension'];
        }

        if (!file_exists($originalFile)) {
            return;
        }

        // check if cache image with filesize exists
        $cacheFile = str_replace('.webp', '.' . $originalExtension, $file);

        if (file_exists($cacheFile)) {
            $webPFile = Optimizer::convertToWebP($cacheFile);
            self::outputWebP($webPFile);

            return;
        }

        // if original cache doesn't exists, and we need no sizes
        if ($width === false && $height === false && file_exists($originalCache)) {
            $webPFile = Optimizer::convertToWebP($originalCache);
            self::outputWebP($webPFile);

            return;
        }

        // if original cache doesn't exists, create it
        try {
            $Project = QUI::getProject($project);
            $Media   = $Project->getMedia();
            $Image   = $Media->get($result[0]['id']);

            if ($width === false && $height === false) {
                $sizeCacheFile = $Image->createCache();
            } else {
                $sizeCacheFile = $Image->createResizeCache($width, $height);
            }

            $webPFile = Optimizer::convertToWebP($sizeCacheFile);
            self::outputWebP($webPFile);

            return;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }
    }

    /**
     * @param $webPFile
     */
    public static function outputWebP($webPFile)
    {
        if (file_exists($webPFile)) {
            try {
                QUI\Utils\System\File::fileHeader($webPFile);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }
    }

    /**
     * event : on request
     *
     * @param QUI\Rewrite $Rewrite
     * @param string $url
     */
    public static function onRequest(QUI\Rewrite $Rewrite, string $url)
    {
        if (!defined('NO_INTERNAL_CACHE')) {
            define('NO_INTERNAL_CACHE', true); // use only website cache, not the quiqqer internal cache
        }


        try {
            $cacheEnabled = QUI::getPackage('quiqqer/cache')->getConfig()->get('settings', 'cache');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $cacheEnabled = false;
        }

        try {
            $ignoreWebpCheck = (bool)QUI::getPackage('quiqqer/cache')->getConfig()->get('settings', 'ignoreWebPCheck');

            if (isset($_SERVER['HTTP_ACCEPT'])
                && QUI::getPackage('quiqqer/cache')->getConfig()->get('settings', 'webp')) {
                // if webp supported, use it
                if (strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false
                    || isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], ' Chrome/') !== false
                    || $ignoreWebpCheck
                ) {
                    // webp is supported!
                } else {
                    // webp is not supported!
                    $cacheEnabled = false;

                    if (!defined('QUIQQER_CACHE_DISABLE_WEBP')) {
                        define('QUIQQER_CACHE_DISABLE_WEBP', true);
                    }
                }
            }
        } catch (QUI\Exception $Exception) {
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
            $content  = QUI\Cache\Handler::init()->getCacheFromRequest();
            $Response = QUI::getGlobalResponse();
            $Response->setContent($content);

            QUI\Cache\Parser\HTTP2ServerPush::parseCSS($content, $Response);
            QUI\Cache\Parser\HTTP2ServerPush::parseImages($content, $Response);

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
    public static function onRequestOutput(string &$output)
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
            $output = QUI\Cache\Parser\LazyLoading::getInstance()->parse($output);

            return;
        }

        try {
            if (QUI::getRewrite()->getSite()->getAttribute('nocache')) {
                return;
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
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

        // check project setting
        try {
            $Project = QUI::getRewrite()->getProject();

            if ((int)$Project->getConfig('website.nocache')) {
                return;
            }
        } catch (QUI\Exception $Exception) {
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
            URL_OPT_DIR . 'quiqqer/cache/bin/requireBundler.js'
        );
    }

    /**
     * Clear the cache -> onSiteSave ...
     * look at <!-- clear cache --> in events.xml
     */
    public static function clearCache()
    {
        if (QUI::getUsers()->isSystemUser(QUI::getUserBySession())) {
            return;
        }

        if (QUI::getUsers()->isNobodyUser(QUI::getUserBySession())) {
            return;
        }

        QUI\Cache\Handler::init()->clearCache();
    }

    /**
     * event : on image create size cache
     *
     * @param QUI\Projects\Media\Item $Image
     * @param \Intervention\Image\Image $Cache
     */
    public static function onMediaCreateSizeCache(
        QUI\Projects\Media\Item $Image,
        \Intervention\Image\Image $Cache
    ) {
        if (!($Image instanceof QUI\Projects\Media\Image)) {
            return;
        }

        try {
            $Package          = QUI::getPackage('quiqqer/cache');
            $optimizeOnResize = $Package->getConfig()->get('settings', 'optimize_on_resize');
            $useWebP          = Handler::init()->useWebP();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        if ($useWebP) {
            Optimizer::convertToWebP($Cache->basePath());
        }

        if (empty($optimizeOnResize)) {
            return;
        }

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

    /**
     * @param QUI\Projects\Media\Item $Item
     */
    public static function onMediaSave(QUI\Projects\Media\Item $Item)
    {
        if (!Handler::init()->useWebP()) {
            return;
        }

        if (!($Item instanceof QUI\Projects\Media\Image)) {
            return;
        }

        try {
            // delete all webp cache files
            $Media = $Item->getMedia();
            $cdir  = CMS_DIR . $Media->getCacheDir();
            $file  = $Item->getAttribute('file');

            $cachefile = $cdir . $file;
            $cacheData = pathinfo($cachefile);

            $fileData = File::getInfo($Item->getFullPath());
            $files    = File::readDir($cacheData['dirname'], true);
            $filename = $fileData['filename'];

            foreach ($files as $file) {
                $len = strlen($filename);

                if (substr($file, 0, $len + 2) == $filename . '__' && strpos($file, '.webp') !== false) {
                    File::unlink($cacheData['dirname'] . '/' . $file);
                }
            }

            // create the webp main cache
            $cacheFile = $Item->createCache();
        } catch (QUI\Exception $Exception) {
            return;
        }

        Optimizer::convertToWebP($cacheFile);

        // check if same file exists
        try {
            $Folder   = $Item->getParent();
            $filename = $Item->getPathinfo(PATHINFO_FILENAME);
            $children = $Folder->getChildrenByName($filename);

            if (count($children) >= 2) {
                QUI::getMessagesHandler()->addAttention(
                    QUI::getLocale()->get('quiqqer/cache', 'message.attention.webp.duplicate.file')
                );
            }
        } catch (QUI\Exception $Exception) {
        }
    }

    /**
     * @param $picture
     */
    public static function onMediaCreateImageHtml(&$picture)
    {
        if (Handler::init()->useWebP() === false) {
            return;
        }

        if (stripos($picture, '.png') === false
            && stripos($picture, '.jpg') === false
            && stripos($picture, '.jpeg') === false
        ) {
            return;
        }

        // rewrite image
        preg_match_all(
            '#(<source[^>]*>)#i',
            $picture,
            $sourceSets
        );

        if (!count($sourceSets)) {
            return;
        }

        if (!isset($sourceSets[0])) {
            return;
        }

        // last sourceSet
        $lastSourceSet = array_pop($sourceSets[0]);

        $picture = str_ireplace([
            '.png',
            '.jpeg',
            '.jpg'
        ], '.webp', $picture);

        $picture = str_ireplace([
            'image/png',
            'image/jpeg',
            'image/jpg'
        ], 'image/webp', $picture);

        // add fallback png / jpg
        $picture = \preg_replace('#<img#i', $lastSourceSet . '<img', $picture);
    }

    /**
     * event: on quiqqer translator publish
     */
    public static function quiqqerTranslatorPublish()
    {
        self::clearCache();
    }

    /**
     * @param $smarty
     * @param $src
     * @param $params
     */
    public static function smartyImageOnlySource($smarty, &$src, &$params)
    {
        if (!Handler::init()->useWebP()) {
            return;
        }

        if (strpos($src, '.jpg') !== false || strpos($src, '.jpeg') !== false || strpos($src, '.png') !== false) {
            $src = str_replace([
                '.jpg',
                '.jpeg',
                '.png'
            ], '.webp', $src);
        }
    }

    /**
     * Don't use webp for mails
     *
     * @return void
     */
    public static function onMailerSendInit()
    {
        // this solution is not optimal
        // if a mail is sent during the normal running system, the webp is off for everyone (smarty too)
        if (!defined('QUIQQER_CACHE_DISABLE_WEBP')) {
            define('QUIQQER_CACHE_DISABLE_WEBP', true);
        }
    }
}
