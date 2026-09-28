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
    public const EXTENDS_KEY = '_extends';

    /**
     * Global config
     *
     * @var array
     */
    private $configCache = [];

    /**
     * @inheritdoc
     * @throws \LogicException
     */
    public function load($area)
    {
        $diConfiguration = $this->loadFile($area);
        $base = $diConfiguration[self::EXTENDS_KEY] ?? null;
        if ($base === null) {
            return $diConfiguration;
        }

        $baseConfiguration = $this->loadFile($base);
        if (isset($baseConfiguration[self::EXTENDS_KEY])) {
            throw new \LogicException(sprintf(
                'The compiled DI configuration of "%s" extends "%s", which is not a complete configuration.',
                $area,
                $base
            ));
        }

        return self::merge($baseConfiguration, $diConfiguration);
    }

    /**
     * Returns the contents of a compiled configuration file
     *
     * @param string $area
     * @return array
     */
    private function loadFile($area)
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
        unset($diConfiguration[self::EXTENDS_KEY]);

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
