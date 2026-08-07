<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
namespace Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend;

use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Image\Format\FormatProviderInterface;

/**
 * Product image attribute backend
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Image extends AbstractBackend
{
    /**
     * Filesystem facade
     *
     * @var \Magento\Framework\Filesystem
     */
    protected $_filesystem;

    /**
     * File Uploader factory
     *
     * @var \Magento\MediaStorage\Model\File\UploaderFactory
     */
    protected $_fileUploaderFactory;

    /**
     * @var FormatProviderInterface
     */
    private $formatProvider;

    /**
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\MediaStorage\Model\File\UploaderFactory $fileUploaderFactory
     * @param FormatProviderInterface|null $formatProvider
     */
    public function __construct(
        \Magento\Framework\Filesystem $filesystem,
        \Magento\MediaStorage\Model\File\UploaderFactory $fileUploaderFactory,
        ?FormatProviderInterface $formatProvider = null
    ) {
        $this->formatProvider = $formatProvider
            ?: ObjectManager::getInstance()->get(FormatProviderInterface::class);
        $this->_filesystem = $filesystem;
        $this->_fileUploaderFactory = $fileUploaderFactory;
    }

    /**
     * After save
     *
     * @param \Magento\Framework\DataObject $object
     * @return $this|void
     */
    public function afterSave($object)
    {
        $value = $object->getData($this->getAttribute()->getName());

        if (is_array($value) && !empty($value['delete'])) {
            $object->setData($this->getAttribute()->getName(), '');
            $this->getAttribute()->getEntity()->saveAttribute($object, $this->getAttribute()->getName());
            return;
        }

        try {
            /** @var $uploader \Magento\MediaStorage\Model\File\Uploader */
            $uploader = $this->_fileUploaderFactory->create(['fileId' => $this->getAttribute()->getName()]);
            $uploader->setAllowedExtensions($this->formatProvider->getExtensions());
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
        } catch (\Exception $e) {
            return $this;
        }
        $path = $this->_filesystem->getDirectoryRead(
            DirectoryList::MEDIA
        )->getAbsolutePath(
            'catalog/product/'
        );
        $uploader->save($path);

        $fileName = $uploader->getUploadedFileName();
        if ($fileName) {
            $object->setData($this->getAttribute()->getName(), $fileName);
            $this->getAttribute()->getEntity()->saveAttribute($object, $this->getAttribute()->getName());
        }
        return $this;
    }
}
