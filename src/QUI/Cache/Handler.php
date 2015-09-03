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
        return VAR_DIR . 'cache/plugins/cache/';
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
        $cachefile = $dir . md5($uri);


        if (file_exists($cachefile)) {
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
        $Request = QUI::getRequest();
        $uri     = $Request->getUri();
        $query   = $Request->getQueryString();

        if (!is_null($query)) {
            throw new QUI\Exception('Get Params exists. No Cache exists', 404);
        }

        $dir       = $this->getCacheDir();
        $cachefile = $dir . md5($uri);

        QUI\Utils\System\File::mkdir($dir);

        file_put_contents($cachefile, $content);
    }

    /**
     * Clear the complete cache
     */
    public function clearCache()
    {
        QUI::getTemp()->moveToTemp($this->getCacheDir());
    }
}
