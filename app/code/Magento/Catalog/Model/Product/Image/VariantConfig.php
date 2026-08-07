<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Catalog\Model\Product\Image;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Settings governing the extra copies written next to a cached catalog image.
 */
class VariantConfig
{
    public const XML_PATH_FORMATS = 'catalog/image/variant_formats';

    /**
     * Per format quality lives at catalog/image/<format>_quality.
     */
    private const XML_PATH_QUALITY_TEMPLATE = 'catalog/image/%s_quality';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var array<string, int>
     */
    private $defaultQuality;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param array $defaultQuality
     */
    public function __construct(ScopeConfigInterface $scopeConfig, array $defaultQuality = [])
    {
        $this->scopeConfig = $scopeConfig;
        $this->defaultQuality = $defaultQuality;
    }

    /**
     * Retrieve the format names a copy has to be generated for
     *
     * @return string[]
     */
    public function getEnabledFormats(): array
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_FORMATS);
        if ($value === '') {
            return [];
        }

        $formats = array_map('trim', explode(',', $value));

        return array_values(array_filter($formats, static fn (string $f): bool => $f !== ''));
    }

    /**
     * Whether a copy has to be generated in the given format
     *
     * @param string $format
     * @return bool
     */
    public function isEnabled(string $format): bool
    {
        return in_array(strtolower($format), $this->getEnabledFormats(), true);
    }

    /**
     * Retrieve the encoding quality for a generated copy
     *
     * AVIF reaches a comparable perceived quality well below the value JPEG needs, so each format
     * carries its own default rather than sharing one number.
     *
     * @param string $format
     * @return int
     */
    public function getQuality(string $format): int
    {
        $format = strtolower($format);
        $configured = $this->scopeConfig->getValue(sprintf(self::XML_PATH_QUALITY_TEMPLATE, $format));

        if ($configured !== null && $configured !== '') {
            $quality = (int) $configured;
            if ($quality >= 1 && $quality <= 100) {
                return $quality;
            }
        }

        return $this->defaultQuality[$format] ?? 80;
    }
}
