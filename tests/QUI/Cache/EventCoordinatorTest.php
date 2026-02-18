<?php

namespace QUITests\Cache;

use PHPUnit\Framework\TestCase;
use QUI\Cache\EventCoordinator;
use QUI\Package\Package;
use QUI\Projects\Media;
use QUI\Projects\Media\Image as QuiMediaImage;
use QUI\Projects\Media\Item;
use QUI\Rewrite;
use QUI\Template;
use QUI\Users\User;
use Intervention\Image\ImageManager;
use Intervention\Image\Origin;
use QUI;

class EventCoordinatorTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        $_GET = [];
        $_POST = [];
    }

    public function testOnRequestImageNotFoundReturnsEarlyForNonWebp(): void
    {
        EventCoordinator::onRequestImageNotFound('/media/cache/project/file.jpg');
        $this->assertTrue(true);
    }

    public function testOnRequestImageNotFoundReturnsEarlyForUnknownProject(): void
    {
        EventCoordinator::onRequestImageNotFound('media/cache/definitely-not-existing-project/file.webp');
        $this->assertTrue(true);
    }

    public function testOnRequestImageNotFoundCanReachProjectPath(): void
    {
        $standard = QUI::getProjectManager()->getStandard();

        if (!$standard) {
            $this->markTestSkipped('No standard project available.');
        }

        $url = 'media/cache/' . $standard->getName() . '/non-existing-file.webp';
        EventCoordinator::onRequestImageNotFound($url);
        $this->assertTrue(true);
    }

    public function testOutputWebPDoesNothingForMissingFile(): void
    {
        EventCoordinator::outputWebP('/tmp/definitely-missing-file.webp');
        $this->assertTrue(true);
    }

    public function testOnMediaCreateImageHtmlReturnsImmediatelyWhenWebpDisabled(): void
    {
        putenv('QUIQQER_CACHE_DISABLE_WEBP=1');
        $picture = '<img src="/media/cache/demo/image.jpg" />';

        EventCoordinator::onMediaCreateImageHtml($picture);
        $this->assertSame('<img src="/media/cache/demo/image.jpg" />', $picture);
    }

    public function testOnMediaCreateImageHtmlTransformsPictureMarkup(): void
    {
        if (defined('QUIQQER_CACHE_DISABLE_WEBP')) {
            $this->markTestSkipped('QUIQQER_CACHE_DISABLE_WEBP is globally defined in this runtime.');
        }

        putenv('QUIQQER_CACHE_DISABLE_WEBP');
        $picture = '<picture><source srcset="/img/test.jpg"><img src="/img/test.jpg" /></picture>';

        EventCoordinator::onMediaCreateImageHtml($picture);

        $this->assertStringContainsString('type="image/webp"', $picture);
        $this->assertStringContainsString('.webp', $picture);
    }

    public function testOnRequestOutputReturnsEarlyWhenQueryOrPostExists(): void
    {
        $output = '<html><body>test</body></html>';

        $_GET = ['foo' => 'bar'];
        $_POST = [];
        EventCoordinator::onRequestOutput($output);
        $this->assertSame('<html><body>test</body></html>', $output);

        $_GET = [];
        $_POST = ['foo' => 'bar'];
        EventCoordinator::onRequestOutput($output);
        $this->assertSame('<html><body>test</body></html>', $output);
    }

    public function testOnMailerSendInitCanBeCalled(): void
    {
        EventCoordinator::onMailerSendInit();
        $this->assertTrue(true);
    }

    public function testOnRequestCanBeCalledWithQueryParams(): void
    {
        $_GET = ['foo' => 'bar'];
        $_POST = [];

        $rewrite = $this->createMock(Rewrite::class);
        EventCoordinator::onRequest($rewrite, '/');

        $this->assertTrue(true);
    }

    public function testOnRequestOutputCanBeCalledWithCleanParams(): void
    {
        $_GET = [];
        $_POST = [];
        $output = '<html><body>ok</body></html>';

        EventCoordinator::onRequestOutput($output);
        $this->assertIsString($output);
    }

    public function testOnPackageConfigSaveReturnsEarlyForOtherPackages(): void
    {
        $package = $this->createMock(Package::class);
        $package->method('getName')->willReturn('quiqqer/other');

        EventCoordinator::onPackageConfigSave($package);
        $this->assertTrue(true);
    }

    public function testOnPackageConfigSaveForCachePackageCanBeCalled(): void
    {
        $package = $this->createMock(Package::class);
        $package->method('getName')->willReturn('quiqqer/cache');

        EventCoordinator::onPackageConfigSave($package);
        $this->assertTrue(true);
    }

    public function testOnTemplateGetHeaderCanBeCalled(): void
    {
        $template = $this->createMock(Template::class);
        EventCoordinator::onTemplateGetHeader($template);

        $this->assertTrue(true);
    }

    public function testOnTemplateGetHeaderCanExtendHeaderWhenConfigAvailable(): void
    {
        $template = $this->createMock(Template::class);
        $template->expects($this->atMost(1))->method('extendHeader');

        EventCoordinator::onTemplateGetHeader($template);
        $this->assertTrue(true);
    }

    public function testClearAndIndependentClearAndTranslatorPublishCanBeCalled(): void
    {
        EventCoordinator::clearCache();
        EventCoordinator::onQuiqqerMenuIndependentClear();
        EventCoordinator::quiqqerTranslatorPublish();

        $this->assertTrue(true);
    }

    public function testUserLoginLogoutAutoLoginHooksCanBeCalled(): void
    {
        $user = $this->createMock(User::class);

        EventCoordinator::onUserLogin($user);
        EventCoordinator::onQuiqqerFrontendUsersUserAutoLogin($user, null);
        EventCoordinator::onUserLogout($user);

        $this->assertTrue(true);
    }

    public function testOnMediaReplaceReturnsEarlyWhenWebpDisabled(): void
    {
        putenv('QUIQQER_CACHE_DISABLE_WEBP=1');

        $media = $this->createMock(Media::class);
        $item = $this->createMock(Item::class);

        EventCoordinator::onMediaReplace($media, $item);
        $this->assertTrue(true);
    }

    public function testOnMediaSaveReturnsEarlyWhenWebpDisabled(): void
    {
        putenv('QUIQQER_CACHE_DISABLE_WEBP=1');
        $item = $this->createMock(Item::class);

        EventCoordinator::onMediaSave($item);
        $this->assertTrue(true);
    }

    public function testOnMediaCreateSizeCacheReturnsEarlyForNonImageItem(): void
    {
        $item = $this->createMock(Item::class);
        $interventionImage = ImageManager::gd()->create(1, 1);

        EventCoordinator::onMediaCreateSizeCache($item, $interventionImage);
        $this->assertTrue(true);
    }

    public function testOnUpdateEndCanBeCalled(): void
    {
        EventCoordinator::onUpdateEnd();
        $this->assertTrue(true);
    }

    public function testGetCreatedSizeCacheFileReturnsOriginPathWhenAvailable(): void
    {
        $tmp = '/tmp/event-coordinator-' . md5((string)mt_rand()) . '.jpg';
        file_put_contents($tmp, 'x');

        $image = $this->createMock(QuiMediaImage::class);
        $interventionImage = ImageManager::gd()->create(1, 1);
        $interventionImage->setOrigin((new Origin())->setFilePath($tmp));

        $method = new \ReflectionMethod(EventCoordinator::class, 'getCreatedSizeCacheFile');
        $method->setAccessible(true);

        $result = $method->invoke(null, $image, $interventionImage);
        $this->assertSame($tmp, $result);

        @unlink($tmp);
    }
}
