<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Catalog\Model\Product\Image;

use Magento\Framework\Image\Adapter\OutputFormatAwareInterface;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\Image\Format\FormatProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Writes extra copies next to a cached catalog image, as "<image>.<format>".
 *
 * Keeping the original name as a prefix lets a web server hand a copy to clients whose Accept
 * header advertises support and fall back to the original for everyone else, without the
 * storefront having to know which copies exist.
 */
class VariantGenerator
{
    /**
     * @var AdapterFactory
     */
    private $adapterFactory;

    /**
     * @var VariantConfig
     */
    private $config;

    /**
     * @var FormatProviderInterface
     */
    private $formatProvider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param AdapterFactory $adapterFactory
     * @param VariantConfig $config
     * @param FormatProviderInterface $formatProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        AdapterFactory $adapterFactory,
        VariantConfig $config,
        FormatProviderInterface $formatProvider,
        LoggerInterface $logger
    ) {
        $this->adapterFactory = $adapterFactory;
        $this->config = $config;
        $this->formatProvider = $formatProvider;
        $this->logger = $logger;
    }

    /**
     * Generate every configured copy of an already written image
     *
     * @param string|null $imagePath Absolute path of the source image
     * @return string[] Absolute paths of the copies that were written
     */
    public function execute(?string $imagePath): array
    {
        if ($imagePath === null || $imagePath === '') {
            return [];
        }

        $formats = $this->config->getEnabledFormats();
        if (empty($formats)) {
            return [];
        }

        $sourceExtension = strtolower((string) pathinfo($imagePath, PATHINFO_EXTENSION));
        $written = [];

        foreach ($formats as $format) {
            // Re-encoding a file into the format it already is would only duplicate it.
            if ($sourceExtension === $format || $this->formatProvider->get($format) === null) {
                continue;
            }

            $variantPath = $this->write($imagePath, $format);
            if ($variantPath !== null) {
                $written[] = $variantPath;
            }
        }

        return $written;
    }

    /**
     * Encode a single copy, reporting failures without interrupting the caller
     *
     * @param string $imagePath
     * @param string $format
     * @return string|null
     */
    private function write(string $imagePath, string $format): ?string
    {
        $variantPath = $imagePath . '.' . $format;

        try {
            $adapter = $this->adapterFactory->create();
            if (!$adapter instanceof OutputFormatAwareInterface) {
                return null;
            }

            $adapter->open($imagePath);
            $adapter->setOutputFormat($format);
            $adapter->quality($this->config->getQuality($format));
            $adapter->save($variantPath);
        } catch (\Throwable $e) {
            // A build without support for the format, or an image the encoder rejects, must not
            // fail the resize of the original.
            $this->logger->warning(
                sprintf(
                    'Could not generate the %s variant of "%s": %s',
                    strtoupper($format),
                    $imagePath,
                    $e->getMessage()
                )
            );

            return null;
        }

        return $variantPath;
    }
}
