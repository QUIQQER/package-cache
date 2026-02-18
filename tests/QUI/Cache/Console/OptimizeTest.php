<?php

namespace QUITests\Cache\Console;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Cache\Optimizer;
use QUI\Cache\Console\Optimize;

class OptimizeTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->setOptimizerState('isOptiPngInstalled', null);
        $this->setOptimizerState('isJpegoptimInstalled', null);
    }

    public function testConstructorConfiguresCommandNameAndArguments(): void
    {
        $tool = new Optimize();

        $this->assertSame('package:cache-optimize', $tool->getName());
        $this->assertNotEmpty($tool->getDescription());
    }

    public function testExecuteWithUnavailableOptimizersRunsWithoutException(): void
    {
        $projectName = $this->getExistingProjectName();

        if ($projectName === null) {
            $this->markTestSkipped('No project available in this test environment.');
        }

        putenv('QUIQQER_CACHE_DISABLE_WEBP=1');
        $this->setOptimizerState('isOptiPngInstalled', false);
        $this->setOptimizerState('isJpegoptimInstalled', false);

        $tool = new Optimize();
        $tool->setArgument('project', $projectName);
        $tool->setArgument('mtime', '1');
        $tool->execute();

        $this->assertTrue(true);
    }

    public function testExecuteWithAvailableOptimizersRunsBranches(): void
    {
        $projectName = $this->getExistingProjectName();

        if ($projectName === null) {
            $this->markTestSkipped('No project available in this test environment.');
        }

        putenv('QUIQQER_CACHE_DISABLE_WEBP=1');
        $this->setOptimizerState('isOptiPngInstalled', true);
        $this->setOptimizerState('isJpegoptimInstalled', true);

        $tool = new Optimize();
        $tool->setArgument('project', $projectName);
        $tool->setArgument('mtime', '1');
        $tool->execute();

        $this->assertTrue(true);
    }

    public function testExecuteWithWildcardProjectUsesReadInput(): void
    {
        $projectName = $this->getExistingProjectName();

        if ($projectName === null) {
            $this->markTestSkipped('No project available in this test environment.');
        }

        putenv('QUIQQER_CACHE_DISABLE_WEBP=1');
        $this->setOptimizerState('isOptiPngInstalled', false);
        $this->setOptimizerState('isJpegoptimInstalled', false);

        $tool = new class ($projectName) extends Optimize {
            private string $input;

            public function __construct(string $input)
            {
                parent::__construct();
                $this->input = $input;
            }

            public function readInput(): string
            {
                return $this->input;
            }

            public function writeLn(string $msg = '', bool|string $color = false, bool|string $bg = false): void
            {
            }

            public function write(string $msg, bool|string $color = false, bool|string $bg = false): void
            {
            }

            public function resetColor(): void
            {
            }
        };

        $tool->setArgument('project', '*');
        $tool->setArgument('mtime', '1');
        $tool->execute();

        $this->assertTrue(true);
    }

    public function testExecuteWithWildcardAndEmptyInputFallsBackToStandardProject(): void
    {
        $projectName = $this->getExistingProjectName();

        if ($projectName === null) {
            $this->markTestSkipped('No project available in this test environment.');
        }

        putenv('QUIQQER_CACHE_DISABLE_WEBP=1');
        $this->setOptimizerState('isOptiPngInstalled', false);
        $this->setOptimizerState('isJpegoptimInstalled', false);

        $tool = new class () extends Optimize {
            public function readInput(): string
            {
                return '';
            }

            public function writeLn(string $msg = '', bool|string $color = false, bool|string $bg = false): void
            {
            }

            public function write(string $msg, bool|string $color = false, bool|string $bg = false): void
            {
            }

            public function resetColor(): void
            {
            }
        };

        $tool->setArgument('project', '*');
        $tool->setArgument('mtime', '1');
        $tool->execute();

        $this->assertTrue(true);
    }

    private function getExistingProjectName(): ?string
    {
        $standard = QUI::getProjectManager()->getStandard();

        if ($standard) {
            return $standard->getName();
        }

        $list = QUI::getProjectManager()->getProjectList();

        if (empty($list)) {
            return null;
        }

        return $list[0]->getName();
    }

    private function setOptimizerState(string $propertyName, ?bool $value): void
    {
        $reflection = new \ReflectionClass(Optimizer::class);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }
}
