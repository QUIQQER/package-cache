<?php

/**
 * This file contains QUI\Cache\Optimizer
 */

namespace QUI\Cache;

use Minify_CSS;
use QUI;

use function copy;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function json_encode;
use function ltrim;
use function md5;
use function parse_url;
use function pathinfo;
use function rename;
use function serialize;
use function shell_exec;
use function strpos;
use function unlink;

use const JSON_PRETTY_PRINT;

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
    protected static ?bool $isJpegoptimInstalled = null;

    /**
     * Stores/Caches if OptiPNG is installed.
     * @var bool
     */
    protected static ?bool $isOptiPngInstalled = null;

    /**
     * Stores/Caches if WebP is installed.
     * @var bool
     */
    protected static ?bool $isWebPInstalled = null;


    /**
     * Stores/Caches if UglifyJS is installed.
     * @var bool
     */
    protected static ?bool $isUglifyJsInstalled = null;

    /**
     * @var bool|null
     */
    protected static ?bool $isUglifyTerserJsInstalled = null;

    // region Optimization Methods

    /**
     * @param $project
     * @param int $mtime
     */
    public static function optimizeProjectImages($project, int $mtime = 2)
    {
        $Console = new Console\Optimize();
        $Console->setArgument('project', $project);
        $Console->setArgument('mtime', $mtime);
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
    public static function optimizeAMD(array $needles, array $requireConf): string
    {
        $rJsFile   = OPT_DIR . 'quiqqer/cache/amd/r.js';
        $cacheHash = md5(serialize($needles) . serialize($requireConf));
        $cacheName = 'quiqqer/cache/' . $cacheHash;

        try {
            return QUI\Cache\Manager::get($cacheName);
        } catch (QUI\Exception $Exception) {
        }

        // config params
        $CacheHandler = QUI\Cache\Handler::init();
        $amdDir       = $CacheHandler->getCacheDir() . 'amd/';
        $amdUrlDir    = $CacheHandler->getURLCacheDir() . 'amd/';
        $buildFile    = $amdDir . $cacheHash . '-build.js';

        if (file_exists($buildFile)) {
            return file_get_contents($buildFile);
        }


        $requireBuildConfig = $amdDir . $cacheHash . '-build-require-config.js';
        $moduleBuildConfig  = $amdDir . $cacheHash . '-build-config.js';
        $moduleCreation     = $amdDir . $cacheHash . '.js';

        if (isset($requireConf['pkgs'])) {
            unset($requireConf['pkgs']);
        }

        if (isset($requireConf['packages'])) {
            unset($requireConf['packages']);
        }

        // set relativ paths to absolute
        $requireConf['baseUrl']           = CMS_DIR;
        $requireConf['paths'][$cacheHash] = $amdUrlDir . $cacheHash;

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
            'requirejs.config(' . json_encode($requireConf, JSON_PRETTY_PRINT) . ');'
        );

        file_put_contents(
            $moduleCreation,
            'define("' . $cacheHash . '", ' . json_encode($needles, JSON_PRETTY_PRINT) . ');'
        );

        file_put_contents(
            $moduleBuildConfig,
            "({
                name: '$cacheHash',
                out: '$cacheHash-build.js',
                mainConfigFile: '$cacheHash-build-require-config.js',
                optimizeCss: 'none',
                keepAmdefine: true,
                preserveLicenseComments: false
            })"
        );


        // compile
        $command = 'nodejs';
        exec("command -v $command", $output, $returnCode);

        if ($returnCode != 0) {
            $command = 'node';
            exec("command -v $command", $output, $returnCode);
        }

        if ($returnCode != 0) {
            throw new QUI\Exception('nodejs is not installed or is not callable');
        }

        $exec   = "$command $rJsFile -o '$moduleBuildConfig' mainConfigFile='$requireBuildConfig'";
        $result = shell_exec($exec);

        // optimize
        self::optimizeJavaScript($buildFile);

        if (file_exists($buildFile)) {
            return file_get_contents($buildFile);
        }

        QUI\System\Log::addWarning($result);

        throw new QUI\Exception('Could not create build');
    }

    /**
     * Optimize the content of a css file
     *
     * @param string $cssFile - css file
     * @return string
     * @throws QUI\Exception
     */
    public static function optimizeCSS(string $cssFile): string
    {
        $cssFilePath = CMS_DIR . $cssFile;

        if (!file_exists($cssFilePath)) {
            $parse       = parse_url($cssFilePath);
            $cssFilePath = $parse['path'];

            if (!file_exists($cssFilePath)) {
                // URL BIN DIR, we must use the real QUIQQER BIN DIR
                if (strpos($cssFile, URL_BIN_DIR) === 0) {
                    $cssFilePath = OPT_DIR . 'quiqqer/quiqqer' . $cssFile;

                    if (!file_exists($cssFilePath)) {
                        $parse       = parse_url($cssFilePath);
                        $cssFilePath = $parse['path'];

                        if (!file_exists($cssFilePath)) {
                            throw new QUI\Exception('File not found', 404);
                        }
                    }
                } else {
                    throw new QUI\Exception('File not found', 404);
                }
            }
        }

        $CSSMinify  = new Minify_CSS();
        $cssContent = file_get_contents($cssFilePath);

        $minified = $CSSMinify->minify($cssContent, [
            'docRoot'    => CMS_DIR,
            'currentDir' => dirname($cssFilePath) . '/'
        ]);

        return $minified;
    }

    /**
     * Optimize the content of a JavaScript file
     *
     * @param string $jsFile - JavaScript file
     * @throws QUI\Exception
     */
    public static function optimizeJavaScript(string $jsFile)
    {
        $jsFilePath = $jsFile;

        if (!file_exists($jsFilePath)) {
            $parse      = parse_url($jsFilePath);
            $jsFilePath = $parse['path'];

            if (!file_exists($jsFilePath)) {
                throw new QUI\Exception('File not found', 404);
            }
        }

        $o = $jsFilePath . '_o';
        $c = self::getUglifyCommand();

        $exec = "$c $jsFilePath --screw-ie8 --compress --mangle > " . $o;
        shell_exec($exec);

        $oc = file_get_contents($o);

        if (!empty($oc)) {
            unlink($jsFilePath);
            rename($o, $jsFilePath);
        }
    }

    /**
     * @param string $file
     *
     * @throws QUI\Exception
     */
    public static function optimizePNG(string $file)
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
    public static function optimizeJPG(string $file)
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
    protected static function getbuildParams(): array
    {
        $fileExclusionRegExp = '/\.git|^tests$|^build$|^coverage$|^doc$|^jsdoc$|^examples$|';
        $fileExclusionRegExp .= '^r\.js|\.md|^package\.json|^composer\.json|^bower\.json|';
        $fileExclusionRegExp .= '^init\.js|^initDev\.js|^\.jshintrc|^\.flowconfig|';
        $fileExclusionRegExp .= '^build\.js|^build-jsdoc\.js|^build\-config\.js/';

        return [
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
            'modules'                => [],
            'paths'                  => [
                'qui' => 'quiqqer/qui/qui'
            ]
        ];
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

        if (!self::isCommandAvailable("jpegoptim")) {
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
    public static function isJpegoptimInstalled(): ?bool
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

        if (!self::isCommandAvailable("optipng")) {
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
    public static function isOptiPngInstalled(): ?bool
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

    // region webP

    /**
     * Checks if OptiPNG is installed.
     * Throws an exception if it's not installed.
     *
     * @throws QUI\Exception
     */
    public static function checkWebPInstalled()
    {
        if (self::$isWebPInstalled !== null) {
            if (self::$isWebPInstalled === false) {
                throw new QUI\Exception('WebP is not installed');
            }

            return;
        }

        self::$isWebPInstalled = false;

        if (!self::webPCommand()) {
            throw new QUI\Exception('WebP is not installed');
        }

        // Only reached if no exception is thrown above
        self::$isWebPInstalled = true;
    }

    /**
     * Returns if webP is installed.
     *
     * @return bool
     */
    public static function isWebPInstalled(): ?bool
    {
        if (self::$isWebPInstalled !== null) {
            return self::$isWebPInstalled;
        }

        try {
            self::checkWebPInstalled();
        } catch (QUI\Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * @return bool|string
     */
    public static function webPCommand()
    {
        if (self::isCommandAvailable("cwebp")) {
            return 'cwebp';
        }

        return false;
    }

    /**
     * Convert a file to a webP file
     *
     * @param string $file
     * @return string|false
     */
    public static function convertToWebP(string $file)
    {
        if (!file_exists($file)) {
            return false;
        }

        $quality = 70;
        $parts   = pathinfo($file);

        if (!isset($parts['extension'])) {
            return false;
        }

        if ($parts['extension'] === 'svg') {
            return false;
        }

        $webPFile = $parts['dirname'] . DIRECTORY_SEPARATOR . $parts['filename'] . '.webp';
        $webP     = self::webPCommand();

        if ($parts['extension'] === 'gif') {
            $webP = 'gif2webp';
        }

        $command = $webP . ' -q ' . escapeshellarg($quality)
            . ' ' . escapeshellarg($file)
            . ' -o ' . escapeshellarg($webPFile);

        shell_exec($command);

        return $webPFile;
    }

    // endregion

    // region UglifyJS installation state methods

    /**
     * @return string
     * @throws QUI\Exception
     */
    public static function getUglifyCommand(): string
    {
        if (self::$isUglifyTerserJsInstalled) {
            return 'uglifyjs.terser';
        }

        if (self::$isUglifyJsInstalled) {
            return 'uglifyjs';
        }

        // check terser first
        try {
            if (self::$isUglifyTerserJsInstalled === null) {
                self::checkUglifyTerserJsInstalled();
                return 'uglifyjs.terser';
            }
        } catch (QUI\Exception $Exception) {
        }

        try {
            if (self::$isUglifyJsInstalled === null) {
                self::checkUglifyJsInstalled();
                return 'uglifyjs';
            }
        } catch (QUI\Exception $Exception) {
        }

        throw new QUI\Exception('Please install uglifyjs or uglifyjs.terser');
    }

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
                throw new QUI\Exception('uglifyjs is not installed');
            }

            return;
        }

        self::$isUglifyJsInstalled = false;

        if (!self::isCommandAvailable("uglifyjs")) {
            throw new QUI\Exception('uglifyjs is not installed');
        }

        // Only reached if no exception is thrown above
        self::$isUglifyJsInstalled = true;
    }

    /**
     * Checks if UglifyJS is installed.
     * Throws an exception if it's not installed.
     *
     * @throws QUI\Exception
     */
    public static function checkUglifyTerserJsInstalled()
    {
        if (self::$isUglifyTerserJsInstalled !== null) {
            if (self::$isUglifyTerserJsInstalled === false) {
                throw new QUI\Exception('uglifyjs.terser is not installed');
            }

            return;
        }

        self::$isUglifyTerserJsInstalled = false;

        if (!self::isCommandAvailable("uglifyjs.terser")) {
            throw new QUI\Exception('uglifyjs.terser is not installed');
        }

        // Only reached if no exception is thrown above
        self::$isUglifyTerserJsInstalled = true;
    }

    /**
     * Returns if UglifyJS is installed.
     *
     * @return bool
     */
    public static function isUglifyJsInstalled(): ?bool
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
    public static function isCommandAvailable(string $command): bool
    {
        exec("command -v $command", $output, $returnCode);

        return $returnCode == 0;
    }
}
