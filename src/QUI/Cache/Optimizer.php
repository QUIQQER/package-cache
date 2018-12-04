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
 * @todo optipng version higher than 0.7.4
 */
class Optimizer
{
    /**
     * Stores/Caches if Jpegoptim is installed.
     * @var bool
     */
    protected static $isJpegoptimInstalled = null;


    /**
     * Stores/Caches if OptiPNG is installed.
     * @var bool
     */
    protected static $isOptiPngInstalled = null;


    /**
     * Stores/Caches if UglifyJS is installed.
     * @var bool
     */
    protected static $isUglifyJsInstalled = null;


    // region Optimization Methods

    /**
     * @param $project
     * @param int $mtime
     */
    public static function optimizeProjectImages($project, $mtime = 2)
    {
        $Console = new Console\Optimize();
        $Console->setArgument('project', $project);
        $Console->setArgument('mtime', (int)$mtime);
        $Console->execute();
    }

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
        $command = 'nodejs';
        exec("command -v {$command}", $output, $returnCode);

        if ($returnCode != 0) {
            $command = 'node';
            exec("command -v {$command}", $output, $returnCode);
        }

        if ($returnCode != 0) {
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
                    $cssfilePath = OPT_DIR . 'quiqqer/quiqqer' . $cssfile;

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

        self::checkUglifyJsInstalled();

        $exec   = "uglifyjs {$jsfilePath} --screw-ie8 --compress --mangle";
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
        if (!self::isOptiPngInstalled()) {
            return;
        }

        if (!file_exists($file)) {
            throw new QUI\Exception('File not exists', 404);
        }

        shell_exec('optipng -strip all "' . $file . '"');
    }

    /**
     * Optimize a given JPG file.
     *
     * @param string $file - The file's absolute path
     *
     * @throws QUI\Exception
     */
    public static function optimizeJPG($file)
    {
        if (!self::isJpegoptimInstalled()) {
            return;
        }

        if (!file_exists($file)) {
            throw new QUI\Exception('File not exists', 404);
        }

        $quality = 70;
        shell_exec('jpegoptim -m' . $quality . ' -o --strip-all "' . $file . '"');
    }
    // endregion


    /**
     * Return config build params
     *
     * @return array
     */
    protected static function getbuildParams()
    {
        $fileExclusionRegExp = '';
        $fileExclusionRegExp .= '/\.git|^tests$|^build$|^coverage$|^doc$|^jsdoc$|^examples$|';
        $fileExclusionRegExp .= '^r\.js|\.md|^package\.json|^composer\.json|^bower\.json|';
        $fileExclusionRegExp .= '^init\.js|^initDev\.js|^\.jshintrc|^\.flowconfig|';
        $fileExclusionRegExp .= '^build\.js|^build-jsdoc\.js|^build\-config\.js/';

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
            'fileExclusionRegExp'    => $fileExclusionRegExp,
            'modules'                => array(),
            'paths'                  => array(
                'qui' => 'quiqqer/qui/qui'
            )
        );
    }

    // region Jpegoptim installation state methods

    /**
     * Checks if Jpegoptim is installed.
     * Throws an exception if it's not installed.
     *
     * @throws QUI\Exception
     */
    public static function checkJpegoptimInstalled()
    {
        if (self::$isJpegoptimInstalled !== null) {
            if (self::$isJpegoptimInstalled === false) {
                throw new QUI\Exception('jpegoptim is not installed');
            }

            return;
        }

        self::$isJpegoptimInstalled = false;

        if (self::isCommandAvailable("jpegoptim")) {
            throw new QUI\Exception('jpegoptim is not installed');
        }

        // Only reached if no exception is thrown above
        self::$isJpegoptimInstalled = true;
    }

    /**
     * Returns if Jpegoptim is installed.
     *
     * @return bool
     */
    public static function isJpegoptimInstalled()
    {
        if (self::$isJpegoptimInstalled !== null) {
            return self::$isJpegoptimInstalled;
        }

        try {
            self::checkJpegoptimInstalled();
        } catch (QUI\Exception $Exception) {
            return false;
        }

        return true;
    }

    // endregion


    // region OptiPNG installation state methods

    /**
     * Checks if OptiPNG is installed.
     * Throws an exception if it's not installed.
     *
     * @throws QUI\Exception
     */
    public static function checkOptiPngInstalled()
    {
        if (self::$isOptiPngInstalled !== null) {
            if (self::$isOptiPngInstalled === false) {
                throw new QUI\Exception('OptiPNG is not installed');
            }

            return;
        }

        self::$isOptiPngInstalled = false;

        if (self::isCommandAvailable("optipng")) {
            throw new QUI\Exception('OptiPNG is not installed');
        }

        // Only reached if no exception is thrown above
        self::$isOptiPngInstalled = true;
    }


    /**
     * Returns if OptiPNG is installed.
     *
     * @return bool
     */
    public static function isOptiPngInstalled()
    {
        if (self::$isOptiPngInstalled !== null) {
            return self::$isOptiPngInstalled;
        }

        try {
            self::checkOptiPngInstalled();
        } catch (QUI\Exception $Exception) {
            return false;
        }

        return true;
    }

    // endregion


    // region UglifyJS installation state methods

    /**
     * Checks if UglifyJS is installed.
     * Throws an exception if it's not installed.
     *
     * @throws QUI\Exception
     */
    public static function checkUglifyJsInstalled()
    {
        if (self::$isUglifyJsInstalled !== null) {
            if (self::$isUglifyJsInstalled === false) {
                throw new QUI\Exception('UglifyJS is not installed');
            }

            return;
        }

        self::$isUglifyJsInstalled = false;

        if (self::isCommandAvailable("uglifyjs")) {
            throw new QUI\Exception('UglifyJS is not installed');
        }

        // Only reached if no exception is thrown above
        self::$isUglifyJsInstalled = true;
    }


    /**
     * Returns if UglifyJS is installed.
     *
     * @return bool
     */
    public static function isUglifyJsInstalled()
    {
        if (self::$isUglifyJsInstalled !== null) {
            return self::$isUglifyJsInstalled;
        }

        try {
            self::checkUglifyJsInstalled();
        } catch (QUI\Exception $Exception) {
            return false;
        }

        return true;
    }

    // endregion


    /**
     * Checks if the given (system-)command is available on the system.
     *
     * @param string $command
     * @return bool
     */
    public static function isCommandAvailable($command)
    {
        exec("command -v {$command}", $output, $returnCode);

        return $returnCode == 0;
    }
}
