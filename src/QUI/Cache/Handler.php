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
     * @var null|bool
     */
    protected $webP = null;

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
     * @return bool
     */
    public function useWebP()
    {
        if ($this->webP !== null) {
            return $this->webP;
        }

        try {
            $Package    = QUI::getPackage('quiqqer/cache');
            $this->webP = $Package->getConfig()->get('settings', 'webp');
            $this->webP = !!$this->webP;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }

        return $this->webP;
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

        if (\is_string($query)) {
            \parse_str($query, $query);
        }

        if (!\is_array($query)) {
            $query = [];
        }

        if (isset($query['_url'])) {
            unset($query['_url']);
        }

        if (!empty($query)) {
            throw new QUI\Exception('Get Params exists. No Cache exists', 404);
        }

        $dir       = $this->getCacheDir();
        $cacheFile = $dir.\md5($uri).QUI\Rewrite::getDefaultSuffix();

        if (\file_exists($cacheFile) && !\is_dir($cacheFile)) {
            $cache = \file_get_contents($cacheFile);

            if (empty($cache)) {
                throw new QUI\Exception('No Cache exists', 404);
            }

            // replace user data
            $cache = \preg_replace_callback(
                '/<script id="quiqqer-user-defined">(.*)<\/script>/Uis',
                function () {
                    $Nobody      = QUI::getUsers()->getNobody();
                    $Country     = $Nobody->getCountry();
                    $countryCode = '';

                    if ($Country) {
                        $countryCode = $Country->getCode();
                    }

                    $user = [
                        'id'      => 0,
                        'name'    => $Nobody->getName(),
                        'lang'    => $Nobody->getLang(),
                        'country' => $countryCode
                    ];

                    return '<script id="quiqqer-user-defined">var QUIQQER_USER= '.\json_encode($user).';</script>';
                },
                $cache
            );

            return $cache;
        }

        throw new QUI\Exception('No Cache exists', 404);
    }

    /**
     * Generate a cache file from the request
     *
     * @param string $content - content to store
     * @throws QUI\Exception
     */
    public function generateCacheFromRequest(&$content)
    {
        // logged in users shouldn'tgenerate any cache
        if (QUI::getUsers()->isAuth(QUI::getUserBySession())) {
            return;
        }

        // @todo create cache-id
        // cache-id = group ids hash


        $Package            = QUI::getPackage('quiqqer/cache');
        $cacheSetting       = $Package->getConfig()->get('settings', 'cache');
        $jsCacheSetting     = $Package->getConfig()->get('settings', 'jscache');
        $htmlCacheSetting   = $Package->getConfig()->get('settings', 'htmlcache');
        $lazyLoadingSetting = $Package->getConfig()->get('settings', 'lazyloading');

        if (!$cacheSetting) {
            return;
        }

        $Request = QUI::getRequest();
        $uri     = $Request->getUri();
        $query   = $Request->getQueryString();

        if (\is_string($query)) {
            \parse_str($query, $query);
        }

        if (!\is_array($query)) {
            $query = [];
        }

        if (isset($query['_url'])) {
            unset($query['_url']);
        }

        if (!empty($query)) {
            throw new QUI\Exception('Get Params exists. No Cache exists', 404);
        }

        $cacheId   = \md5($uri);
        $dir       = $this->getCacheDir();
        $binDir    = $this->getCacheDir().'bin/';
        $urlBinDir = $this->getURLCacheDir().'bin/';

        QUI\Utils\System\File::mkdir($dir);
        QUI\Utils\System\File::mkdir($binDir);

        $Minify = new \Minify();
        $Minify->setCache($binDir);
        $Minify->setDocRoot(CMS_DIR);

        /**
         * HTML
         */
        $cacheHtmlFile = $dir.$cacheId.QUI\Rewrite::getDefaultSuffix();
        \file_put_contents($cacheHtmlFile, $content);


        /**
         * Bundle JavaScript
         */
        if ($jsCacheSetting) {
            \preg_match_all(
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
                if (\strpos($entry[0], 'id="quiqqer-user-defined"') !== false) {
                    continue;
                }

                // quiqqer/package-cache/issues/7
                if (\strpos($entry[0], 'type=') !== false) {
                    if (\strpos($entry[0], 'type="application/javascript"') === false ||
                        \strpos($entry[0], 'type="text/javascript"') === false
                    ) {
                        continue;
                    }
                }

                if (\strpos($entry[0], 'src=') === false) {
                    $content   = \str_replace($entry, '', $content);
                    $jsContent .= $entry[1];
                    continue;
                }

                \preg_match(
                    '/<script\s+?src="([^"]*)"[^>]*>(.*)<\/script>/Uis',
                    $entry[0],
                    $matches
                );

                if (!isset($matches[1])) {
                    continue;
                }

                $file = CMS_DIR.\ltrim($matches[1], '/');

                if (!\file_exists($file)) {
                    $parse = \parse_url($file);
                    $file  = $parse['path'];
                }

                if (!\file_exists($file)) {
                    continue;
                }

                $jsContent .= \file_get_contents($file).';';

                $content = \str_replace($entry[0], '', $content);
            }


            // js id
            $jsId = \md5($jsContent);

            $cacheJSFile    = $binDir.$jsId.'.cache.js';
            $cacheURLJSFile = $urlBinDir.$jsId.'.cache.js';

            // create javascript cache file
            if (!\file_exists($cacheJSFile)) {
                \file_put_contents($cacheJSFile, $jsContent);

                try {
                    $optimized = Optimizer::optimizeJavaScript($cacheJSFile);

                    if (!empty($optimized)) {
                        \file_put_contents($cacheJSFile, $optimized);
                    }
                } catch (QUI\Exception $Exception) {
                    // could not optimize javascript
                }
            }

            $cached = '<script src="'.URL_OPT_DIR.'bin/dexie/dist/dexie.min.js" type="text/javascript"></script>'.
                      '<script src="'.$cacheURLJSFile.'" type="text/javascript"></script>'.
                      '</body>';

            // insert quiqqer.cache.js
            $content = \str_replace('</body>', $cached, $content);

            \file_put_contents($cacheHtmlFile, $content);


            /**
             * Bundle require modules
             */
            $requirePackages = [];
            $jsContent       = '';

            \preg_replace_callback(
                '/data-qui="([^"]*)"/Uis',
                function ($found) use (&$requirePackages) {
                    $requirePackages[] = $found[1];

                    return $found[0];
                },
                $content
            );

            $requirePackages = \array_unique($requirePackages);
            \sort($requirePackages);

            foreach ($requirePackages as $require) {
                $found = \strpos($require, 'package/');

                if ($found === false) {
                    continue;
                }

                if ($found !== 0) {
                    continue;
                }

                $file = \trim(\substr_replace($require, OPT_DIR, 0, \strlen('package/')));

                if (!\file_exists($file)) {
                    $file = $file.'.js';
                }

                if (!\file_exists($file)) {
                    continue;
                }

                $jsContent .= \file_get_contents($file).';';
            }

            $jsId           = \md5(\serialize($requirePackages));
            $cacheJSFile    = $binDir.$jsId.'.cache.pkg.js';
            $cacheURLJSFile = $urlBinDir.$jsId.'.cache.pkg.js';

            if (!\file_exists($cacheJSFile)) {
                \file_put_contents($cacheJSFile, $jsContent);

                try {
                    $optimized = Optimizer::optimizeJavaScript($cacheJSFile);

                    if (!empty($optimized)) {
                        \file_put_contents($cacheJSFile, $optimized);
                    }
                } catch (QUI\Exception $Exception) {
                }
            }

            $content = \str_replace(
                '</body>',
                '<script async src="'.$cacheURLJSFile.'" type="text/javascript"></script></body>',
                $content
            );

            \file_put_contents($cacheHtmlFile, $content);
        }

        /**
         * lazy loading during the cache
         */

        if ($lazyLoadingSetting) {
            $content = Parser\LazyLoading::getInstance()->parse($content);
            \file_put_contents($cacheHtmlFile, $content);
        }

        /**
         * Bundle CSS
         */
        $content = $this->generateCSSCache($content);
        \file_put_contents($cacheHtmlFile, $content);

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
                        'jsMinifier' => ['JSMin', 'minify']
                    ]
                ])
            ];

            $result = $Minify->combine($sources, [
                'content'   => $content,
                'id'        => $cacheId,
                'minifyAll' => true
            ]);

            \file_put_contents($cacheHtmlFile, $result);

            $content = $result;
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

    /**
     * @param $content
     * @return string|string[]
     * @throws QUI\Exception
     */
    public function generateCSSCache($content)
    {
        $Package    = QUI::getPackage('quiqqer/cache');
        $cssEnabled = $Package->getConfig()->get('css', 'status');
        $cssInline  = $Package->getConfig()->get('css', 'css_inline');

        if (!$cssEnabled || \defined('QUIQQER_CACHE_NO_CSS_CACHE')) {
            return $content;
        }

        $binDir    = $this->getCacheDir().'bin/';
        $urlBinDir = $this->getURLCacheDir().'bin/';

        $templateFiles = [];
        $cssFiles      = [];

        $template     = QUI::getRewrite()->getProject()->getAttribute('template');
        $customCss    = USR_DIR.QUI::getRewrite()->getProject()->getName().'/bin/custom.css';
        $templatePath = OPT_DIR.$template;

        \preg_match_all(
            '/<link[^>]+href="([^"]*)"[^>]*>/Uis',
            $content,
            $matches,
            \PREG_SET_ORDER
        );

        // filter files
        foreach ($matches as $match) {
            if (\strpos($match[0], 'rel') !== false && \strpos($match[0], 'rel="stylesheet"') === false) {
                continue;
            }

            if (\strpos($match[0], 'alternate') !== false) {
                continue;
            }

            if (\strpos($match[0], 'next') !== false) {
                continue;
            }

            if (\strpos($match[0], 'prev') !== false) {
                continue;
            }

            $file = CMS_DIR.$match[1];

            if (!\file_exists($file)) {
                $parse = \parse_url($file);
                $file  = $parse['path'];
            }

            if (!\file_exists($file)) {
                continue;
            }

            $file = QUI\Utils\StringHelper::replaceDblSlashes($file);

            if (\strpos($file, $templatePath) !== false || $file === $customCss) {
                $templateFiles[] = [
                    'file'  => $file,
                    'match' => $match
                ];
            } else {
                $cssFiles[] = [
                    'file'  => $file,
                    'match' => $match
                ];
            }
        }

        $replace = '';

        // generate template files
        if (\count($templateFiles)) {
            $cssContent      = '';
            $cssId           = \md5(\serialize($templateFiles));
            $cacheTplFile    = $binDir.$cssId.'.tpl.cache.css';
            $cacheUrlTplFile = $urlBinDir.$cssId.'.tpl.cache.css';

            foreach ($templateFiles as $fileData) {
                $file    = $fileData['file'];
                $match   = $fileData['match'];
                $cssFile = $binDir.\md5($file).'.css';

                if ($cssInline === 'inline') {
                    $cssFile = CMS_DIR.\md5($file).'.css';
                }

                $CSSMinify = new \MatthiasMullie\Minify\CSS();
                $CSSMinify->add($file);
                $CSSMinify->minify($cssFile);

                $comment    = "\n/* File: {$match[1]} */\n";
                $cssContent .= $comment.file_get_contents($cssFile)."\n";

                unlink($cssFile);

                $content = \str_replace($match[0], '', $content);
            }

            \file_put_contents($cacheTplFile, $cssContent);

            $replace .= '<link href="'.$cacheUrlTplFile.'" rel="stylesheet" type="text/css" />';
        }

        // generate sediment css files
        if (\count($cssFiles)) {
            $cssContent      = '';
            $cssId           = \md5(\serialize($cssFiles));
            $cacheSedFile    = $binDir.$cssId.'.sed.cache.css';
            $cacheUrlSedFile = $urlBinDir.$cssId.'.sed.cache.css';

            foreach ($cssFiles as $fileData) {
                $file    = $fileData['file'];
                $match   = $fileData['match'];
                $cssFile = $binDir.\md5($file).'.css';

                if ($cssInline === 'inline') {
                    $cssFile = CMS_DIR.\md5($file).'.css';
                }

                $CSSMinify = new \MatthiasMullie\Minify\CSS();
                $CSSMinify->add($file);
                $CSSMinify->minify($cssFile);

                $comment    = "\n/* File: {$match[1]} */\n";
                $cssContent .= $comment.file_get_contents($cssFile)."\n";

                unlink($cssFile);

                // delete css from main content
                $content = \str_replace($match[0], '', $content);
            }

            \file_put_contents($cacheSedFile, $cssContent);

            $replace .= '<link href="'.$cacheUrlSedFile.'" rel="stylesheet" type="text/css" />';
        }

        // inline css
        \preg_match_all(
            '#<style>(.*)</style>#Uis',
            $content,
            $matches,
            \PREG_SET_ORDER
        );

        if ($cssInline === 'inline_as_file') {
            $inlineCSS = '';

            $cssId              = \md5(\serialize($matches));
            $cacheInlineFile    = $binDir.$cssId.'.inline.cache.css';
            $cacheUrlInlineFile = $urlBinDir.$cssId.'.inline.cache.css';

            foreach ($matches as $match) {
                $inlineCSS .= $match[1];
                $content   = \str_replace($match[0], '', $content);
            }

            $CSSMinify = new \MatthiasMullie\Minify\CSS();
            $CSSMinify->add($inlineCSS);

            \file_put_contents($cacheInlineFile, $CSSMinify->minify());

            $replace .= '<link href="'.$cacheUrlInlineFile.'" rel="stylesheet" type="text/css" />';
        } else {
            foreach ($matches as $match) {
                $CSSMinify = new \MatthiasMullie\Minify\CSS();
                $CSSMinify->add($match[1]);

                $content = \str_replace(
                    $match[0],
                    '<style>'.$CSSMinify->minify().'</style>',
                    $content
                );
            }
        }

        // prepare output
        if ($cssInline === 'inline') {
            // insert as inline
            $cssContent = '';

            if (isset($cacheTplFile)) {
                $cssContent .= \file_get_contents($cacheTplFile);
            }

            if (isset($cacheSedFile)) {
                $cssContent .= \file_get_contents($cacheSedFile);
            }

            if (!empty($cssContent)) {
                $content = \str_replace(
                    '<!-- quiqqer css -->',
                    '<style>'.$cssContent.'</style>',
                    $content
                );
            }
        } else {
            // insert as file
            $content = str_replace(
                '<!-- quiqqer css -->',
                $replace,
                $content
            );
        }

        return $content;
    }
}
