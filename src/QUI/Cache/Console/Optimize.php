<?php

/**
 * This file contains QUI\Cache\Console\Optimize
 */

namespace QUI\Cache\Console;

use QUI;

use function array_map;
use function array_unique;
use function count;
use function explode;
use function file_exists;
use function implode;
use function pathinfo;
use function shell_exec;
use function trim;

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
            ->addArgument('mtime', 'Only images are newer than (--mtime): in days. Default = 1000', false, true);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\System\Console\Tool::execute()
     */
    public function execute(): void
    {
        $project = $this->getArgument('project');
        $mtime = (int)$this->getArgument('mtime');

        if ($project === '' || $project === '*') {
            $projects = QUI::getProjectManager()->getProjectList();
            $projects = array_map(function ($Project) {
                return $Project->getName();
            }, $projects);

            $projects = array_unique($projects);

            $this->write('Please select a project [' . implode(',', $projects) . ']:');
            $project = $this->readInput();
        }

        if (empty($project)) {
            $project = QUI::getProjectManager()->getStandard()->getName();
        }

        $Project = QUI::getProjectManager()->getProject($project);
        $Media = $Project->getMedia();
        $cacheDir = $Media->getCacheDir();

        if (!$mtime) {
            $mtime = 1000;
        }

        if (QUI\Cache\Optimizer::isOptiPngInstalled()) {
            // find all pngs
            $this->writeLn('Optimize PNG files', 'green');

            $list = shell_exec('find "' . $cacheDir . '" -iname \*.png -type f -mtime -' . $mtime);
            $list = explode("\n", trim($list));
            $count = count($list);

            $this->resetColor();
            $this->writeLn('Found ' . $count . ' images');

            foreach ($list as $image) {
                try {
                    QUI\Cache\Optimizer::optimizePNG(CMS_DIR . $image);
                } catch (QUI\Exception) {
                    continue;
                }
            }
        } else {
            $this->writeLn(
                'Notice:',
                'yellow'
            );
            $this->writeLn(
                'In order to optimize PNGs you need to install OptiPNG on your system.',
                'yellow'
            );
            $this->writeLn(
                'Find out more about this in the wiki: https://dev.quiqqer.com/quiqqer/package-cache/wikis/home',
                'yellow'
            );
            $this->writeLn();
            $this->resetColor();
        }

        if (QUI\Cache\Optimizer::isJpegoptimInstalled()) {
            // find all jpgs
            $this->writeLn('Optimize JPG files ...', 'green');

            $list = shell_exec('find "' . $cacheDir . '" -iname \*.jp*g -type f -mtime -' . $mtime);
            $list = explode("\n", trim($list));
            $count = count($list);

            $this->resetColor();
            $this->writeLn('Found ' . $count . ' images');

            foreach ($list as $image) {
                try {
                    QUI\Cache\Optimizer::optimizeJPG(CMS_DIR . $image);
                } catch (QUI\Exception) {
                    continue;
                }
            }
        } else {
            $this->writeLn(
                'Notice:',
                'yellow'
            );
            $this->writeLn(
                'In order to optimize JPGs you need to install Jpegoptim on your system.',
                'yellow'
            );
            $this->writeLn(
                'Find out more about this in the wiki: https://dev.quiqqer.com/quiqqer/package-cache/wikis/home',
                'yellow'
            );
            $this->writeLn();
            $this->resetColor();
        }

        if (QUI\Cache\Handler::init()->useWebP()) {
            // find all jpgs
            $this->writeLn('Optimize images to webp files ...', 'green');

            $list = shell_exec(
                'find "' . $cacheDir . '" -name \'*\' -exec file {} \; | grep -o -P \'^.+: \w+ image\''
            );
            $list = explode("\n", trim($list));
            $count = count($list);

            $this->resetColor();
            $this->writeLn('Found ' . $count . ' images');

            foreach ($list as $image) {
                $image = explode(':', $image);
                $image = $image[0];

                // check if webp exists
                $parts = pathinfo(CMS_DIR . $image);
                $webPFile = $parts['dirname'] . DIRECTORY_SEPARATOR . $parts['filename'] . '.webp';

                if (!file_exists($webPFile)) {
                    QUI\Cache\Optimizer::convertToWebP(CMS_DIR . $image);
                }
            }
        }

        $this->writeLn('DONE.', 'green');
        $this->writeLn();
        $this->resetColor();
    }
}
