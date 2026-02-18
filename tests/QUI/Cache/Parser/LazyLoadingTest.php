<?php

namespace QUITests\Cache\Parser;

use PHPUnit\Framework\TestCase;
use QUI\Cache\Parser\LazyLoading;

class LazyLoadingTest extends TestCase
{
    public function testParseAddsLazyLoadingForRegularImages(): void
    {
        $html = '<div><img src="/media/test.jpg" alt="Example"></div>';
        $result = LazyLoading::getInstance()->parse($html);

        $this->assertStringContainsString('src="/media/test.jpg"', $result);
        $this->assertStringContainsString('loading="lazy"', $result);
        $this->assertStringContainsString('class="lazyload"', $result);
    }

    public function testParseAppendsLazyloadClassWhenClassAlreadyExists(): void
    {
        $html = '<img src="/media/test.jpg" class="hero">';
        $result = LazyLoading::getInstance()->parse($html);

        $this->assertStringContainsString('class="hero lazyload"', $result);
    }

    public function testParseKeepsAlreadyLazyLoadedImageUnchanged(): void
    {
        $html = '<img src="/media/test.jpg" loading="lazy" data-src="/media/test.jpg" class="hero">';
        $result = LazyLoading::getInstance()->parse($html);

        $this->assertSame($html, $result);
    }

    public function testParseSkipsSvgAndDataUris(): void
    {
        $svg = '<img src="/media/icon.svg">';
        $data = '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=">';

        $this->assertSame($svg, LazyLoading::getInstance()->parse($svg));
        $this->assertSame($data, LazyLoading::getInstance()->parse($data));
    }

    public function testParseSkipsImageWithoutSrc(): void
    {
        $html = '<img alt="no source">';
        $result = LazyLoading::getInstance()->parse($html);

        $this->assertSame($html, $result);
    }
}
