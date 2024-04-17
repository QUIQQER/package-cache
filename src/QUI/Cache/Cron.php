<?php

/**
 * This file contains the \QUI\Cache\Cron
 */

namespace QUI\Cache;

use QUI;

use function array_map;
use function array_unique;

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
    public static function optimizeProjectImages($params, $CronManager): void
    {
        if (!isset($params['project'])) {
            throw new QUI\Exception('Need a project parameter');
        }

        if ($params['project'] === '*') {
            $projects = QUI::getProjectManager()->getProjectList();
            $projects = array_map(function ($Project) {
                return $Project->getName();
            }, $projects);

            $projects = array_unique($projects);
        } else {
            $projects = [$params['project']];
        }

        foreach ($projects as $project) {
            $Project = QUI::getProject($project);
            $mtime = 2;

            if (isset($params['mtime'])) {
                $mtime = (int)$params['mtime'];
            }

            Optimizer::optimizeProjectImages($Project->getName(), $mtime);
        }
    }

    /**
     * @deprecated
     *
     * Clear the temp folder
     */
    public static function clearTempFolder(): void
    {
        QUI\System\Log::addWarning(
            '\QUI\Cache::clearTempFolder is deprecated. Please switch cron to quiqqer/cron by deleting this cron and'
            . ' setting it up again.'
        );

        QUI\Cron\QuiqqerCrons::clearTempFolder();
    }
}
