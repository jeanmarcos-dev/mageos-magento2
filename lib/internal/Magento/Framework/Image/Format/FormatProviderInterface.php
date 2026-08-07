<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\Image\Format;

/**
 * Single source of truth for the image formats the framework understands.
 *
 * Adding a format is a matter of registering one more entry through dependency
 * injection; consumers pick it up without further changes.
 *
 * @api
 */
interface FormatProviderInterface
{
    /**
     * Retrieve every registered format, keyed by name
     *
     * @return FormatInterface[]
     */
    public function getAll(): array;

    /**
     * Retrieve a format by its identifier
     *
     * @param string $name
     * @return FormatInterface|null
     */
    public function get(string $name): ?FormatInterface;

    /**
     * Resolve a format from a file extension, with or without a leading dot
     *
     * @param string $extension
     * @return FormatInterface|null
     */
    public function getByExtension(string $extension): ?FormatInterface;

    /**
     * Resolve a format from a MIME type
     *
     * @param string $mimeType
     * @return FormatInterface|null
     */
    public function getByMimeType(string $mimeType): ?FormatInterface;

    /**
     * Resolve a format from an IMAGETYPE_* constant
     *
     * @param int $imageType
     * @return FormatInterface|null
     */
    public function getByImageType(int $imageType): ?FormatInterface;

    /**
     * Retrieve every known file extension across all formats
     *
     * @return string[]
     */
    public function getExtensions(): array;

    /**
     * Retrieve every known MIME type across all formats
     *
     * @return string[]
     */
    public function getMimeTypes(): array;

    /**
     * Retrieve a MIME type to primary extension map
     *
     * @return array<string, string>
     */
    public function getMimeTypeToExtensionMap(): array;
}
