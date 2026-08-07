<?php
/**
 * Copyright 2026 Mage-OS
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Catalog\Model\Product\Image;

use Magento\Framework\Filesystem\Io\File as IoFile;
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
     * @var IoFile
     */
    private $ioFile;

    /**
     * Formats already reported as unencodable, so the warning is logged once rather than per image
     *
     * @var array<string, true>
     */
    private $unsupportedFormats = [];

    /**
     * @param AdapterFactory $adapterFactory
     * @param VariantConfig $config
     * @param FormatProviderInterface $formatProvider
     * @param LoggerInterface $logger
     * @param IoFile $ioFile
     */
    public function __construct(
        AdapterFactory $adapterFactory,
        VariantConfig $config,
        FormatProviderInterface $formatProvider,
        LoggerInterface $logger,
        IoFile $ioFile
    ) {
        $this->adapterFactory = $adapterFactory;
        $this->config = $config;
        $this->formatProvider = $formatProvider;
        $this->logger = $logger;
        $this->ioFile = $ioFile;
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

        $sourceExtension = strtolower((string) ($this->ioFile->getPathInfo($imagePath)['extension'] ?? ''));
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

            // Opening the source decodes it in full, so ask first: a build without the codec would
            // otherwise pay that decode for every image only to fail on save.
            if (!$adapter->supportsOutputFormat($format)) {
                $this->reportUnsupported($format);

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

    /**
     * Warn once per format that this installation cannot encode it
     *
     * @param string $format
     * @return void
     */
    private function reportUnsupported(string $format): void
    {
        if (isset($this->unsupportedFormats[$format])) {
            return;
        }
        $this->unsupportedFormats[$format] = true;

        $this->logger->warning(
            sprintf(
                'The configured image adapter cannot encode %s, so no %s copies will be generated.',
                strtoupper($format),
                strtoupper($format)
            )
        );
    }
}
