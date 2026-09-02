<?php
/**
 * Copyright 2014 Adobe
 * All Rights Reserved.
 */
namespace Magento\Framework\App\ObjectManager\ConfigLoader;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;

/**
 * Load configuration files
 */
class Compiled implements ConfigLoaderInterface
{
    /**
     * Global config
     *
     * @var array
     */
    private $configCache = [];

    /**
     * @inheritdoc
     */
    public function load($area)
    {
        $diConfiguration = $this->loadFile($area);
        $base = $diConfiguration[ConfigLoaderInterface::EXTENDS_KEY] ?? null;

        return $base === null ? $diConfiguration : self::merge($this->load($base), $diConfiguration);
    }

    /**
     * Returns the contents of a compiled configuration file
     *
     * @param string $area
     * @return array
     */
    protected function loadFile($area)
    {
        if (!isset($this->configCache[$area])) {
            $this->configCache[$area] = include self::getFilePath($area);
        }

        return $this->configCache[$area];
    }

    /**
     * Applies a configuration on top of another one, per top-level key of every section
     *
     * @param array $base
     * @param array $diConfiguration
     * @return array
     */
    private static function merge(array $base, array $diConfiguration)
    {
        unset($diConfiguration[ConfigLoaderInterface::EXTENDS_KEY]);

        foreach ($diConfiguration as $section => $values) {
            $base[$section] = is_array($values) && is_array($base[$section] ?? null)
                ? array_replace($base[$section], $values)
                : $values;
        }

        return $base;
    }

    /**
     * Returns path to compiled configuration
     *
     * @param string $area
     * @return string
     */
    public static function getFilePath($area)
    {
        $diPath = DirectoryList::getDefaultConfig()[DirectoryList::GENERATED_METADATA][DirectoryList::PATH];
        return BP . '/' . $diPath . '/' . $area . '.php';
    }
}
