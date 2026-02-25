<?php

namespace QUITests\Cache\Console;

use PHPUnit\Framework\TestCase;
use QUI\Cache\Console\AMD;

class AMDTest extends TestCase
{
    public function testConstructorSetsCommandDataAndExecuteIsNoop(): void
    {
        $tool = new AMD();

        $this->assertSame('package:cache-amd', $tool->getName());
        $this->assertNotEmpty($tool->getDescription());

        $tool->execute();
        $this->assertTrue(true);
    }
}
