<?php

/**
 * This file contains the \QUI\Cache\EventCoordinator
 */

namespace QUI\Cache;

use DOMDocument;
use DOMElement;
use Intervention\Image\Image;
use QUI;
use QUI\Users\User;
use QUI\Utils\System\File;

use function count;
use function define;
use function defined;
use function explode;
use function file_exists;
use function header_remove;
use function ltrim;
use function pathinfo;
use function preg_replace;
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
     * @param string $url
     */
    public static function onRequestImageNotFound(string $url): void
    {
        $ext = pathinfo($url, PATHINFO_EXTENSION);

        if ($ext !== 'webp') {
            return;
        }

        $project = explode('/', $url)[2];

        if (!QUI::getProjectManager()::existsProject($project)) {
            return;
        }

        $file = CMS_DIR . $url;
        $parts = pathinfo($file);

        $filenameParts = explode('__', $parts['filename']);
        $filename = $filenameParts[0];

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
        $width = false;

        if (isset($filenameParts[1])) {
            $sizeParts = explode('x', $filenameParts[1]);
            $width = $sizeParts[0];

            if (isset($sizeParts[1])) {
                $height = $sizeParts[1];
            }
        }

        // look after the original image
        try {
            $result = QUI::getDataBase()->fetch([
                'from' => QUI::getDBTableName($project . '_media'),
                'where' => [
                    'file' => [
                        'type' => 'LIKE%',
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

        $originalFile = CMS_DIR . 'media/sites/' . $project . '/' . $result[0]['file'];
        $originalCache = CMS_DIR . 'media/cache/' . $project . '/' . $result[0]['file'];

        if (defined('FILEINFO_EXTENSION')) {
            $originalExtension = pathinfo($originalFile, FILEINFO_EXTENSION);
        } else {
            /* @deprecated */
            $pathInfo = pathinfo($originalFile);

            if (empty($pathInfo['extension'])) {
                return;
            }

            $originalExtension = $pathInfo['extension'];
        }

        if (!file_exists($originalFile)) {
            return;
        }

        // check if cache image with filesize exists
        $cacheFile = str_replace('.webp', '.' . $originalExtension, $file);

        if (file_exists($cacheFile)) {
            $webPFile = Optimizer::convertToWebP($cacheFile);
            self::outputWebP((string)$webPFile);

            return;
        }

        // if original cache doesn't exist, and we need no sizes
        if ($width === false && $height === false && file_exists($originalCache)) {
            $webPFile = Optimizer::convertToWebP($originalCache);
            self::outputWebP((string)$webPFile);

            return;
        }

        // if original cache doesn't exist, create it
        try {
            $Project = QUI::getProject($project);
            $Media = $Project->getMedia();
            $Image = $Media->get($result[0]['id']);

            if ($width === false && $height === false) {
                $sizeCacheFile = $Image->createCache();
            } elseif (method_exists($Image, 'createResizeCache')) {
                $sizeCacheFile = $Image->createResizeCache($width, $height);
            } else {
                return;
            }

            $webPFile = Optimizer::convertToWebP($sizeCacheFile);
            self::outputWebP((string)$webPFile);
            return;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }
    }

    /**
     * @param string $webPFile
     */
    public static function outputWebP(string $webPFile): void
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
    public static function onRequest(QUI\Rewrite $Rewrite, string $url): void
    {
        if (!defined('NO_INTERNAL_CACHE')) {
            define('NO_INTERNAL_CACHE', true); // use only website cache, not the quiqqer internal cache
        }

        $config = null;

        try {
            $config = QUI::getPackage('quiqqer/cache')->getConfig();
            $cacheEnabled = (bool)$config?->get('settings', 'cache');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $cacheEnabled = false;
        }

        try {
            $ignoreWebpCheck = (bool)$config?->get('settings', 'ignoreWebPCheck');

            if (
                isset($_SERVER['HTTP_ACCEPT'])
                && $config?->get('settings', 'webp')
            ) {
                // if webp supported, use it
                if (
                    str_contains($_SERVER['HTTP_ACCEPT'], 'image/webp')
                    || isset($_SERVER['HTTP_USER_AGENT']) && str_contains($_SERVER['HTTP_USER_AGENT'], ' Chrome/')
                    || $ignoreWebpCheck
                ) {
                    // webp is supported!
                } else {
                    // webp is not supported!
                    $cacheEnabled = false;

                    // no cache generating for this version
                    QUI::getRewrite()->getSite()?->setAttribute('nocache', true);

                    if (!defined('QUIQQER_CACHE_DISABLE_WEBP')) {
                        define('QUIQQER_CACHE_DISABLE_WEBP', true);
                    }
                }
            }
        } catch (QUI\Exception) {
        }

        if (!$cacheEnabled) {
            return;
        }

        $getParams = $_GET;
        $postParams = $_POST;

        if (isset($getParams['_url'])) {
            unset($getParams['_url']);
        }

        // query strings have no cache
        if (!empty($getParams) || !empty($postParams)) {
            return;
        }

        // logged-in users get no cache
        if (QUI::getUsers()->isAuth(QUI::getUserBySession())) {
            return;
        }

        try {
            $content = QUI\Cache\Handler::init()->getCacheFromRequest();
            $Response = QUI::getGlobalResponse();
            $Response->setContent($content);

            //QUI\Cache\Parser\HTTP2ServerPush::parseCSS($content, $Response);
            //QUI\Cache\Parser\HTTP2ServerPush::parseImages($content, $Response);

            $Response->headers->remove('Cache-Control');
            $Response->setCache([
                'max_age' => Config::getHtmlCacheMaxAgeHeaderValue(),
                'public' => true,
                'must_revalidate' => true
            ]);

            // Remove session cookie from cached responses as the response is not unique (see quiqqer/core#1290)
            header_remove('Set-Cookie');

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
    public static function onRequestOutput(string &$output): void
    {
        $getParams = $_GET;
        $postParams = $_POST;

        if (isset($getParams['_url'])) {
            unset($getParams['_url']);
        }

        // query strings have no cache
        if (!empty($getParams) || !empty($postParams)) {
            return;
        }

        // logged-in users get no cache
        if (QUI::getUsers()->isAuth(QUI::getUserBySession())) {
            $output = QUI\Cache\Parser\LazyLoading::getInstance()->parse($output);
            return;
        }

        try {
            if (QUI::getRewrite()->getSite()?->getAttribute('nocache')) {
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
            if (QUI::getRewrite()->getSite()?->getAttribute('nocache')) {
                return;
            }

            $Package = QUI::getPackage('quiqqer/cache');
            $cacheSetting = $Package->getConfig()?->get('settings', 'cache');
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

            if ((int)$Project?->getConfig('website.nocache')) {
                return;
            }
        } catch (QUI\Exception) {
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
    public static function onPackageConfigSave(QUI\Package\Package $Package): void
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
    public static function onTemplateGetHeader(QUI\Template $Template): void
    {
        try {
            $Package = QUI::getPackage('quiqqer/cache');
            $cacheSetting = $Package->getConfig()?->get('settings', 'cache');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        $Template->extendHeader(
            "<script>
                window.QUIQQER_CACHE_CACHESETTING = $cacheSetting;
            </script>"
        );
    }

    /**
     * Clear the cache -> onSiteSave ...
     * look at <!-- clear cache --> in events.xml
     */
    public static function clearCache(): void
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
     * @param Image $Cache
     */
    public static function onMediaCreateSizeCache(
        QUI\Projects\Media\Item $Image,
        Image $Cache
    ): void {
        if (!($Image instanceof QUI\Projects\Media\Image)) {
            return;
        }

        try {
            $Package = QUI::getPackage('quiqqer/cache');
            $optimizeOnResize = $Package->getConfig()?->get('settings', 'optimize_on_resize');
            $useWebP = Handler::init()->useWebP();
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

    public static function onMediaReplace(QUI\Projects\Media $Media, QUI\Projects\Media\Item $Item): void
    {
        if (!Handler::init()->useWebP()) {
            return;
        }

        if ($Item instanceof QUI\Projects\Media\Image) {
            try {
                $Item->deleteCache();
            } catch (QUI\Exception) {
            }
        }
    }

    /**
     * @param QUI\Projects\Media\Item $Item
     */
    public static function onMediaSave(QUI\Projects\Media\Item $Item): void
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
            $cdir = CMS_DIR . $Media->getCacheDir();
            $file = $Item->getAttribute('file');

            $cachefile = $cdir . $file;
            $cacheData = pathinfo($cachefile);

            $fileData = File::getInfo($Item->getFullPath());
            $files = File::readDir($cacheData['dirname'], true);
            $filename = $fileData['filename'];

            foreach ($files as $file) {
                $len = strlen($filename);

                if (substr($file, 0, $len + 2) == $filename . '__' && str_contains($file, '.webp')) {
                    File::unlink($cacheData['dirname'] . '/' . $file);
                }
            }

            // create the webp main cache
            $cacheFile = $Item->createCache();
        } catch (QUI\Exception) {
            return;
        }

        Optimizer::convertToWebP((string)$cacheFile);

        // check if same file exists
        try {
            $Folder = $Item->getParent();
            $filename = $Item->getPathinfo(PATHINFO_FILENAME);
            $children = $Folder->getChildrenByName($filename);

            if (count($children) >= 2) {
                QUI::getMessagesHandler()->addAttention(
                    QUI::getLocale()->get('quiqqer/cache', 'message.attention.webp.duplicate.file')
                );
            }
        } catch (QUI\Exception) {
        }
    }

    /**
     * @param string $picture
     */
    public static function onMediaCreateImageHtml(string &$picture): void
    {
        if (Handler::init()->useWebP() === false) {
            return;
        }

        if (stripos($picture, '<picture') !== false) {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $picture, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $pictures = $dom->getElementsByTagName('picture');

            foreach ($pictures as $pic) {
                $sources = [];

                foreach ($pic->childNodes as $node) {
                    if ($node instanceof DOMElement && $node->tagName === 'source') {
                        $sources[] = $node;
                    }
                }

                foreach ($sources as $source) {
                    $srcset = $source->getAttribute('srcset');

                    if (preg_match('/\.(jpe?g|png)(\?.*)?$/i', $srcset)) {
                        $webpSrcset = preg_replace('/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', $srcset);

                        try {
                            $webpSource = $dom->createElement('source');
                        } catch (\Exception) {
                            continue;
                        }

                        $webpSource->setAttribute('srcset', (string)$webpSrcset);
                        $webpSource->setAttribute('type', 'image/webp');

                        foreach ($source->attributes as $attr) {
                            if ($attr->name !== 'srcset' && $attr->name !== 'type') {
                                $webpSource->setAttribute($attr->name, $attr->value);
                            }
                        }

                        $pic->insertBefore($webpSource, $source);
                    }
                }

                // <img> im <picture> ebenfalls als <source> ergänzen
                foreach ($pic->childNodes as $node) {
                    if ($node instanceof DOMElement && $node->tagName === 'img') {
                        $imgSrc = $node->getAttribute('src');
                        $imgSrcset = $node->getAttribute('srcset');

                        if (
                            preg_match('/\.(jpe?g|png)(\?.*)?$/i', $imgSrc) ||
                            preg_match('/\.(jpe?g|png)(\?.*)?$/i', $imgSrcset)
                        ) {
                            try {
                                $webpSource = $dom->createElement('source');
                            } catch (\Exception) {
                                continue;
                            }

                            // srcset bevorzugen, sonst src
                            if (!empty($imgSrcset)) {
                                $webpSrcset = str_ireplace(['.png', '.jpg', '.jpeg'], '.webp', $imgSrcset);
                                $webpSource->setAttribute('srcset', $webpSrcset);
                            } elseif (!empty($imgSrc)) {
                                $webpSrc = preg_replace('/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', $imgSrc);
                                $webpSource->setAttribute('srcset', (string)$webpSrc);
                            }

                            $webpSource->setAttribute('type', 'image/webp');

                            if ($node->hasAttribute('sizes')) {
                                $webpSource->setAttribute('sizes', $node->getAttribute('sizes'));
                            }

                            if ($node->hasAttribute('media')) {
                                $webpSource->setAttribute('media', $node->getAttribute('media'));
                            }

                            $pic->insertBefore($webpSource, $node);
                        }
                    }
                }
            }

            $result = $dom->saveHTML();
            $result = preg_replace('/^<\?xml.*?\?>/', '', (string)$result);
            $picture = trim((string)$result);
        }
    }

    /**
     * event: on quiqqer translator publish
     */
    public static function quiqqerTranslatorPublish(): void
    {
        self::clearCache();
    }

    /**
     * Don't use webp for mails
     *
     * @return void
     */
    public static function onMailerSendInit(): void
    {
        // this solution is not optimal
        // if a mail is sent during the normal running system, the webp is off for everyone (smarty too)
        if (!defined('QUIQQER_CACHE_DISABLE_WEBP')) {
            define('QUIQQER_CACHE_DISABLE_WEBP', true);
        }
    }

    /**
     * @return void
     */
    public static function onQuiqqerMenuIndependentClear(): void
    {
        self::clearCache();
    }

    public static function onUserLogin(User $User): void
    {
        Handler::setLoggedInCookieIfEnabled();
    }

    public static function onQuiqqerFrontendUsersUserAutoLogin(
        User $User,
        mixed $Registrar
    ): void {
        Handler::setLoggedInCookieIfEnabled();
    }

    public static function onUserLogout(User $User): void
    {
        Handler::removeLoggedInCookie();
    }

    public static function onUpdateEnd(): void
    {
        QUI\Cache\Handler::init()->clearCache();
    }
}
