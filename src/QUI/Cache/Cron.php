<?php

/**
 * This file contains the \QUI\Cache\Cron
 */

namespace QUI\Cache;

use QUI;

/**
 * Class Cron
 * @package QUI\Cache
 */
class Cron
{
    /**
     * @param $params
     * @param $CronManager
     * @throws QUI\Exception
     */
    public static function optimizeProjectImages($params, $CronManager)
    {
        if (!isset($params['project'])) {
            throw new QUI\Exception('Need a project parameter to search release dates');
        }

        $Project = QUI::getProject($params['project']);
        $mtime   = 2;

        if (!isset($params['mtime'])) {
            $mtime = (int)$params['mtime'];
        }

        Optimizer::optimizeProjectImages($Project->getName(), $mtime);
    }
}
