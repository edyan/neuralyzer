<?php
/**
 * Inet Data Anonymization
 *
 * PHP Version 5.3 -> 7.0
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2005-2015 iNet Process
 *
 * @package inetprocess/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link http://www.inetprocess.com
 */

namespace Inet\Neuralyzer\Configuration;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration Reader
 */
class Reader
{
    /**
     * Configuration file name
     *
     * @var string
     */
    protected $configFileName;

    /**
     * Define the directories to search in. Only the first file found is taken into account
     * Can be defined via the constructor
     *
     * @var array
     */
    protected $configDirectories;

    /**
     * Configuration file name
     *
     * @var string|array
     */
    protected $configFilePath;

    /**
     * Stores the config values
     *
     * @var array
     */
    protected $configValues = array();

    /**
     * Constructor
     *
     * @param string $configFileName
     * @param array  $configDirectories
     */
    public function __construct($configFileName, array $configDirectories = array('.'))
    {
        $this->configFileName = $configFileName;
        $this->configDirectories = $configDirectories;

        $locator = new FileLocator($this->configDirectories);
        $this->configFilePath = $locator->locate($this->configFileName);

        $this->parseAndValidateConfig();
    }

    /**
     * Getter
     *
     * @return array Config Values
     */
    public function getConfigValues()
    {
        return $this->configValues;
    }

    /**
     * Return the list of entites
     *
     * @return array
     */
    public function getEntities()
    {
        return array_keys($this->configValues['entities']);
    }

    /**
     * Parse and validate the configuration
     */
    protected function parseAndValidateConfig()
    {
        $this->configValues = Yaml::parse(file_get_contents($this->configFilePath));

        $processor = new Processor();
        $configuration = new AnonConfiguration();
        $processor->processConfiguration($configuration, array($this->configValues));
    }
}
