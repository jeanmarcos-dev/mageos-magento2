<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\App\Test\Unit\ObjectManager\ConfigLoader;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager\ConfigLoader\Compiled;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use PHPUnit\Framework\TestCase;

class CompiledTest extends TestCase
{
    /**
     * @var string
     */
    private $metadataDir;

    /**
     * @var string[]
     */
    private $writtenFiles = [];

    /**
     * @var string[]
     */
    private $createdDirs = [];

    protected function setUp(): void
    {
        $this->metadataDir = BP . '/'
            . DirectoryList::getDefaultConfig()[DirectoryList::GENERATED_METADATA][DirectoryList::PATH];

        foreach ([dirname($this->metadataDir), $this->metadataDir] as $dir) {
            if (!is_dir($dir) && mkdir($dir, 0777, true)) {
                $this->createdDirs[] = $dir;
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach (array_reverse($this->createdDirs) as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    /**
     * Writes a compiled configuration where the loader looks for it
     *
     * @param string $area
     * @param array $configuration
     * @return void
     */
    private function writeCompiledFile($area, array $configuration)
    {
        $file = Compiled::getFilePath($area);
        file_put_contents($file, '<?php return ' . var_export($configuration, true) . ';');
        $this->writtenFiles[] = $file;
    }

    /**
     * @param array $files
     * @return Compiled
     */
    private function loaderFor(array $files)
    {
        $loader = $this->getMockBuilder(Compiled::class)
            ->onlyMethods(['loadFile'])
            ->getMock();
        $loader->method('loadFile')->willReturnCallback(
            static fn ($area) => $files[$area]
        );

        return $loader;
    }

    public function testLoadReturnsAConfigurationWithoutMarkerUnchanged(): void
    {
        $global = [
            'arguments' => ['Type' => ['a' => 1]],
            'preferences' => ['Interface' => 'Implementation'],
        ];

        $this->assertSame($global, $this->loaderFor(['global' => $global])->load('global'));
    }

    public function testLoadResolvesADeltaAgainstTheAreaItExtends(): void
    {
        $loader = $this->loaderFor([
            'global' => [
                'arguments' => ['Shared' => ['a' => 1], 'Overridden' => ['b' => 2]],
                'preferences' => ['Interface' => 'GlobalImplementation'],
                'instanceTypes' => ['virtual' => 'GlobalType'],
            ],
            'frontend' => [
                ConfigLoaderInterface::EXTENDS_KEY => 'global',
                'arguments' => ['Overridden' => ['b' => 'frontend'], 'FrontendOnly' => ['c' => 3]],
                'preferences' => ['Interface' => 'FrontendImplementation'],
                'instanceTypes' => [],
            ],
        ]);

        $this->assertSame(
            [
                'arguments' => [
                    'Shared' => ['a' => 1],
                    'Overridden' => ['b' => 'frontend'],
                    'FrontendOnly' => ['c' => 3],
                ],
                'preferences' => ['Interface' => 'FrontendImplementation'],
                'instanceTypes' => ['virtual' => 'GlobalType'],
            ],
            $loader->load('frontend')
        );
    }

    public function testLoadMergesASectionTheBaseDoesNotKnow(): void
    {
        $loader = $this->loaderFor([
            'global' => ['arguments' => []],
            'frontend' => [
                ConfigLoaderInterface::EXTENDS_KEY => 'global',
                'arguments' => [],
                'sectionAddedLater' => ['key' => 'areaValue'],
            ],
        ]);

        $this->assertSame(['key' => 'areaValue'], $loader->load('frontend')['sectionAddedLater']);
    }

    public function testLoadKeepsOverridesToFalsyValues(): void
    {
        $loader = $this->loaderFor([
            'global' => ['arguments' => ['Type' => 'value']],
            'frontend' => [
                ConfigLoaderInterface::EXTENDS_KEY => 'global',
                'arguments' => ['Type' => ''],
            ],
        ]);

        $this->assertSame(['Type' => ''], $loader->load('frontend')['arguments']);
    }

    public function testLoadDoesNotCacheTheResolvedConfiguration(): void
    {
        $global = ['arguments' => ['Shared' => 1]];
        $frontend = [
            ConfigLoaderInterface::EXTENDS_KEY => 'global',
            'arguments' => ['Own' => 2],
        ];

        $loader = $this->getMockBuilder(Compiled::class)
            ->onlyMethods(['loadFile'])
            ->getMock();
        $loader->expects($this->exactly(4))
            ->method('loadFile')
            ->willReturnCallback(
                static fn ($area) => $area === 'global' ? $global : $frontend
            );

        $this->assertSame($loader->load('frontend'), $loader->load('frontend'));
    }

    public function testLoadResolvesADeltaReadFromDiskAndLeavesItsCacheIntact(): void
    {
        $base = 'zz_base_' . uniqid();
        $area = 'zz_area_' . uniqid();
        $this->writeCompiledFile($base, ['arguments' => ['Shared' => 1, 'Overridden' => 2]]);
        $this->writeCompiledFile($area, [
            ConfigLoaderInterface::EXTENDS_KEY => $base,
            'arguments' => ['Overridden' => 'fromArea'],
        ]);

        $loader = new Compiled();
        $expected = ['arguments' => ['Shared' => 1, 'Overridden' => 'fromArea']];

        $this->assertSame($expected, $loader->load($area));
        $this->assertSame(
            $expected,
            $loader->load($area),
            'dropping the marker must not mutate the cached contents of the file'
        );
    }
}
