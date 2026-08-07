<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\Image\Adapter;

use Magento\Framework\Image\Format\FormatInterface;

/**
 * Implemented by adapters able to write an image in a format other than the one it was read from.
 *
 * Without this an adapter always encodes with the source format, so saving a JPEG under a ".webp"
 * name would produce JPEG bytes behind a misleading extension.
 *
 * @api
 */
interface OutputFormatAwareInterface
{
    /**
     * Force the format used by the next save, by format name; null restores the source format
     *
     * @param string|null $formatName
     * @return void
     * @throws \InvalidArgumentException When the format is unknown or cannot be written
     */
    public function setOutputFormat(?string $formatName): void;

    /**
     * Retrieve the forced output format, if any
     *
     * @return FormatInterface|null
     */
    public function getOutputFormat(): ?FormatInterface;
}
