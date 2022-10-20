<?php

/**
 * This file contains QUI\Cache\Console\AMD
 */

namespace QUI\Cache\Console;

use QUI;

/**
 * Class AMD
 *
 * @package QUI\Cache\Console
 */
class AMD extends QUI\System\Console\Tool
{
    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->setName('package:cache-amd')
            ->setDescription('Optimize the AMD JavaScript Packages');
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\System\Console\Tool::execute()
     */
    public function execute()
    {
//        QUI\Cache\AMDOptimizer::optimize($this);
    }
}
