<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Catalog\Model\Config\Source\Image;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Image\Format\FormatProviderInterface;

/**
 * Formats a catalog image copy can be generated in.
 *
 * Candidates are declared through dependency injection and filtered against the format registry, so
 * a format the installation cannot describe never shows up as an option.
 */
class VariantFormat implements OptionSourceInterface
{
    /**
     * @var FormatProviderInterface
     */
    private $formatProvider;

    /**
     * @var string[]
     */
    private $candidates;

    /**
     * @param FormatProviderInterface $formatProvider
     * @param string[] $candidates Format names offered as delivery copies
     */
    public function __construct(FormatProviderInterface $formatProvider, array $candidates = [])
    {
        $this->formatProvider = $formatProvider;
        $this->candidates = $candidates;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach ($this->candidates as $name) {
            $format = $this->formatProvider->get($name);
            if ($format === null) {
                continue;
            }

            $options[] = [
                'value' => $format->getName(),
                'label' => strtoupper($format->getName()),
            ];
        }

        return $options;
    }
}
