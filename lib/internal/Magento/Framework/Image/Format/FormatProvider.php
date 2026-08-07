<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\Image\Format;

/**
 * Dependency injection driven registry of image formats.
 */
class FormatProvider implements FormatProviderInterface
{
    /**
     * @var FormatInterface[]
     */
    private $formats = [];

    /**
     * @var array<string, FormatInterface>
     */
    private $byExtension = [];

    /**
     * @var array<string, FormatInterface>
     */
    private $byMimeType = [];

    /**
     * @var array<int, FormatInterface>
     */
    private $byImageType = [];

    /**
     * @param FormatInterface[] $formats
     * @throws \InvalidArgumentException
     */
    public function __construct(array $formats = [])
    {
        foreach (array_filter($formats) as $key => $format) {
            if (!$format instanceof FormatInterface) {
                throw new \InvalidArgumentException(
                    sprintf('Image format "%s" must implement %s.', $key, FormatInterface::class)
                );
            }

            $this->formats[$format->getName()] = $format;

            foreach ($format->getExtensions() as $extension) {
                $this->byExtension[$extension] = $format;
            }
            foreach ($format->getMimeTypes() as $mimeType) {
                $this->byMimeType[$mimeType] = $format;
            }
            $this->byImageType[$format->getImageType()] = $format;
        }
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        return $this->formats;
    }

    /**
     * @inheritDoc
     */
    public function get(string $name): ?FormatInterface
    {
        return $this->formats[strtolower($name)] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getByExtension(string $extension): ?FormatInterface
    {
        return $this->byExtension[strtolower(ltrim($extension, '.'))] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getByMimeType(string $mimeType): ?FormatInterface
    {
        return $this->byMimeType[strtolower($mimeType)] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getByImageType(int $imageType): ?FormatInterface
    {
        return $this->byImageType[$imageType] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getExtensions(): array
    {
        return array_keys($this->byExtension);
    }

    /**
     * @inheritDoc
     */
    public function getMimeTypes(): array
    {
        return array_keys($this->byMimeType);
    }

    /**
     * @inheritDoc
     */
    public function getMimeTypeToExtensionMap(): array
    {
        $map = [];
        foreach ($this->byMimeType as $mimeType => $format) {
            $map[$mimeType] = $format->getPrimaryExtension();
        }

        return $map;
    }
}
