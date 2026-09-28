<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\App\Test\Unit\ObjectManager\ConfigLoader;

use Magento\Framework\App\ObjectManager\ConfigLoader\Compiled;
use PHPUnit\Framework\TestCase;

class CompiledTest extends TestCase
{
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
        $metadataDir = dirname(Compiled::getFilePath('any'));

        foreach ([dirname($metadataDir), $metadataDir] as $dir) {
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
     * Writes compiled configurations where the loader looks for them, under unique area names
     *
     * @param array $configurations
     * @return string[]
     */
    private function writeCompiledFiles(array $configurations)
    {
        $areas = [];
        foreach (array_keys($configurations) as $name) {
            $areas[$name] = 'zz_' . $name . '_' . uniqid();
        }

        foreach ($configurations as $name => $configuration) {
            if (isset($configuration[Compiled::EXTENDS_KEY])) {
                $configuration[Compiled::EXTENDS_KEY] = $areas[$configuration[Compiled::EXTENDS_KEY]];
            }
            $file = Compiled::getFilePath($areas[$name]);
            file_put_contents($file, '<?php return ' . var_export($configuration, true) . ';');
            $this->writtenFiles[] = $file;
        }

        return $areas;
    }

    public function testLoadReturnsAConfigurationWithoutMarkerUnchanged(): void
    {
        $global = [
            'arguments' => ['Type' => ['a' => 1]],
            'preferences' => ['Interface' => 'Implementation'],
        ];
        $areas = $this->writeCompiledFiles(['global' => $global]);

        $this->assertSame($global, (new Compiled())->load($areas['global']));
    }

    public function testLoadResolvesADeltaAgainstTheAreaItExtends(): void
    {
        $areas = $this->writeCompiledFiles([
            'global' => [
                'arguments' => ['Shared' => ['a' => 1], 'Overridden' => ['b' => 2]],
                'preferences' => ['Interface' => 'GlobalImplementation'],
                'instanceTypes' => ['virtual' => 'GlobalType'],
            ],
            'frontend' => [
                Compiled::EXTENDS_KEY => 'global',
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
            (new Compiled())->load($areas['frontend'])
        );
    }

    public function testLoadMergesASectionTheBaseDoesNotKnow(): void
    {
        $areas = $this->writeCompiledFiles([
            'global' => ['arguments' => []],
            'frontend' => [
                Compiled::EXTENDS_KEY => 'global',
                'arguments' => [],
                'sectionAddedLater' => ['key' => 'areaValue'],
            ],
        ]);

        $this->assertSame(
            ['key' => 'areaValue'],
            (new Compiled())->load($areas['frontend'])['sectionAddedLater']
        );
    }

    public function testLoadKeepsOverridesToFalsyValues(): void
    {
        $areas = $this->writeCompiledFiles([
            'global' => ['arguments' => ['Type' => 'value']],
            'frontend' => [
                Compiled::EXTENDS_KEY => 'global',
                'arguments' => ['Type' => ''],
            ],
        ]);

        $this->assertSame(['Type' => ''], (new Compiled())->load($areas['frontend'])['arguments']);
    }

    public function testLoadingADeltaLeavesItAndItsBaseUnchangedForLaterLoads(): void
    {
        $global = ['arguments' => ['Shared' => 1, 'Overridden' => 2]];
        $areas = $this->writeCompiledFiles([
            'global' => $global,
            'frontend' => [
                Compiled::EXTENDS_KEY => 'global',
                'arguments' => ['Overridden' => 'fromArea'],
            ],
        ]);
        $loader = new Compiled();
        $expected = ['arguments' => ['Shared' => 1, 'Overridden' => 'fromArea']];

        $this->assertSame($expected, $loader->load($areas['frontend']));
        $this->assertSame($expected, $loader->load($areas['frontend']));
        $this->assertSame($global, $loader->load($areas['global']));
    }

    public function testLoadFailsWhenTheBaseIsItselfADelta(): void
    {
        $areas = $this->writeCompiledFiles([
            'global' => ['arguments' => []],
            'frontend' => [Compiled::EXTENDS_KEY => 'global', 'arguments' => []],
            'custom' => [Compiled::EXTENDS_KEY => 'frontend', 'arguments' => []],
        ]);

        $this->expectException(\LogicException::class);

        (new Compiled())->load($areas['custom']);
    }

    public function testLoadFailsOnADeltaThatExtendsItself(): void
    {
        $areas = $this->writeCompiledFiles([
            'frontend' => [Compiled::EXTENDS_KEY => 'frontend', 'arguments' => []],
        ]);

        $this->expectException(\LogicException::class);

        (new Compiled())->load($areas['frontend']);
    }
}
