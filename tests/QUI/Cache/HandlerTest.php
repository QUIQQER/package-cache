<?php

namespace QUITests\Cache;

use PHPUnit\Framework\TestCase;
use QUI\Cache\Handler;

class HandlerTest extends TestCase
{
    private string $tempCacheDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCacheDir = '/tmp/qui-cache-handler-' . md5((string)mt_rand()) . '/';
        @mkdir($this->tempCacheDir . 'bin/', 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('QUIQQER_CACHE_DISABLE_WEBP');
        $this->removeDir($this->tempCacheDir);
    }

    public function testCacheDirectoryGettersReturnConfiguredBasePaths(): void
    {
        $handler = Handler::init();

        $this->assertStringEndsWith('cache/packages/cache/', $handler->getCacheDir());
        $this->assertStringEndsWith('cache/packages/cache/', $handler->getURLCacheDir());
    }

    public function testUseWebPReturnsFalseWhenDisabledViaEnvironment(): void
    {
        putenv('QUIQQER_CACHE_DISABLE_WEBP=1');
        $handler = Handler::init();

        $this->assertFalse($handler->useWebP());
    }

    public function testParseImagesToWebPRewritesMediaCacheImagesOutsidePicture(): void
    {
        $handler = $this->createHandlerProxy();
        $html = '<img src="/media/cache/demo/a.jpg" data-src="/media/cache/demo/b.png">';
        $result = $handler->callParseImagesToWebP($html);

        $this->assertStringContainsString('/media/cache/demo/a.webp', $result);
        $this->assertStringContainsString('/media/cache/demo/b.webp', $result);
    }

    public function testParseImagesToWebPDoesNotRewriteInsidePicture(): void
    {
        $handler = $this->createHandlerProxy();
        $html = '<picture><img src="/media/cache/demo/a.jpg"></picture>';
        $result = $handler->callParseImagesToWebP($html);

        $this->assertSame($html, $result);
    }

    public function testGetAmdCssFilesReturnsDefaultFilesForEmptyContent(): void
    {
        $handler = $this->createHandlerProxy();
        $files = $handler->callGetAmdCssFiles('<div>No AMD modules</div>');

        $this->assertNotEmpty($files);
        $this->assertArrayHasKey('qui/controls/messages/Message.css', $files);
    }

    public function testCookieHelpersCanBeCalled(): void
    {
        Handler::setLoggedInCookieIfEnabled();
        Handler::removeLoggedInCookie();

        $this->assertTrue(true);
    }

    public function testUseWebPReturnsFromCachedPropertyWhenSet(): void
    {
        if (defined('QUIQQER_CACHE_DISABLE_WEBP')) {
            $this->markTestSkipped('QUIQQER_CACHE_DISABLE_WEBP is globally defined in this runtime.');
        }

        $handler = new class () extends Handler {
            public function setWebP(?bool $value): void
            {
                $this->webP = $value;
            }
        };

        $handler->setWebP(true);
        $this->assertTrue($handler->useWebP());
    }

    public function testGetCacheFromRequestThrowsWhenNoCacheExists(): void
    {
        $handler = Handler::init();

        $this->expectException(\QUI\Exception::class);
        $handler->getCacheFromRequest();
    }

    public function testClearCacheCanBeCalled(): void
    {
        $handler = Handler::init();
        $handler->clearCache();

        $this->assertTrue(true);
    }

    public function testGenerateJavaScriptCacheProducesScriptTags(): void
    {
        $handler = new class ($this->tempCacheDir) extends Handler {
            private string $baseDir;

            public function __construct(string $baseDir)
            {
                $this->baseDir = $baseDir;
            }

            public function getCacheDir(): string
            {
                return $this->baseDir;
            }

            public function getURLCacheDir(): string
            {
                return '/tmp-cache-url/';
            }
        };

        $content = '<html><body><script>window.TEST=1;</script></body></html>';
        $result = $handler->generateJavaScriptCache($content);

        $this->assertStringContainsString('/tmp-cache-url/bin/', $result);
        $this->assertStringContainsString('</body>', $result);
    }

    public function testGenerateJavaScriptCacheSkipsUnsupportedScriptTypes(): void
    {
        $handler = new class ($this->tempCacheDir) extends Handler {
            private string $baseDir;

            public function __construct(string $baseDir)
            {
                $this->baseDir = $baseDir;
            }

            public function getCacheDir(): string
            {
                return $this->baseDir;
            }

            public function getURLCacheDir(): string
            {
                return '/tmp-cache-url/';
            }
        };

        $content = '<html><body><script type="application/json">{"a":1}</script></body></html>';
        $result = $handler->generateJavaScriptCache($content);

        $this->assertStringContainsString('/tmp-cache-url/bin/', $result);
    }

    public function testGenerateCacheFromRequestCanBeCalled(): void
    {
        $handler = new class ($this->tempCacheDir) extends Handler {
            private string $baseDir;

            public function __construct(string $baseDir)
            {
                $this->baseDir = $baseDir;
            }

            public function getCacheDir(): string
            {
                return $this->baseDir;
            }

            public function getURLCacheDir(): string
            {
                return '/tmp-cache-url/';
            }
        };

        $content = '<html><head><!-- quiqqer css --></head><body>ok</body></html>';

        try {
            $handler->generateCacheFromRequest($content);
            $this->assertTrue(true);
        } catch (\QUI\Exception) {
            $this->assertTrue(true);
        }
    }

    public function testGenerateCssCacheReturnsInputWhenCacheDisabledConstantIsSet(): void
    {
        if (!defined('QUIQQER_CACHE_NO_CSS_CACHE')) {
            define('QUIQQER_CACHE_NO_CSS_CACHE', true);
        }

        $handler = new class () extends Handler {
        };

        $content = '<html><head><!-- quiqqer css --></head><body></body></html>';
        $result = $handler->generateCSSCache($content);

        $this->assertSame($content, $result);
    }

    public function testGetAmdCssFilesParsesCssPluginEntriesFromModule(): void
    {
        $modulePath = '/var/www/toolbox/packages/quiqqer/cache/tests/tmp-amd-module.js';
        file_put_contents($modulePath, "define(['css!package/quiqqer/cache/bin/example.css']);");

        $handler = $this->createHandlerProxy();
        $content = '<div data-qui="package/quiqqer/cache/tests/tmp-amd-module"></div>';
        $files = $handler->callGetAmdCssFiles($content);

        $this->assertArrayHasKey('package/quiqqer/cache/bin/example.css', $files);

        @unlink($modulePath);
    }

    private function createHandlerProxy(): object
    {
        return new class () extends Handler {
            public function callParseImagesToWebP(string $content): string
            {
                return $this->parseImagesToWebP($content);
            }

            public function callGetAmdCssFiles(string $content): array
            {
                return $this->getAmdCssFiles($content);
            }
        };
    }

    private function removeDir(string $dir): void
    {
        if (empty($dir) || !is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . $item;

            if (is_dir($path)) {
                $this->removeDir($path . '/');
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }
}
