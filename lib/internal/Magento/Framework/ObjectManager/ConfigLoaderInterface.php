<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\ObjectManager;

/**
 * Interface \Magento\Framework\ObjectManager\ConfigLoaderInterface
 *
 * @api
 */
interface ConfigLoaderInterface
{
    /**
     * When present, names the area the loaded configuration only holds the differences against
     */
    public const EXTENDS_KEY = '_extends';

    /**
     * Load modules DI configuration
     *
     * @param string $area
     * @return array
     */
    public function load($area);
}
