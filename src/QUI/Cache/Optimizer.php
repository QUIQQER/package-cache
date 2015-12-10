<?php

/**
 * This file contains QUI\Cache\Optimizer
 */
namespace QUI\Cache;

use QUI;

/**
 * Class Optimizer
 *
 * @package QUI\Cache
 */
class Optimizer
{
    /**
     * Optimize and bundle a require js request
     *
     * @param array $needles
     * @param array $requireConf
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function optimizeAMD(array $needles, array $requireConf)
    {
        $rJsFile   = OPT_DIR . 'quiqqer/cache/amd/r.js';
        $cachehash = md5(serialize($needles) . serialize($requireConf));
        $cacheName = 'quiqqer/cache/' . $cachehash;

        try {
            return QUI\Cache\Manager::get($cacheName);

        } catch (QUI\Exception $Exception) {
        }

        // config params
        $CacheHandler = QUI\Cache\Handler::init();
        $amdDir       = $CacheHandler->getCacheDir() . 'amd/';
        $amdUrlDir    = $CacheHandler->getURLCacheDir() . 'amd/';
        $buildFile    = $amdDir . $cachehash . '-build.js';

        if (file_exists($buildFile)) {
            return file_get_contents($buildFile);
        }


        $requireBuildConfig = $amdDir . $cachehash . '-build-require-config.js';
        $moduleBuildConfig  = $amdDir . $cachehash . '-build-config.js';
        $moduleCreation     = $amdDir . $cachehash . '.js';

        if (isset($requireConf['pkgs'])) {
            unset($requireConf['pkgs']);
        }

        if (isset($requireConf['packages'])) {
            unset($requireConf['packages']);
        }

        // set relativ paths to absolute
        $requireConf['baseUrl']           = CMS_DIR;
        $requireConf['paths'][$cachehash] = $amdUrlDir . $cachehash;

        // all paths relative
        foreach ($requireConf['paths'] as $entry => $path) {
            $requireConf['paths'][$entry] = ltrim($path, '/');
        }

        // require plugins
        copy(OPT_DIR . 'quiqqer/cache/amd/css.js', $amdDir . 'css-builder.js');
        copy(OPT_DIR . 'quiqqer/cache/amd/image.js', $amdDir . 'image.js');
        copy(OPT_DIR . 'quiqqer/cache/amd/text.js', $amdDir . 'text.js');

        $requireConf['map']["*"]["css"]   = ltrim("{$amdUrlDir}css-builder", '/');
        $requireConf['map']["*"]["image"] = ltrim("{$amdUrlDir}image", '/');
        $requireConf['map']["*"]["text"]  = ltrim("{$amdUrlDir}text", '/');


        // set main paths
        $requireConf['paths']["locale"]        = ltrim(URL_VAR_DIR . "locale/bin", '/');
        $requireConf['paths']["qui"]           = ltrim(URL_OPT_DIR . "quiqqer/qui/qui", '/');
        $requireConf['paths']["classes"]       = ltrim(URL_OPT_DIR . "quiqqer/quiqqer/bin/QUI/classes", '/');
        $requireConf['paths']["controls"]      = ltrim(URL_OPT_DIR . "quiqqer/quiqqer/bin/QUI/controls", '/');
        $requireConf['paths']["utils"]         = ltrim(URL_OPT_DIR . "quiqqer/quiqqer/bin/QUI/utils", '/');
        $requireConf['paths']["polyfills"]     = ltrim(URL_OPT_DIR . "quiqqer/quiqqer/bin/QUI/polyfills", '/');
        $requireConf['paths']["Controls"]      = ltrim(URL_OPT_DIR . "quiqqer/quiqqer/bin/Controls", '/');
        $requireConf['paths']["Ajax"]          = ltrim(URL_OPT_DIR . "quiqqer/quiqqer/bin/QUI/Ajax", '/');
        $requireConf['paths']["Locale"]        = ltrim(URL_OPT_DIR . "quiqqer/quiqqer/bin/QUI/Locale", '/');
        $requireConf['paths']["UploadManager"] = ltrim(URL_OPT_DIR . "quiqqer/quiqqer/bin/QUI/UploadManager", '/');


        // create config files
        QUI\Utils\System\File::mkdir($amdDir);

        file_put_contents(
            $requireBuildConfig,
            'requirejs.config(' . json_encode($requireConf, \JSON_PRETTY_PRINT) . ');'
        );

        file_put_contents(
            $moduleCreation,
            'define("' . $cachehash . '", ' . json_encode($needles, \JSON_PRETTY_PRINT) . ');'
        );

        file_put_contents(
            $moduleBuildConfig,
            "({
                name: '{$cachehash}',
                out: '{$cachehash}-build.js',
                mainConfigFile: '{$cachehash}-build-require-config.js',
                optimizeCss: 'none',
                keepAmdefine: true,
                preserveLicenseComments: false
            })"
        );


        // compile
        $command     = 'nodejs';
        $nodejsCheck = shell_exec("which nodejs");

        if (empty($nodejsCheck)) {
            $command     = 'node';
            $nodejsCheck = shell_exec("which node");
        }

        if (empty($nodejsCheck)) {
            throw new QUI\Exception('nodejs is not installed or is not callable');
        }

        $exec   = "{$command} {$rJsFile} -o '{$moduleBuildConfig}' mainConfigFile='{$requireBuildConfig}'";
        $result = shell_exec($exec);

        // optimize
        $optimized = self::optimizeJavaScript($buildFile);

        QUI\System\Log::writeRecursive($optimized);


        if (file_exists($buildFile)) {
            return file_get_contents($buildFile);
        }

        QUI\System\Log::addWarning($result);

        throw new QUI\Exception('Could not create build');
    }

    /**
     * Optimize the content of a css file
     *
     * @param string $cssfile - css file
     * @return string
     * @throws QUI\Exception
     */
    public static function optimizeCSS($cssfile)
    {
        $cssfilePath = CMS_DIR . $cssfile;

        if (!file_exists($cssfilePath)) {
            $parse       = parse_url($cssfilePath);
            $cssfilePath = $parse['path'];

            if (!file_exists($cssfilePath)) {
                // URL BIN DIR, we must use the real QUIQQER BIN DIR
                if (strpos($cssfile, URL_BIN_DIR) === 0) {
                    $cssfilePath = OPT_DIR .'quiqqer/quiqqer'. $cssfile;

                    if (!file_exists($cssfilePath)) {
                        $parse       = parse_url($cssfilePath);
                        $cssfilePath = $parse['path'];

                        if (!file_exists($cssfilePath)) {
                            throw new QUI\Exception('File not found', 404);
                        }
                    }

                } else {
                    throw new QUI\Exception('File not found', 404);
                }
            }
        }

        $CSSMinify  = new \Minify_CSS();
        $cssContent = file_get_contents($cssfilePath);

        $minified = $CSSMinify->minify($cssContent, array(
            'docRoot'    => CMS_DIR,
            'currentDir' => dirname($cssfilePath) . '/'
        ));

        return $minified;
    }

    /**
     * Optimize the content of a JavaScript file
     *
     * @param string $jsfile - JavaScript file
     * @return string
     * @throws QUI\Exception
     */
    public static function optimizeJavaScript($jsfile)
    {
        $jsfilePath = $jsfile;

        if (!file_exists($jsfilePath)) {
            $parse      = parse_url($jsfilePath);
            $jsfilePath = $parse['path'];

            if (!file_exists($jsfilePath)) {
                throw new QUI\Exception('File not found', 404);
            }
        }

        $command     = 'uglifyjs';
        $uglifyjsCheck = shell_exec("which uglifyjs");

        if (empty($uglifyjsCheck)) {
            throw new QUI\Exception('uglifyjs is not installed or is not callable');
        }

        $exec   = "{$command} {$jsfilePath} --screw-ie8 --compress --mangle";
        $result = shell_exec($exec);

        return $result;
    }

    /**
     * @param string $file
     *
     * @throws QUI\Exception
     */
    public static function optimizePNG($file)
    {
        $optipng = shell_exec("which optipng");

        if (empty($optipng)) {
            throw new QUI\Exception('optipng is not installed');
        }

        if (!file_exists($file)) {
            throw new QUI\Exception('File not exists', 404);
        }

        shell_exec('optipng "' . $file . '"');
    }

    /**
     * @param string $file
     *
     * @throws QUI\Exception
     */
    public static function optimizeJPG($file)
    {
        $jpegoptim = shell_exec("which jpegoptim");
        $quality   = 70;

        if (empty($jpegoptim)) {
            throw new QUI\Exception('jpegoptim is not installed');
        }

        if (!file_exists($file)) {
            throw new QUI\Exception('File not exists', 404);
        }

        shell_exec('jpegoptim -m' . $quality . ' -o --strip-all "' . $file . '"');
    }

    /**
     * Return config build params
     *
     * @return array
     */
    protected static function _getbuildParams()
    {
        return array(
            'appDir'                 => ".",
            'baseUrl'                => ".",
            'dir'                    => "./bin",
            'useStrict'              => true,
            'mainConfigFile'         => "build-config.js",
            'keepBuildDir'           => false,
            'optimizeCss'            => 'standard',
            'wrapShim'               => false,
            "findNestedDependencies" => true,
            "normalizeDirDefines"    => true,
            'fileExclusionRegExp'    => '/\.git|^tests$|^build$|^coverage$|^doc$|^jsdoc$|^examples$|^r\.js|\.md|^package\.json|^composer\.json|^bower\.json|^init\.js|^initDev\.js|^\.jshintrc|^\.flowconfig|^build\.js|^build-jsdoc\.js|^build\-config\.js/',
            'modules'                => array(),
            'paths'                  => array(
                'qui' => 'quiqqer/qui/qui'
            )
        );
    }
}
