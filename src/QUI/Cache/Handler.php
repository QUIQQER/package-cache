<?php

/**
 * This file contains the \QUI\Cache\Handler
 */

namespace QUI\Cache;

use QUI;

/**
 * Class Handler
 * Handles the request and the caching system
 *
 * @package QUI\Cache
 */
class Handler
{
    /**
     * @return Handler
     */
    public static function init()
    {
        return new self();
    }

    /**
     * Return the path to the cache dir
     *
     * @return string
     */
    public function getCacheDir()
    {
        return VAR_DIR . 'cache/packages/cache/';
    }

    /**
     * Return the url path to the cache dir
     *
     * @return string
     */
    public function getURLCacheDir()
    {
        return URL_VAR_DIR . 'cache/packages/cache/';
    }

    /**
     * Get Cache from request
     *
     * @return string
     * @throws QUI\Exception
     */
    public function getCacheFromRequest()
    {
        // loged in users get no cache
        if (QUI::getUsers()->isAuth(QUI::getUserBySession())) {
            throw new QUI\Exception('Loged in user. No Cache exists', 404);
        }

        $Request = QUI::getRequest();
        $uri     = $Request->getUri();
        $query   = $Request->getQueryString();

        if (!is_null($query)) {
            throw new QUI\Exception('Get Params exists. No Cache exists', 404);
        }

        $dir       = $this->getCacheDir();
        $cachefile = $dir . md5($uri) . '.html';

        if (file_exists($cachefile) && !is_dir($cachefile)) {
            return file_get_contents($cachefile);
        }

        throw new QUI\Exception('No Cache exists', 404);
    }

    /**
     * Generate a cache file from the request
     *
     * @param string $content - content to store
     * @throws QUI\Exception
     */
    public function generatCacheFromRequest($content)
    {
        // loged in users shouldn'tgenerate any cache
        if (QUI::getUsers()->isAuth(QUI::getUserBySession())) {
            return;
        }

        $Package          = QUI::getPackage('quiqqer/cache');
        $cacheSetting     = $Package->getConfig()->get('settings', 'cache');
        $jsCacheSetting   = $Package->getConfig()->get('settings', 'jscache');
        $htmlCacheSetting = $Package->getConfig()->get('settings', 'htmlcache');
        $cssCacheSetting  = $Package->getConfig()->get('settings', 'csscache');

        if (!$cacheSetting) {
            return;
        }

        $Request = QUI::getRequest();
        $uri     = $Request->getUri();
        $query   = $Request->getQueryString();

        if (!is_null($query)) {
            throw new QUI\Exception('Get Params exists. No Cache exists', 404);
        }

        $cacheId = md5($uri);
        $dir     = $this->getCacheDir();

        $binDir    = $this->getCacheDir() . '/bin/';
        $urlBinDir = $this->getURLCacheDir() . '/bin/';

        QUI\Utils\System\File::mkdir($dir);
        QUI\Utils\System\File::mkdir($binDir);

        $Minify = new \Minify();
        $Minify->setCache($binDir);
        $Minify->setDocRoot(CMS_DIR);

        /**
         * HTML
         */
        $cacheHtmlFile = $dir . $cacheId . '.html';
        file_put_contents($cacheHtmlFile, $content);


        /**
         * Bundle JavaScript
         */

        if ($jsCacheSetting) {

            preg_match_all(
                '/<script[^>]*>(.*)<\/script>/Uis',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            $jsContent = '';
            $jsId      = md5(serialize($matches));

            $cacheJSFile    = $binDir . $jsId . '.cache.js';
            $cacheURLJSFile = $urlBinDir . $jsId . '.cache.js';

            foreach ($matches as $entry) {

                if (strpos($entry[0], 'src=') === false) {

                    $content = str_replace($entry, '', $content);
                    $jsContent .= $entry[1];

                    continue;
                }

                preg_match(
                    '/<script\s+?src="([^"]*)"[^>]*>(.*)<\/script>/Uis',
                    $entry[0],
                    $matches
                );

                $file = CMS_DIR . ltrim($matches[1], '/');

                if (!file_exists($file)) {
                    $parse = parse_url($file);
                    $file  = $parse['path'];
                }

                if (!file_exists($file)) {
                    continue;
                }

                $jsContent .= file_get_contents($file) . ';';

                $content = str_replace($entry[0], '', $content);
            }

            // create javascript cache file
            file_put_contents($cacheJSFile, $jsContent);

            try
            {
                $optimized = Optimizer::optimizeJavaScript($cacheJSFile);

                if (!empty($optimized)) {
                    file_put_contents($cacheJSFile, $optimized);
                }

            } catch (QUI\Exception $Exception) {
                // could not optimize javascript
            }


            // insert quiqqer.cache.js
            $content = str_replace(
                '</body>',
                '<script src="' . $cacheURLJSFile . '" type="text/javascript"></script></body>',
                $content
            );

            file_put_contents($cacheHtmlFile, $content);
        }


        /**
         * Bundle CSS
         */
        if ($cssCacheSetting) {

            $CSSMinify = new \Minify_CSS();

            preg_match_all(
                '/<link\s+?href="([^"]*)"[^>]*>/Uis',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            $cssId = md5(serialize($matches));

            $cacheCSSFile    = $binDir . $cssId . '.cache.css';
            $cacheURLCSSFile = $urlBinDir . $cssId . '.cache.css';

            $cssContent = '';

            foreach ($matches as $match) {
                if (strpos($match[0], 'alternate') !== false) {
                    continue;
                }

                if (strpos($match[0], 'next') !== false) {
                    continue;
                }

                if (strpos($match[0], 'prev') !== false) {
                    continue;
                }


                $file = CMS_DIR . $match[1];

                if (!file_exists($file)) {
                    $parse = parse_url($file);
                    $file  = $parse['path'];
                }

                if (!file_exists($file)) {
                    continue;
                }

                $comment  = "\n/* File: {$match[1]} */\n";
                $minified = $CSSMinify->minify(file_get_contents($file), array(
                    'docRoot'    => CMS_DIR,
                    'currentDir' => dirname(CMS_DIR . $match[1]) . '/'
                ));

                $cssContent .= $comment . $minified . "\n";

                // delete css from main content
                $content = str_replace($match[0], '', $content);
            }

            // create css cache file
            file_put_contents($cacheCSSFile, $cssContent);


            // insert css cache file to the head
            $content = str_replace(
                '<!-- quiqqer css -->',
                '<link href="' . $cacheURLCSSFile . '" rel="stylesheet" type="text/css" />',
                $content
            );

            file_put_contents($cacheHtmlFile, $content);
        }

        /**
         * HTML optimize
         */
        if ($htmlCacheSetting) {

            $sources = array(
                new \Minify_Source(array(
                    'id'            => $cacheId,
                    'content'       => $content,
                    'contentType'   => 'text/html',
                    'minifyOptions' => array(
                        'cssMinifier' => array('Minify_CSS', 'minify'),
                        'jsMinifier'  => array('JSMin', 'minify')
                    )
                ))
            );

            $result = $Minify->combine($sources, array(
                'content'   => $content,
                'id'        => $cacheId,
                'minifyAll' => true
            ));

            file_put_contents($cacheHtmlFile, $result);
        }
    }

    /**
     * Clear the complete cache
     */
    public function clearCache()
    {
        QUI::getTemp()->moveToTemp($this->getCacheDir());
        QUI\Cache\Manager::clear('quiqqer/cache');
    }
}
