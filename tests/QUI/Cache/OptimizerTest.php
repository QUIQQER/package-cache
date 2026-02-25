<?php

namespace QUITests\Cache;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Cache\Optimizer;

class OptimizerTest extends TestCase
{
    private string $fixtureCssFile = '';

    protected function setUp(): void
    {
        parent::setUp();
        $fixtureDir = __DIR__ . '/fixtures';
        @mkdir($fixtureDir, 0777, true);
        $this->fixtureCssFile = $fixtureDir . '/optimizer.css';
        file_put_contents($this->fixtureCssFile, "body {\n    color: red;\n}\n");
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->setStaticProperty('isJpegoptimInstalled', null);
        $this->setStaticProperty('isOptiPngInstalled', null);
        $this->setStaticProperty('isWebPInstalled', null);
        $this->setStaticProperty('isUglifyJsInstalled', null);
        $this->setStaticProperty('isUglifyTerserJsInstalled', null);
        if (!empty($this->fixtureCssFile) && file_exists($this->fixtureCssFile)) {
            @unlink($this->fixtureCssFile);
        }
    }

    public function testIsCommandAvailableForKnownAndUnknownCommands(): void
    {
        $this->assertTrue(Optimizer::isCommandAvailable('sh'));
        $this->assertFalse(Optimizer::isCommandAvailable('definitely-not-a-real-command-quiqqer'));
    }

    public function testJpegoptimStateHelpers(): void
    {
        $this->setStaticProperty('isJpegoptimInstalled', true);
        $this->assertTrue(Optimizer::isJpegoptimInstalled());
        Optimizer::checkJpegoptimInstalled();

        $this->setStaticProperty('isJpegoptimInstalled', false);
        $this->assertFalse(Optimizer::isJpegoptimInstalled());

        $this->expectException(QUI\Exception::class);
        Optimizer::checkJpegoptimInstalled();
    }

    public function testOptiPngStateHelpers(): void
    {
        $this->setStaticProperty('isOptiPngInstalled', true);
        $this->assertTrue(Optimizer::isOptiPngInstalled());
        Optimizer::checkOptiPngInstalled();

        $this->setStaticProperty('isOptiPngInstalled', false);
        $this->assertFalse(Optimizer::isOptiPngInstalled());

        $this->expectException(QUI\Exception::class);
        Optimizer::checkOptiPngInstalled();
    }

    public function testWebPStateHelpersAndCommandDetection(): void
    {
        $this->setStaticProperty('isWebPInstalled', true);
        $this->assertTrue(Optimizer::isWebPInstalled());
        Optimizer::checkWebPInstalled();

        $this->setStaticProperty('isWebPInstalled', false);
        $this->assertFalse(Optimizer::isWebPInstalled());

        try {
            Optimizer::checkWebPInstalled();
            $this->fail('Expected exception for false webp state');
        } catch (QUI\Exception) {
            $this->assertTrue(true);
        }

        $command = Optimizer::webPCommand();
        $this->assertTrue($command === false || $command === 'cwebp');
    }

    public function testUglifyStateHelpersAndCommandSelection(): void
    {
        $this->setStaticProperty('isUglifyTerserJsInstalled', true);
        $this->setStaticProperty('isUglifyJsInstalled', null);
        $this->assertSame('uglifyjs.terser', Optimizer::getUglifyCommand());

        $this->setStaticProperty('isUglifyTerserJsInstalled', null);
        $this->setStaticProperty('isUglifyJsInstalled', true);
        $this->assertSame('uglifyjs', Optimizer::getUglifyCommand());

        $this->setStaticProperty('isUglifyJsInstalled', false);
        $this->assertFalse(Optimizer::isUglifyJsInstalled());

        $this->expectException(QUI\Exception::class);
        Optimizer::checkUglifyJsInstalled();
    }

    public function testCheckUglifyTerserInstalledThrowsWhenStateIsFalse(): void
    {
        $this->setStaticProperty('isUglifyTerserJsInstalled', false);

        $this->expectException(QUI\Exception::class);
        Optimizer::checkUglifyTerserJsInstalled();
    }

    public function testConvertToWebPReturnsFalseForInvalidFiles(): void
    {
        $missing = '/tmp/does-not-exist-' . md5((string)mt_rand()) . '.png';
        $this->assertFalse(Optimizer::convertToWebP($missing));

        $svg = '/tmp/cache-test-' . md5((string)mt_rand()) . '.svg';
        file_put_contents($svg, '<svg></svg>');
        $this->assertFalse(Optimizer::convertToWebP($svg));
        @unlink($svg);

        $noExtension = '/tmp/cache-test-' . md5((string)mt_rand());
        file_put_contents($noExtension, 'x');
        $this->assertFalse(Optimizer::convertToWebP($noExtension));
        @unlink($noExtension);

        $gif = '/tmp/cache-test-' . md5((string)mt_rand()) . '.gif';
        file_put_contents($gif, 'GIF89a');
        $webpPath = Optimizer::convertToWebP($gif);
        $this->assertIsString($webpPath);
        $this->assertStringEndsWith('.webp', (string)$webpPath);
        @unlink($gif);
    }

    public function testOptimizePngAndJpgReturnEarlyWhenToolsDisabled(): void
    {
        $this->setStaticProperty('isOptiPngInstalled', false);
        $this->setStaticProperty('isJpegoptimInstalled', false);

        Optimizer::optimizePNG('/tmp/non-existing-file.png');
        Optimizer::optimizeJPG('/tmp/non-existing-file.jpg');

        $this->assertTrue(true);
    }

    public function testOptimizePngAndJpgThrowWhenEnabledAndFileMissing(): void
    {
        $this->setStaticProperty('isOptiPngInstalled', true);

        try {
            Optimizer::optimizePNG('/tmp/non-existing-file.png');
            $this->fail('Expected exception for missing PNG file');
        } catch (QUI\Exception) {
            $this->assertTrue(true);
        }

        $this->setStaticProperty('isJpegoptimInstalled', true);

        $this->expectException(QUI\Exception::class);
        Optimizer::optimizeJPG('/tmp/non-existing-file.jpg');
    }

    public function testOptimizeHtmlAndCssNotFoundPath(): void
    {
        $minified = Optimizer::optimizeHtml("<div>  Test  </div>\n");
        $this->assertIsString($minified);
        $this->assertNotEmpty($minified);

        $this->expectException(QUI\Exception::class);
        Optimizer::optimizeCSS('/definitely/not/found.css');
    }

    public function testOptimizeCssCanMinifyExistingFile(): void
    {
        $relativeFile = str_replace((string)CMS_DIR, '', $this->fixtureCssFile);
        $result = Optimizer::optimizeCSS($relativeFile);

        $this->assertIsString($result);
        $this->assertStringContainsString('color:red', str_replace(' ', '', $result));
    }

    public function testOptimizeJavaScriptViaQjoReturnsWhenDisabledOrUnavailable(): void
    {
        Optimizer::optimizeJavaScriptViaQJO('/tmp/non-existing-file.js');
        $this->assertTrue(true);
    }

    public function testOptimizeProjectImagesCanBeCalled(): void
    {
        $standard = QUI::getProjectManager()->getStandard();

        if (!$standard) {
            $this->markTestSkipped('No standard project available.');
        }

        Optimizer::optimizeProjectImages($standard->getName(), 1);
        $this->assertTrue(true);
    }

    public function testGetUglifyCommandThrowsWhenNoImplementationIsAvailable(): void
    {
        $this->setStaticProperty('isUglifyJsInstalled', false);
        $this->setStaticProperty('isUglifyTerserJsInstalled', false);

        $this->expectException(QUI\Exception::class);
        Optimizer::getUglifyCommand();
    }

    private function setStaticProperty(string $name, mixed $value): void
    {
        $reflection = new \ReflectionClass(Optimizer::class);
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }
}
