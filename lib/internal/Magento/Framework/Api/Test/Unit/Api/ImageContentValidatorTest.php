<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\Api\Test\Unit\Api;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageContentValidator;
use Magento\Framework\Image\Format\Format;
use Magento\Framework\Image\Format\FormatProvider;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit test class for \Magento\Framework\Api\ImageContentValidator
 */
class ImageContentValidatorTest extends TestCase
{
    /**
     * @var ImageContentValidator
     */
    protected $imageContentValidator;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        $this->imageContentValidator = $this->objectManager->getObject(
            ImageContentValidator::class,
            [
                'formatProvider' => new FormatProvider(
                    [
                        new Format('jpeg', ['jpg', 'jpeg'], ['image/jpeg', 'image/jpg'], IMAGETYPE_JPEG, false, true),
                        new Format('png', ['png'], ['image/png'], IMAGETYPE_PNG, true, false),
                        new Format('gif', ['gif'], ['image/gif'], IMAGETYPE_GIF, true, false),
                        new Format('webp', ['webp'], ['image/webp'], IMAGETYPE_WEBP, true, true),
                        new Format('avif', ['avif'], ['image/avif'], IMAGETYPE_AVIF, true, true),
                    ]
                ),
            ]
        );
    }

    public function testIsValidEmptyContent()
    {
        $this->expectException('Magento\Framework\Exception\InputException');
        $this->expectExceptionMessage('The image content must be valid base64 encoded data.');
        $imageContent = $this->getMockBuilder(ImageContentInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $imageContent->expects($this->any())
            ->method('getBase64EncodedData')
            ->willReturn('');

        $this->imageContentValidator->isValid($imageContent);
    }

    public function testIsValidEmptyProperties()
    {
        $this->expectException('Magento\Framework\Exception\InputException');
        $this->expectExceptionMessage('The image content must be valid base64 encoded data.');
        $imageContent = $this->getMockBuilder(ImageContentInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $imageContent->expects($this->any())
            ->method('getBase64EncodedData')
            ->willReturn('testImageData');

        $this->imageContentValidator->isValid($imageContent);
    }

    public function testIsValidInvalidMIMEType()
    {
        $this->expectException('Magento\Framework\Exception\InputException');
        $this->expectExceptionMessage('The image MIME type is not valid or not supported.');
        $pathToImageFile = __DIR__ . '/_files/image.jpg';
        $encodedData = @base64_encode(file_get_contents($pathToImageFile));

        $imageContent = $this->getMockBuilder(ImageContentInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $imageContent->expects($this->any())
            ->method('getBase64EncodedData')
            ->willReturn($encodedData);
        $imageContent->expects($this->any())
            ->method('getType')
            ->willReturn('invalidType');

        $this->imageContentValidator->isValid($imageContent);
    }

    public function testIsValidInvalidName()
    {
        $this->expectException('Magento\Framework\Exception\InputException');
        $this->expectExceptionMessage('Provided image name contains forbidden characters.');
        $pathToImageFile = __DIR__ . '/_files/image.jpg';
        $encodedData = @base64_encode(file_get_contents($pathToImageFile));

        $imageContent = $this->getMockBuilder(ImageContentInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $imageContent->expects($this->any())
            ->method('getBase64EncodedData')
            ->willReturn($encodedData);
        $imageContent->expects($this->any())
            ->method('getName')
            ->willReturn('invalid:Name');
        $imageContent->expects($this->any())
            ->method('getType')
            ->willReturn('image/jpeg');

        $this->imageContentValidator->isValid($imageContent);
    }

    public function testIsValid()
    {
        $pathToImageFile = __DIR__ . '/_files/image.jpg';
        $encodedData = @base64_encode(file_get_contents($pathToImageFile));

        $imageContent = $this->getMockBuilder(ImageContentInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $imageContent->expects($this->any())
            ->method('getBase64EncodedData')
            ->willReturn($encodedData);
        $imageContent->expects($this->any())
            ->method('getName')
            ->willReturn('validName');
        $imageContent->expects($this->any())
            ->method('getType')
            ->willReturn('image/jpeg');

        $this->assertTrue($this->imageContentValidator->isValid($imageContent));
    }
}
