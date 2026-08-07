<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\Image\Format;

/**
 * Immutable description of a single image format.
 */
class Format implements FormatInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string[]
     */
    private $extensions;

    /**
     * @var string[]
     */
    private $mimeTypes;

    /**
     * @var int
     */
    private $imageType;

    /**
     * @var bool
     */
    private $supportsAlpha;

    /**
     * @var bool
     */
    private $supportsQuality;

    /**
     * @param string $name
     * @param string[] $extensions
     * @param string[] $mimeTypes
     * @param int $imageType
     * @param bool $supportsAlpha
     * @param bool $supportsQuality
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $name,
        array $extensions,
        array $mimeTypes,
        int $imageType,
        bool $supportsAlpha = false,
        bool $supportsQuality = false
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('An image format requires a name.');
        }
        if (empty($extensions)) {
            throw new \InvalidArgumentException(sprintf('Image format "%s" requires an extension.', $name));
        }
        if (empty($mimeTypes)) {
            throw new \InvalidArgumentException(sprintf('Image format "%s" requires a MIME type.', $name));
        }

        $this->name = strtolower($name);
        $this->extensions = array_values(array_unique(array_map('strtolower', $extensions)));
        $this->mimeTypes = array_values(array_unique(array_map('strtolower', $mimeTypes)));
        $this->imageType = $imageType;
        $this->supportsAlpha = $supportsAlpha;
        $this->supportsQuality = $supportsQuality;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * @inheritDoc
     */
    public function getPrimaryExtension(): string
    {
        return $this->extensions[0];
    }

    /**
     * @inheritDoc
     */
    public function getMimeTypes(): array
    {
        return $this->mimeTypes;
    }

    /**
     * @inheritDoc
     */
    public function getPrimaryMimeType(): string
    {
        return $this->mimeTypes[0];
    }

    /**
     * @inheritDoc
     */
    public function getImageType(): int
    {
        return $this->imageType;
    }

    /**
     * @inheritDoc
     */
    public function supportsAlpha(): bool
    {
        return $this->supportsAlpha;
    }

    /**
     * @inheritDoc
     */
    public function supportsQuality(): bool
    {
        return $this->supportsQuality;
    }
}
