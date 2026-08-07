<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Catalog\Test\Unit\Model\Product\Image;

use Magento\Catalog\Model\Product\Image\VariantConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VariantConfigTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    /**
     * @param mixed $stored
     * @param array $expected
     */
    #[DataProvider('formatsProvider')]
    public function testGetEnabledFormats($stored, array $expected): void
    {
        $this->scopeConfig->method('getValue')->with(VariantConfig::XML_PATH_FORMATS)->willReturn($stored);

        $this->assertSame($expected, $this->config()->getEnabledFormats());
    }

    /**
     * @return array
     */
    public static function formatsProvider(): array
    {
        return [
            'unset' => [null, []],
            'empty string' => ['', []],
            'single format' => ['webp', ['webp']],
            'multiselect stores a comma separated list' => ['webp,avif', ['webp', 'avif']],
            'stray whitespace' => [' webp , avif ', ['webp', 'avif']],
            'stray separators' => ['webp,,avif,', ['webp', 'avif']],
        ];
    }

    public function testIsEnabledIsCaseInsensitive(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('webp,avif');
        $config = $this->config();

        $this->assertTrue($config->isEnabled('webp'));
        $this->assertTrue($config->isEnabled('AVIF'));
        $this->assertFalse($config->isEnabled('jpeg'));
    }

    /**
     * @param mixed $stored
     * @param int $expected
     */
    #[DataProvider('qualityProvider')]
    public function testGetQualityFallsBackPerFormat($stored, int $expected): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $path === 'catalog/image/avif_quality' ? $stored : null
        );

        $this->assertSame($expected, $this->config()->getQuality('avif'));
    }

    /**
     * @return array
     */
    public static function qualityProvider(): array
    {
        return [
            'configured' => ['42', 42],
            'unset uses the per format default' => [null, 50],
            'empty uses the per format default' => ['', 50],
            'below range uses the default' => ['0', 50],
            'above range uses the default' => ['101', 50],
        ];
    }

    public function testAFormatWithoutADeclaredDefaultFallsBackToEighty(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame(80, $this->config()->getQuality('jxl'));
    }

    /**
     * @return VariantConfig
     */
    private function config(): VariantConfig
    {
        return new VariantConfig($this->scopeConfig, ['webp' => 80, 'avif' => 50]);
    }
}
