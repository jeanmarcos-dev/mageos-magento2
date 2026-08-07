<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Catalog\Test\Unit\Model\Product\Image;

use Magento\Catalog\Model\Product\Image\VariantConfig;
use Magento\Catalog\Model\Product\Image\VariantGenerator;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\Image\Adapter\AdapterInterface;
use Magento\Framework\Image\Adapter\Gd2;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\Image\Format\Format;
use Magento\Framework\Image\Format\FormatProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class VariantGeneratorTest extends TestCase
{
    /**
     * @var AdapterFactory|MockObject
     */
    private $adapterFactory;

    /**
     * @var VariantConfig|MockObject
     */
    private $config;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var VariantGenerator
     */
    private $generator;

    protected function setUp(): void
    {
        $this->adapterFactory = $this->createMock(AdapterFactory::class);
        $this->config = $this->createMock(VariantConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->generator = new VariantGenerator(
            $this->adapterFactory,
            $this->config,
            new FormatProvider(
                [
                    'jpeg' => new Format('jpeg', ['jpg', 'jpeg'], ['image/jpeg'], IMAGETYPE_JPEG, false, true),
                    'webp' => new Format('webp', ['webp'], ['image/webp'], IMAGETYPE_WEBP, true, true),
                    'avif' => new Format('avif', ['avif'], ['image/avif'], IMAGETYPE_AVIF, true, true),
                ]
            ),
            $this->logger,
            new IoFile()
        );
    }

    public function testWritesOneSiblingPerConfiguredFormat(): void
    {
        $this->config->method('getEnabledFormats')->willReturn(['webp', 'avif']);
        $this->config->method('getQuality')->willReturnMap([['webp', 80], ['avif', 50]]);

        $saved = [];
        $qualities = [];
        $adapter = $this->createMock(Gd2::class);
        $adapter->method('supportsOutputFormat')->willReturn(true);
        $adapter->method('setOutputFormat');
        $adapter->method('quality')->willReturnCallback(function ($q) use (&$qualities) {
            $qualities[] = $q;
            return $q;
        });
        $adapter->method('save')->willReturnCallback(function ($path) use (&$saved) {
            $saved[] = $path;
        });
        $this->adapterFactory->method('create')->willReturn($adapter);

        $result = $this->generator->execute('/media/cache/x/foo.jpg');

        $this->assertSame(
            ['/media/cache/x/foo.jpg.webp', '/media/cache/x/foo.jpg.avif'],
            $result
        );
        $this->assertSame($result, $saved);
        // Each format has to carry its own quality; AVIF needs a lower value than WebP.
        $this->assertSame([80, 50], $qualities);
    }

    public function testNoConfiguredFormatMeansNoWork(): void
    {
        $this->config->method('getEnabledFormats')->willReturn([]);
        $this->adapterFactory->expects($this->never())->method('create');

        $this->assertSame([], $this->generator->execute('/media/cache/x/foo.jpg'));
    }

    public function testSourceIsNotReEncodedIntoItsOwnFormat(): void
    {
        $this->config->method('getEnabledFormats')->willReturn(['webp', 'avif']);
        $this->config->method('getQuality')->willReturn(50);

        $saved = [];
        $adapter = $this->createMock(Gd2::class);
        $adapter->method('supportsOutputFormat')->willReturn(true);
        $adapter->method('save')->willReturnCallback(function ($path) use (&$saved) {
            $saved[] = $path;
        });
        $this->adapterFactory->method('create')->willReturn($adapter);

        $this->assertSame(['/media/cache/x/foo.webp.avif'], $this->generator->execute('/media/cache/x/foo.webp'));
        $this->assertSame(['/media/cache/x/foo.webp.avif'], $saved);
    }

    public function testUnregisteredFormatIsIgnored(): void
    {
        $this->config->method('getEnabledFormats')->willReturn(['jxl']);
        $this->adapterFactory->expects($this->never())->method('create');

        $this->assertSame([], $this->generator->execute('/media/cache/x/foo.jpg'));
    }

    /**
     * A placeholder image leaves the path empty.
     *
     * @param string|null $path
     */
    #[DataProvider('emptyPathProvider')]
    public function testEmptyPathIsTolerated(?string $path): void
    {
        $this->adapterFactory->expects($this->never())->method('create');

        $this->assertSame([], $this->generator->execute($path));
    }

    /**
     * @return array
     */
    public static function emptyPathProvider(): array
    {
        return [
            'null path' => [null],
            'empty path' => [''],
        ];
    }

    public function testAnAdapterWithoutFormatOverrideSupportIsSkipped(): void
    {
        $this->config->method('getEnabledFormats')->willReturn(['webp']);
        // A third party adapter predating OutputFormatAwareInterface would otherwise be asked to
        // write WebP bytes and silently produce the source format under a .webp name.
        $this->adapterFactory->method('create')->willReturn($this->createMock(AdapterInterface::class));

        $this->assertSame([], $this->generator->execute('/media/cache/x/foo.jpg'));
    }

    public function testAnUnencodableFormatNeverOpensTheSourceImage(): void
    {
        $this->config->method('getEnabledFormats')->willReturn(['avif']);

        $adapter = $this->createMock(Gd2::class);
        $adapter->method('supportsOutputFormat')->willReturn(false);
        // Opening decodes the whole image; a build without the codec must not pay that per image.
        $adapter->expects($this->never())->method('open');
        $adapter->expects($this->never())->method('save');
        $this->adapterFactory->method('create')->willReturn($adapter);

        $this->assertSame([], $this->generator->execute('/media/cache/x/foo.jpg'));
    }

    public function testTheUnencodableWarningIsLoggedOncePerFormat(): void
    {
        $this->config->method('getEnabledFormats')->willReturn(['avif']);

        $adapter = $this->createMock(Gd2::class);
        $adapter->method('supportsOutputFormat')->willReturn(false);
        $this->adapterFactory->method('create')->willReturn($adapter);

        // Three images, one warning: the message is about the build, not about each file.
        $this->logger->expects($this->once())->method('warning');

        $this->generator->execute('/media/cache/x/a.jpg');
        $this->generator->execute('/media/cache/x/b.jpg');
        $this->generator->execute('/media/cache/x/c.jpg');
    }

    public function testOneFailingFormatDoesNotStopTheOthers(): void
    {
        $this->config->method('getEnabledFormats')->willReturn(['avif', 'webp']);
        $this->config->method('getQuality')->willReturn(50);

        $adapter = $this->createMock(Gd2::class);
        // A GD build without libavif rejects AVIF but still handles WebP.
        $adapter->method('supportsOutputFormat')->willReturnCallback(
            static fn (string $format): bool => $format !== 'avif'
        );
        $this->adapterFactory->method('create')->willReturn($adapter);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('cannot encode AVIF'));

        $this->assertSame(['/media/cache/x/foo.jpg.webp'], $this->generator->execute('/media/cache/x/foo.jpg'));
    }
}
