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
    static function optimizeAMD(array $needles, array $requireConf)
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
    static function optimizeCSS($cssfile)
    {
        $cssfilePath = CMS_DIR . $cssfile;

        if (!file_exists($cssfilePath)) {

            $parse       = parse_url($cssfilePath);
            $cssfilePath = $parse['path'];

            if (!file_exists($cssfilePath)) {
                throw new QUI\Exception('File not found', 404);
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
    static function optimizeJavaScript($jsfile)
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
    static function optimizePNG($file)
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
    static function optimizeJPG($file)
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


//
//    /**
//     * @param null|QUI\System\Console\Tool $Output
//     * @throws QUI\Exception
//     */
//    static function ___optimize($Output = null)
//    {
//        $CacheHandler = QUI\Cache\Handler::init();
//
//        $command     = 'nodejs';
//        $nodejsCheck = shell_exec("which nodejs");
//
//        if (empty($nodejsCheck)) {
//            $command     = 'node';
//            $nodejsCheck = shell_exec("which node");
//        }
//
//        if (empty($nodejsCheck)) {
//            throw new QUI\Exception('nodejs is not installed or is not callable');
//        }
//
//        // create amd bin folder
//        $amdDir     = $CacheHandler->getCacheDir() . 'amd/';
//        $amdBinDir  = $amdDir . 'bin/';
//        $amdCopyDir = $amdDir . 'copy/';
//        $rJsFile    = OPT_DIR . 'quiqqer/cache/amd/r.js';
//
//        QUI::getTemp()->moveToTemp($amdDir);
//
//
//        // build main require js build config
//        if ($Output) {
//            $Output->writeLn('Build require config');
//        }
//
//        if ($Output) {
//            $Output->writeLn('Create ' . $amdDir);
//        }
//
//        QUI\Utils\System\File::mkdir($amdDir);
//        QUI\Utils\System\File::mkdir($amdCopyDir);
//
//
//        $buildRequireFile = $amdDir . 'build-config.js';
//
//        file_put_contents($buildRequireFile, '
//            requirejs.config({
//                baseUrl: "/",
//                path : {
//                    "package" : ".",
//                    "qui"     : "quiqqer/qui/qui"
//                },
//                map: {
//                    "*": {
//                        "css"  : "quiqqer/qui/qui/lib/css.js",
//                        "image": "quiqqer/qui/qui/lib/image.js",
//                        "text" : "quiqqer/qui/qui/lib/text.js"
//                    }
//                }
//            });'
//        );
//
//
//        // search packages for amd modules
//        if ($Output) {
//            $Output->writeLn('Search AMD modules');
//        }
//
//        $packages = QUI::getPackageManager()->getInstalled();
//
//        foreach ($packages as $package) {
//
//            $name       = $package['name'];
//            $packageDir = OPT_DIR . $name;
//
//            // find modules in bin dir
//            if ($name == 'quiqqer/qui') {
//                $jsFiles   = QUI\Utils\System\File::find($packageDir . '/qui', '*.js');
//                $cssFiles  = QUI\Utils\System\File::find($packageDir . '/qui', '*.css');
//                $htmlFiles = QUI\Utils\System\File::find($packageDir . '/qui', '*.html');
//
//            } else {
//                $jsFiles   = QUI\Utils\System\File::find($packageDir . '/bin', '*.js');
//                $cssFiles  = QUI\Utils\System\File::find($packageDir . '/bin', '*.css');
//                $htmlFiles = QUI\Utils\System\File::find($packageDir . '/bin', '*.html');
//            }
//
//            $buildDir   = $amdDir . 'bin/' . $name;
//            $amdModules = array();
//
//            // js copy
//            foreach ($jsFiles as $jsFile) {
//
//                $amdName = 'package/' . str_replace(array(OPT_DIR, '.js'), '', $jsFile);
//
//                $amdModules[] = array(
//                    'name' => $amdName
//                );
//
//                // copy file
//                $copyFile = str_replace(OPT_DIR, $amdCopyDir, $jsFile);
//
//                QUI\Utils\System\File::mkfile($copyFile);
//
//                file_put_contents(
//                    $copyFile,
//                    file_get_contents($jsFile)
//                );
//            }
//
//            // css copy
//            foreach ($cssFiles as $cssFile) {
//                $copyFile = str_replace(OPT_DIR, $amdCopyDir, $cssFile);
//
//                QUI\Utils\System\File::mkfile($copyFile);
//
//                file_put_contents(
//                    $copyFile,
//                    file_get_contents($cssFile)
//                );
//            }
//
//            foreach ($htmlFiles as $htmlFile) {
//                $copyFile = str_replace(OPT_DIR, $amdCopyDir, $htmlFile);
//
//                QUI\Utils\System\File::mkfile($copyFile);
//
//                file_put_contents(
//                    $copyFile,
//                    file_get_contents($htmlFile)
//                );
//            }
//        }
//
//        if ($Output) {
//            $Output->writeLn('Starting AMD building');
//        }
//
//
//        // build
//        chdir($amdDir);
//
//        // optimize each js file
//        $jsFiles = QUI\Utils\System\File::find($amdCopyDir, '*.js');
//
//        foreach ($jsFiles as $jsFile) {
//
//            $_jsFile = str_replace($amdCopyDir, '', $jsFile);
//
//            file_put_contents(
//                'build-file.js',
//
//                '({
//                    baseUrl: ".",
//                    paths: {
//                        jquery: "some/other/jquery"
//                    },
//                    name: "main",
//                    out: "main-built.js",
//                    mainConfigFile: "build-config.js"
//                })'
//            );
//
//            echo "\n\n" . $_jsFile . "\n";
//            exit;
//        }
//
//
//        return;
//
//
//        file_put_contents(
//            'build-amd.js',
//            '(' . json_encode(self::_getbuildParams(), \JSON_PRETTY_PRINT) . ')'
//        );
//
//        $cmd = "nodejs '{$rJsFile}' -o build-amd.js";
//
//        if (!$Output) {
//            shell_exec($cmd);
//
//        } else {
//
//            $descriptorspec = array(
//                0 => array("pipe", "r"),
//                1 => array("pipe", "w"),
//                2 => array("pipe", "w")
//            );
//
//            flush();
//
//            $process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());
//
//            if (is_resource($process)) {
//                while ($s = fgets($pipes[1])) {
//                    $Output->write($s);
//                    flush();
//                }
//            }
//        }
//
//        // clearing
//        chdir($amdDir);
//
//        unlink('build-amd.js');
//        unlink('build-config.js');
//        unlink('bin/build.txt');
//        unlink('bin/build-amd.js');
//        unlink('bin/build-config.js');
//
//        QUI::getTemp()->moveToTemp('copy/');
//
//        $dirs = QUI\Utils\System\File::readDir($amdBinDir . 'copy/');
//
//        foreach ($dirs as $dir) {
//            QUI\Utils\System\File::move(
//                $amdBinDir . 'copy/' . $dir,
//                $amdBinDir . $dir
//            );
//        }
//
//        QUI::getTemp()->moveToTemp($amdBinDir . 'copy/');
//
//
//        if ($Output) {
//            $Output->write('Done');
//            $Output->writeLn('');
//        }
//    }

    /**
     * Return config build params
     *
     * @return array
     */
    static protected function _getbuildParams()
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

