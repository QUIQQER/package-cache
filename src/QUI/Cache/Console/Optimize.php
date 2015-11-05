<?php

/**
 * This file contains QUI\Cache\Console\Optimize
 */
namespace QUI\Cache\Console;

use QUI;

/**
 * Class Optimize
 *
 * @package QUI\Cache\Console
 */
class Optimize extends QUI\System\Console\Tool
{
    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->setName('package:cache-optimize')
            ->setDescription('Optimize images')
            ->addArgument('project', 'Name of the Project')
            ->addArgument('mtime', 'Only images are newer than (--mtime): in days. Default = 2', false, true);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\System\Console\Tool::execute()
     */
    public function execute()
    {
        $project = $this->getArgument('project');
        $mtime   = $this->getArgument('mtime');

        $Project  = QUI::getProjectManager()->getProject($project);
        $Media    = $Project->getMedia();
        $cacheDir = $Media->getCacheDir();

        if (!$mtime) {
            $mtime = 2;
        }

        // find all pngs
        $this->writeLn('Optimize PNG Files', 'green');

        $list  = shell_exec('find "' . $cacheDir . '" -iname \*.png -type f -mtime -' . $mtime);
        $list  = explode("\n", trim($list));
        $count = count($list);

        $this->resetColor();
        $this->writeLn('Found ' . $count . ' images');

        foreach ($list as $image) {
            if (file_exists(CMS_DIR . $image)) {
                try {
                    QUI\Cache\Optimizer::optimizePNG(CMS_DIR . $image);
                } catch (QUI\Exception $Exception) {
                    continue;
                }
            }
        }

        // find all jpgs
        $this->writeLn('Optimize JPG Files ...', 'green');

        $list  = shell_exec('find "' . $cacheDir . '" -iname \*.jp*g -type f -mtime -' . $mtime);
        $list  = explode("\n", trim($list));
        $count = count($list);

        $this->resetColor();
        $this->writeLn('Found ' . $count . ' images');

        foreach ($list as $image) {
            if (file_exists(CMS_DIR . $image)) {
                try {
                    QUI\Cache\Optimizer::optimizeJPG(CMS_DIR . $image);
                } catch (QUI\Exception $Exception) {
                    continue;
                }
            }
        }

        $this->writeLn('DONE', 'green');
        $this->writeLn();
        $this->resetColor();
    }
}