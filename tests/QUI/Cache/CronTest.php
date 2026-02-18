<?php

namespace QUITests\Cache;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Cache\Cron;

class CronTest extends TestCase
{
    public function testOptimizeProjectImagesRequiresProjectParameter(): void
    {
        $this->expectException(QUI\Exception::class);
        Cron::optimizeProjectImages([]);
    }

    public function testClearTempFolderCanBeCalled(): void
    {
        Cron::clearTempFolder();
        $this->assertTrue(true);
    }

    public function testOptimizeProjectImagesWithUnknownProjectThrows(): void
    {
        $this->expectException(QUI\Exception::class);
        Cron::optimizeProjectImages([
            'project' => 'definitely-not-existing-project'
        ]);
    }
}
