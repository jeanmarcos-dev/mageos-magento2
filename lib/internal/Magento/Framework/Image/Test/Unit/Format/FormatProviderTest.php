<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\Image\Test\Unit\Format;

use Magento\Framework\Image\Format\Format;
use Magento\Framework\Image\Format\FormatInterface;
use Magento\Framework\Image\Format\FormatProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatProviderTest extends TestCase
{
    /**
     * @var FormatProvider
     */
    private $provider;

    protected function setUp(): void
    {
        $this->provider = new FormatProvider(
            [
                'jpeg' => new Format(
                    'jpeg',
                    ['jpg', 'jpeg', 'jpe'],
                    ['image/jpeg', 'image/jpg'],
                    IMAGETYPE_JPEG,
                    false,
                    true
                ),
                'png' => new Format('png', ['png'], ['image/png'], IMAGETYPE_PNG, true, false),
                'webp' => new Format('webp', ['webp'], ['image/webp'], IMAGETYPE_WEBP, true, true),
                'avif' => new Format('avif', ['avif'], ['image/avif'], IMAGETYPE_AVIF, true, true),
            ]
        );
    }

    public function testGetAllIsKeyedByFormatName(): void
    {
        $this->assertSame(['jpeg', 'png', 'webp', 'avif'], array_keys($this->provider->getAll()));
    }

    /**
     * @param string $extension
     * @param string|null $expectedName
     */
    #[DataProvider('extensionProvider')]
    public function testGetByExtension(string $extension, ?string $expectedName): void
    {
        $format = $this->provider->getByExtension($extension);

        $this->assertSame($expectedName, $format ? $format->getName() : null);
    }

    /**
     * @return array
     */
    public static function extensionProvider(): array
    {
        return [
            'primary extension' => ['webp', 'webp'],
            'uppercase' => ['WEBP', 'webp'],
            'leading dot' => ['.webp', 'webp'],
            'secondary extension of a format' => ['jpe', 'jpeg'],
            'avif alongside webp' => ['avif', 'avif'],
            'unregistered extension' => ['tiff', null],
            'empty string' => ['', null],
        ];
    }

    public function testGetByMimeTypeResolvesEverySynonym(): void
    {
        $this->assertSame('jpeg', $this->provider->getByMimeType('image/jpg')->getName());
        $this->assertSame('jpeg', $this->provider->getByMimeType('IMAGE/JPEG')->getName());
        $this->assertSame('webp', $this->provider->getByMimeType('image/webp')->getName());
        $this->assertNull($this->provider->getByMimeType('image/tiff'));
    }

    public function testGetByImageType(): void
    {
        $this->assertSame('webp', $this->provider->getByImageType(IMAGETYPE_WEBP)->getName());
        $this->assertSame('avif', $this->provider->getByImageType(IMAGETYPE_AVIF)->getName());
        $this->assertNull($this->provider->getByImageType(IMAGETYPE_BMP));
    }

    public function testGetExtensionsCoversEveryRegisteredSpelling(): void
    {
        $this->assertSame(['jpg', 'jpeg', 'jpe', 'png', 'webp', 'avif'], $this->provider->getExtensions());
    }

    public function testGetMimeTypes(): void
    {
        $this->assertSame(
            ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/avif'],
            $this->provider->getMimeTypes()
        );
    }

    public function testGetMimeTypeToExtensionMapUsesThePrimaryExtension(): void
    {
        $this->assertSame(
            [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/avif' => 'avif',
            ],
            $this->provider->getMimeTypeToExtensionMap()
        );
    }

    public function testCapabilityFlagsAreExposed(): void
    {
        $webp = $this->provider->get('webp');

        $this->assertTrue($webp->supportsAlpha());
        $this->assertTrue($webp->supportsQuality());
        $this->assertFalse($this->provider->get('jpeg')->supportsAlpha());
        $this->assertFalse($this->provider->get('png')->supportsQuality());
    }

    public function testUnknownNameYieldsNull(): void
    {
        $this->assertNull($this->provider->get('jxl'));
    }

    public function testNullEntriesAreIgnored(): void
    {
        $provider = new FormatProvider(
            [
                'png' => new Format('png', ['png'], ['image/png'], IMAGETYPE_PNG),
                'webp' => null,
            ]
        );

        $this->assertSame(['png'], array_keys($provider->getAll()));
    }

    public function testNonFormatEntryIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(FormatInterface::class);

        new FormatProvider(['bogus' => new \stdClass()]);
    }

    public function testFormatRequiresAnExtension(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Format('webp', [], ['image/webp'], IMAGETYPE_WEBP);
    }

    public function testFormatRequiresAMimeType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Format('webp', ['webp'], [], IMAGETYPE_WEBP);
    }

    public function testFormatNormalisesCaseAndDuplicates(): void
    {
        $format = new Format('WebP', ['WEBP', 'webp'], ['IMAGE/WEBP'], IMAGETYPE_WEBP);

        $this->assertSame('webp', $format->getName());
        $this->assertSame(['webp'], $format->getExtensions());
        $this->assertSame('image/webp', $format->getPrimaryMimeType());
        $this->assertSame('webp', $format->getPrimaryExtension());
    }

    public function testAFormatDeclaredWithAnUpperCaseNameStaysReachable(): void
    {
        // getAll() is keyed by name and get() lower cases its argument, so the name has to be
        // normalised on the way in or the format becomes unreachable.
        $provider = new FormatProvider(['webp' => new Format('WebP', ['webp'], ['image/webp'], IMAGETYPE_WEBP)]);

        $this->assertSame(['webp'], array_keys($provider->getAll()));
        $this->assertSame('webp', $provider->get('WebP')->getName());
    }
}
