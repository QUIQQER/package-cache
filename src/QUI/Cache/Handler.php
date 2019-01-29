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
        return VAR_DIR.'cache/packages/cache/';
    }

    /**
     * Return the url path to the cache dir
     *
     * @return string
     */
    public function getURLCacheDir()
    {
        return URL_VAR_DIR.'cache/packages/cache/';
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
            throw new QUI\Exception('Logged in user. No Cache exists', 404);
        }

        $Request = QUI::getRequest();
        $uri     = $Request->getUri();
        $query   = $Request->getQueryString();

        if (is_string($query)) {
            parse_str($query, $query);
        }

        if (!is_array($query)) {
            $query = [];
        }

        if (isset($query['_url'])) {
            unset($query['_url']);
        }

        if (!empty($query)) {
            throw new QUI\Exception('Get Params exists. No Cache exists', 404);
        }

        $dir       = $this->getCacheDir();
        $cachefile = $dir.md5($uri).QUI\Rewrite::getDefaultSuffix();

        if (file_exists($cachefile) && !is_dir($cachefile)) {
            $cache = file_get_contents($cachefile);

            if (!empty($cache)) {
                return $cache;
            }
        }

        throw new QUI\Exception('No Cache exists', 404);
    }

    /**
     * Generate a cache file from the request
     *
     * @param string $content - content to store
     * @throws QUI\Exception
     */
    public function generateCacheFromRequest($content)
    {
        // loged in users shouldn'tgenerate any cache
        if (QUI::getUsers()->isAuth(QUI::getUserBySession())) {
            return;
        }

        // @todo create cache-id


        $Package            = QUI::getPackage('quiqqer/cache');
        $cacheSetting       = $Package->getConfig()->get('settings', 'cache');
        $jsCacheSetting     = $Package->getConfig()->get('settings', 'jscache');
        $htmlCacheSetting   = $Package->getConfig()->get('settings', 'htmlcache');
        $cssCacheSetting    = $Package->getConfig()->get('settings', 'csscache');
        $lazyLoadingSetting = $Package->getConfig()->get('settings', 'lazyloading');

        if (!$cacheSetting) {
            return;
        }

        $Request = QUI::getRequest();
        $uri     = $Request->getUri();
        $query   = $Request->getQueryString();

        if (is_string($query)) {
            parse_str($query, $query);
        }

        if (!is_array($query)) {
            $query = [];
        }

        if (isset($query['_url'])) {
            unset($query['_url']);
        }

        if (!empty($query)) {
            throw new QUI\Exception('Get Params exists. No Cache exists', 404);
        }

        $cacheId = md5($uri);
        $dir     = $this->getCacheDir();

        $binDir    = $this->getCacheDir().'/bin/';
        $urlBinDir = $this->getURLCacheDir().'/bin/';

        QUI\Utils\System\File::mkdir($dir);
        QUI\Utils\System\File::mkdir($binDir);

        $Minify = new \Minify();
        $Minify->setCache($binDir);
        $Minify->setDocRoot(CMS_DIR);

        /**
         * HTML
         */
        $cacheHtmlFile = $dir.$cacheId.QUI\Rewrite::getDefaultSuffix();
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

            $jsContent = 'var QUIQQER_JS_IS_CACHED = true;';

            // add own cache js
            $matches[] = [
                '<script src="'.URL_OPT_DIR.'quiqqer/cache/bin/Storage.js"></script>',
                '<script src="'.URL_OPT_DIR.'quiqqer/qui/qui/lib/polyfills/Promise.js"></script>'
            ];

            foreach ($matches as $entry) {
                // quiqqer/package-cache/issues/7
                if (strpos($entry[0], 'type=') !== false) {
                    if (strpos($entry[0], 'type="application/javascript"') === false ||
                        strpos($entry[0], 'type="text/javascript"') === false) {
                        continue;
                    }
                }

                if (strpos($entry[0], 'src=') === false) {
                    $content   = str_replace($entry, '', $content);
                    $jsContent .= $entry[1];
                    continue;
                }

                preg_match(
                    '/<script\s+?src="([^"]*)"[^>]*>(.*)<\/script>/Uis',
                    $entry[0],
                    $matches
                );

                if (!isset($matches[1])) {
                    continue;
                }

                $file = CMS_DIR.ltrim($matches[1], '/');

                if (!file_exists($file)) {
                    $parse = parse_url($file);
                    $file  = $parse['path'];
                }

                if (!file_exists($file)) {
                    continue;
                }

                $jsContent .= file_get_contents($file).';';

                $content = str_replace($entry[0], '', $content);
            }


            // js id
            $jsId = md5($jsContent);

            $cacheJSFile    = $binDir.$jsId.'.cache.js';
            $cacheURLJSFile = $urlBinDir.$jsId.'.cache.js';

            // create javascript cache file
            if (!file_exists($cacheJSFile)) {
                file_put_contents($cacheJSFile, $jsContent);

                try {
                    $optimized = Optimizer::optimizeJavaScript($cacheJSFile);

                    if (!empty($optimized)) {
                        file_put_contents($cacheJSFile, $optimized);
                    }
                } catch (QUI\Exception $Exception) {
                    // could not optimize javascript
                }
            }

            $cached = '<script src="'.URL_OPT_DIR.'bin/dexie/dist/dexie.min.js" type="text/javascript"></script>'.
                      '<script src="'.$cacheURLJSFile.'" type="text/javascript"></script>'.
                      '</body>';

            // insert quiqqer.cache.js
            $content = str_replace('</body>', $cached, $content);

            file_put_contents($cacheHtmlFile, $content);


            /**
             * Bundle require modules
             */
            $requirePackages = [];
            $jsContent       = '';

            preg_replace_callback(
                '/data-qui="([^"]*)"/Uis',
                function ($found) use (&$requirePackages) {
                    $requirePackages[] = $found[1];

                    return $found[0];
                },
                $content
            );

            $requirePackages = array_unique($requirePackages);
            sort($requirePackages);

            foreach ($requirePackages as $require) {
                $found = strpos($require, 'package/');

                if ($found === false) {
                    continue;
                }

                if ($found !== 0) {
                    continue;
                }

                $file = trim(substr_replace($require, OPT_DIR, 0, strlen('package/')));

                if (!file_exists($file)) {
                    $file = $file.'.js';
                }

                if (!file_exists($file)) {
                    continue;
                }

                $jsContent .= file_get_contents($file).';';
            }

            $jsId           = md5(serialize($requirePackages));
            $cacheJSFile    = $binDir.$jsId.'.cache.pkg.js';
            $cacheURLJSFile = $urlBinDir.$jsId.'.cache.pkg.js';

            if (!file_exists($cacheJSFile)) {
                file_put_contents($cacheJSFile, $jsContent);

                try {
                    $optimized = Optimizer::optimizeJavaScript($cacheJSFile);

                    if (!empty($optimized)) {
                        file_put_contents($cacheJSFile, $optimized);
                    }
                } catch (QUI\Exception $Exception) {
                }
            }

            $content = str_replace(
                '</body>',
                '<script async src="'.$cacheURLJSFile.'" type="text/javascript"></script></body>',
                $content
            );

            file_put_contents($cacheHtmlFile, $content);
        }

        /**
         * lazy loading
         */

        if ($lazyLoadingSetting) {
            $content = Parser\LazyLoading::getInstance()->parse($content);
            file_put_contents($cacheHtmlFile, $content);
        }

        /**
         * Bundle CSS
         */
        if ($cssCacheSetting) {
            $CSSMinify = new \Minify_CSS();

            preg_match_all(
                '/<link[^>]+href="([^"]*)"[^>]*>/Uis',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            $cssId = md5(serialize($matches));

            $cacheCSSFile    = $binDir.$cssId.'.cache.css';
            $cacheURLCSSFile = $urlBinDir.$cssId.'.cache.css';

            $cssContent = '';

            foreach ($matches as $match) {
                if (strpos($match[0], 'rel') !== false
                    && strpos($match[0], 'rel="stylesheet"') === false
                ) {
                    continue;
                }

                if (strpos($match[0], 'alternate') !== false) {
                    continue;
                }

                if (strpos($match[0], 'next') !== false) {
                    continue;
                }

                if (strpos($match[0], 'prev') !== false) {
                    continue;
                }


                $file = CMS_DIR.$match[1];

                if (!file_exists($file)) {
                    $parse = parse_url($file);
                    $file  = $parse['path'];
                }

                if (!file_exists($file)) {
                    continue;
                }

                $comment  = "\n/* File: {$match[1]} */\n";
                $minified = $CSSMinify->minify(file_get_contents($file), [
                    'docRoot'    => CMS_DIR,
                    'currentDir' => dirname(CMS_DIR.$match[1]).'/'
                ]);

                $cssContent .= $comment.$minified."\n";

                // delete css from main content
                $content = str_replace($match[0], '', $content);
            }

            // create css cache file
            if (!file_exists($cacheCSSFile)) {
                file_put_contents($cacheCSSFile, $cssContent);
            }


            // insert css cache file to the head
//            $content = str_replace(
//                '<!-- quiqqer css -->',
//                '<link href="' . $cacheURLCSSFile . '" rel="stylesheet" type="text/css" />',
//                $content
//            );

            $content = str_replace(
                '<!-- quiqqer css -->',
                '<style>'.$cssContent.'</style>',
                $content
            );

            file_put_contents($cacheHtmlFile, $content);
        }

        /**
         * HTML optimize
         */
        if ($htmlCacheSetting) {
            $sources = [
                new \Minify_Source([
                    'id'            => $cacheId,
                    'content'       => $content,
                    'contentType'   => 'text/html',
                    'minifyOptions' => [
                        'cssMinifier' => ['Minify_CSS', 'minify'],
                        'jsMinifier'  => ['JSMin', 'minify']
                    ]
                ])
            ];

            $result = $Minify->combine($sources, [
                'content'   => $content,
                'id'        => $cacheId,
                'minifyAll' => true
            ]);

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
