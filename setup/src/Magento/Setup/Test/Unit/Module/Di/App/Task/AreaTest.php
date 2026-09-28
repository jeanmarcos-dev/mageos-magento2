<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Setup\Test\Unit\Module\Di\App\Task;

use Magento\Framework\App;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\ObjectManager\ConfigLoader\Compiled as CompiledConfigLoader;
use Magento\Framework\App\ObjectManager\ConfigWriterInterface;
use Magento\Setup\Module\Di\App\Task\Operation\Area;
use Magento\Setup\Module\Di\Compiler\Config;
use Magento\Setup\Module\Di\Compiler\Config\ModificationChain;
use Magento\Setup\Module\Di\Compiler\Config\Reader;
use Magento\Setup\Module\Di\Definition\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AreaTest extends TestCase
{
    /**
     * @var App\AreaList|MockObject
     */
    private $areaListMock;

    /**
     * @var \Magento\Setup\Module\Di\Code\Reader\Decorator\Area|MockObject
     */
    private $areaInstancesNamesList;

    /**
     * @var Config\Reader|MockObject
     */
    private $configReaderMock;

    /**
     * @var Config\WriterInterface|MockObject
     */
    private $configWriterMock;

    /**
     * @var ModificationChain|MockObject
     */
    private $configChain;

    protected function setUp(): void
    {
        $this->areaListMock = $this->getMockBuilder(AreaList::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->areaInstancesNamesList =
            $this->getMockBuilder(\Magento\Setup\Module\Di\Code\Reader\Decorator\Area::class)
                ->disableOriginalConstructor()
                ->getMock();
        $this->configReaderMock = $this->getMockBuilder(Reader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configWriterMock =
            $this->getMockBuilder(ConfigWriterInterface::class)
                ->disableOriginalConstructor()
                ->getMock();
        $this->configChain = $this->getMockBuilder(ModificationChain::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testDoOperationEmptyPath()
    {
        $areaOperation = new Area(
            $this->areaListMock,
            $this->areaInstancesNamesList,
            $this->configReaderMock,
            $this->configWriterMock,
            $this->configChain
        );

        $this->assertNull($areaOperation->doOperation());
    }

    public function testDoOperationGlobalArea()
    {
        $path = 'path/to/codebase/';
        $arguments = ['class' => []];
        $generatedConfig = [
            'arguments' => $arguments,
            'preferences' => [],
            'instanceTypes' => []
        ];

        $areaOperation = new Area(
            $this->areaListMock,
            $this->areaInstancesNamesList,
            $this->configReaderMock,
            $this->configWriterMock,
            $this->configChain,
            [$path]
        );

        $this->areaListMock->expects($this->once())
            ->method('getCodes')
            ->willReturn([]);
        $this->areaInstancesNamesList->expects($this->once())
            ->method('getList')
            ->with($path)
            ->willReturn($arguments);
        $this->configReaderMock->expects($this->once())
            ->method('generateCachePerScope')
            ->with(
                $this->isInstanceOf(Collection::class),
                App\Area::AREA_GLOBAL
            )
            ->willReturn($generatedConfig);
        $this->configChain->expects($this->once())
            ->method('modify')
            ->with($generatedConfig)
            ->willReturn($generatedConfig);

        $this->configWriterMock->expects($this->once())
            ->method('write')
            ->with(
                App\Area::AREA_GLOBAL,
                $generatedConfig
            );

        $areaOperation->doOperation();
    }

    public function testDoOperationWritesOnlyTheDifferencesOfNonGlobalAreas()
    {
        $globalConfig = [
            'arguments' => [
                'Overridden' => ['b' => 2],
                'Shared' => ['a' => 1],
            ],
            'preferences' => ['SomeInterface' => 'GlobalImplementation'],
            'instanceTypes' => ['globalVirtual' => 'GlobalType'],
        ];
        $frontendConfig = [
            'arguments' => [
                'FrontendOnly' => ['c' => 3],
                'Overridden' => ['b' => 'frontend'],
                'Shared' => ['a' => 1],
            ],
            'preferences' => ['SomeInterface' => 'FrontendImplementation'],
            'instanceTypes' => ['globalVirtual' => 'GlobalType'],
        ];

        $written = $this->compileGlobalAndFrontend($globalConfig, $frontendConfig);

        $this->assertSame($globalConfig, $written[App\Area::AREA_GLOBAL]);
        $this->assertSame(
            [
                CompiledConfigLoader::EXTENDS_KEY => App\Area::AREA_GLOBAL,
                'arguments' => [
                    'FrontendOnly' => ['c' => 3],
                    'Overridden' => ['b' => 'frontend'],
                ],
                'preferences' => ['SomeInterface' => 'FrontendImplementation'],
                'instanceTypes' => [],
            ],
            $written[App\Area::AREA_FRONTEND]
        );
        $this->assertFrontendLoadsBackAs($frontendConfig, $written);
    }

    public function testDoOperationWritesTheWholeConfigurationOfAnAreaThatDropsAGlobalKey()
    {
        $frontendConfig = self::sections(['lazyTypes' => ['Shared' => true, 'FrontendOnly' => true]]);

        $written = $this->compileGlobalAndFrontend(
            self::sections(['lazyTypes' => ['GlobalOnly' => true, 'Shared' => true]]),
            $frontendConfig
        );

        $this->assertSame($frontendConfig, $written[App\Area::AREA_FRONTEND]);
        $this->assertFrontendLoadsBackAs($frontendConfig, $written);
    }

    public function testDoOperationWritesTheWholeConfigurationOfAnAreaThatDropsAGlobalSection()
    {
        $frontendConfig = self::sections([]);

        $written = $this->compileGlobalAndFrontend(
            self::sections(['lazyTypes' => ['Shared' => true]]),
            $frontendConfig
        );

        $this->assertSame($frontendConfig, $written[App\Area::AREA_FRONTEND]);
        $this->assertFrontendLoadsBackAs($frontendConfig, $written);
    }

    public function testDoOperationWritesTheWholeSectionWhenItIsNotAnArrayInGlobal()
    {
        $frontendConfig = self::sections(['flags' => ['frontend' => true]]);

        $written = $this->compileGlobalAndFrontend(self::sections(['flags' => 'global']), $frontendConfig);

        $this->assertSame(['frontend' => true], $written[App\Area::AREA_FRONTEND]['flags']);
        $this->assertFrontendLoadsBackAs($frontendConfig, $written);
    }

    /**
     * Completes a configuration with the sections the operation always sorts
     *
     * @param array $sections
     * @return array
     */
    private static function sections(array $sections): array
    {
        return $sections + ['arguments' => [], 'preferences' => [], 'instanceTypes' => []];
    }

    /**
     * Runs the operation for the global and frontend areas and returns what it writes, per area
     *
     * @param array $globalConfig
     * @param array $frontendConfig
     * @return array
     */
    private function compileGlobalAndFrontend(array $globalConfig, array $frontendConfig): array
    {
        $path = 'path/to/codebase/';

        $areaOperation = new Area(
            $this->areaListMock,
            $this->areaInstancesNamesList,
            $this->configReaderMock,
            $this->configWriterMock,
            $this->configChain,
            [$path]
        );

        $this->areaListMock->expects($this->once())
            ->method('getCodes')
            ->willReturn([App\Area::AREA_FRONTEND]);
        $this->areaInstancesNamesList->expects($this->once())
            ->method('getList')
            ->with($path)
            ->willReturn([]);
        $this->configReaderMock->expects($this->exactly(2))
            ->method('generateCachePerScope')
            ->willReturn($globalConfig, $frontendConfig);
        $this->configChain->expects($this->exactly(2))->method('modify')->willReturnArgument(0);

        $written = [];
        $this->configWriterMock->expects($this->exactly(2))
            ->method('write')
            ->willReturnCallback(function ($areaCode, $config) use (&$written) {
                $written[$areaCode] = $config;
            });

        $areaOperation->doOperation();

        return $written;
    }

    /**
     * Asserts that the written frontend area loads back, through the compiled loader, as the given configuration
     *
     * @param array $expected
     * @param array $written
     * @return void
     */
    private function assertFrontendLoadsBackAs(array $expected, array $written): void
    {
        $this->assertEquals(
            $expected,
            $this->loadThroughTheCompiledLoader($written[App\Area::AREA_GLOBAL], $written[App\Area::AREA_FRONTEND])
        );
    }

    /**
     * Loads a written area through the compiled loader, with its global base stored under a unique name
     *
     * @param array $writtenGlobal
     * @param array $writtenArea
     * @return array
     */
    private function loadThroughTheCompiledLoader(array $writtenGlobal, array $writtenArea): array
    {
        $base = 'zz_global_' . uniqid();
        $area = 'zz_frontend_' . uniqid();
        $metadataDir = dirname(CompiledConfigLoader::getFilePath($base));
        $createdDirs = [];
        foreach ([dirname($metadataDir), $metadataDir] as $dir) {
            if (!is_dir($dir) && mkdir($dir, 0777, true)) {
                $createdDirs[] = $dir;
            }
        }

        if (isset($writtenArea[CompiledConfigLoader::EXTENDS_KEY])) {
            $writtenArea[CompiledConfigLoader::EXTENDS_KEY] = $base;
        }
        $files = [
            CompiledConfigLoader::getFilePath($base) => $writtenGlobal,
            CompiledConfigLoader::getFilePath($area) => $writtenArea,
        ];

        try {
            foreach ($files as $file => $configuration) {
                file_put_contents($file, '<?php return ' . var_export($configuration, true) . ';');
            }

            return (new CompiledConfigLoader())->load($area);
        } finally {
            foreach (array_keys($files) as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            foreach (array_reverse($createdDirs) as $dir) {
                rmdir($dir);
            }
        }
    }
}
