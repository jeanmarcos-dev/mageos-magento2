<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\Image\Format;

/**
 * Describes a raster image format the framework is able to read and write.
 *
 * @api
 */
interface FormatInterface
{
    /**
     * Retrieve the lower case format identifier, e.g. "webp"
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Retrieve every file extension the format is stored under, without a leading dot
     *
     * @return string[]
     */
    public function getExtensions(): array;

    /**
     * Retrieve the extension to use when a single one has to be chosen
     *
     * @return string
     */
    public function getPrimaryExtension(): string;

    /**
     * Retrieve every MIME type the format is reported as
     *
     * @return string[]
     */
    public function getMimeTypes(): array;

    /**
     * Retrieve the MIME type to use when a single one has to be chosen
     *
     * @return string
     */
    public function getPrimaryMimeType(): string;

    /**
     * Retrieve the matching IMAGETYPE_* constant
     *
     * @return int
     */
    public function getImageType(): int;

    /**
     * Whether the format can carry an alpha channel
     *
     * @return bool
     */
    public function supportsAlpha(): bool;

    /**
     * Whether the format encodes with a lossy 0-100 quality scale
     *
     * @return bool
     */
    public function supportsQuality(): bool;
}
