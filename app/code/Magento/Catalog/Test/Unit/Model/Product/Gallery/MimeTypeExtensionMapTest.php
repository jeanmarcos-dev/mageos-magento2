<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Catalog\Test\Unit\Model\Product\Gallery;

use Magento\Catalog\Model\Product\Gallery\MimeTypeExtensionMap;
use Magento\Framework\Image\Format\Format;
use Magento\Framework\Image\Format\FormatProvider;
use PHPUnit\Framework\TestCase;

class MimeTypeExtensionMapTest extends TestCase
{
    /**
     * @var MimeTypeExtensionMap
     */
    protected $model;

    protected function setUp(): void
    {
        $this->model = new MimeTypeExtensionMap(
            new FormatProvider(
                [
                    new Format('jpeg', ['jpg', 'jpeg'], ['image/jpeg', 'image/jpg'], IMAGETYPE_JPEG, false, true),
                    new Format('png', ['png'], ['image/png'], IMAGETYPE_PNG, true, false),
                    new Format('gif', ['gif'], ['image/gif'], IMAGETYPE_GIF, true, false),
                    new Format('webp', ['webp'], ['image/webp'], IMAGETYPE_WEBP, true, true),
                    new Format('avif', ['avif'], ['image/avif'], IMAGETYPE_AVIF, true, true),
                ]
            )
        );
    }

    public function testGetMimeTypeExtension()
    {
        $this->assertEquals("jpg", $this->model->getMimeTypeExtension("image/jpeg"));
        $this->assertEquals("jpg", $this->model->getMimeTypeExtension("image/jpg"));
        $this->assertEquals("png", $this->model->getMimeTypeExtension("image/png"));
        $this->assertEquals("gif", $this->model->getMimeTypeExtension("image/gif"));
        $this->assertEquals("webp", $this->model->getMimeTypeExtension("image/webp"));
        $this->assertEquals("avif", $this->model->getMimeTypeExtension("image/avif"));
        $this->assertEquals("", $this->model->getMimeTypeExtension("unknown"));
        $this->assertEquals("", $this->model->getMimeTypeExtension(null));
    }
}
