<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
namespace Magento\Catalog\Model\Product\Gallery;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Image\Format\FormatProviderInterface;

class MimeTypeExtensionMap
{
    /**
     * Extra MIME type to extension pairs, merged on top of the ones the format registry knows.
     *
     * @var array
     */
    protected $mimeTypeExtensionMap = [];

    /**
     * @param FormatProviderInterface|null $formatProvider
     */
    public function __construct(?FormatProviderInterface $formatProvider = null)
    {
        $formatProvider = $formatProvider ?: ObjectManager::getInstance()->get(FormatProviderInterface::class);
        // Subclass overrides of $mimeTypeExtensionMap stay authoritative over the registry defaults.
        $this->mimeTypeExtensionMap = array_merge(
            $formatProvider->getMimeTypeToExtensionMap(),
            $this->mimeTypeExtensionMap
        );
    }

    /**
     * Resolve extension from a MIME type.
     *
     * @param string $mimeType
     * @return string
     */
    public function getMimeTypeExtension($mimeType)
    {
        if ($mimeType !==null && isset($this->mimeTypeExtensionMap[$mimeType])) {
            return $this->mimeTypeExtensionMap[$mimeType];
        } else {
            return "";
        }
    }
}
