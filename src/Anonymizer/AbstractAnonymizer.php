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

namespace Inet\Neuralyzer\Anonymizer;

use Inet\Neuralyzer\Configuration\Reader;
use Inet\Neuralyzer\Exception\InetAnonConfigurationException;

/**
 * Abstract Anonymizer
 */
abstract class AbstractAnonymizer
{
    /**
     * Contain the configuration object
     *
     * @var Reader
     */
    protected $configuration;

    /**
     * Configuration of entities
     *
     * @var array
     */
    protected $configEntites = array();

    /**
     * Process the entity according to the anonymizer type
     *
     * @param string        $entity
     * @param callable|null $callback
     * @param bool          $pretend
     * @param bool          $returnResult
     */
    abstract public function processEntity($entity, $callback = null, $pretend = true, $returnResult = false);

    /**
     * Set the configuration
     *
     * @param Reader $configuration
     */
    public function setConfiguration(Reader $configuration)
    {
        $this->configuration = $configuration;
        $configEntites = $configuration->getConfigValues();
        $this->configEntites = $configEntites['entities'];
    }

    /**
     * Generate fake data for an entity and return it as an Array
     *
     * @param string $entity
     *
     * @return array
     */
    public function generateFakeData($entity)
    {
        if (empty($this->configEntites)) {
            throw new InetAnonConfigurationException('No entities found. Have you loaded a configuration file ?');
        }
        if (!array_key_exists($entity, $this->configEntites)) {
            throw new InetAnonConfigurationException("No configuration for that entity ($entity)");
        }

        $faker = \Faker\Factory::create();

        $entityCols = $this->configEntites[$entity]['cols'];
        $entity = array();
        foreach ($entityCols as $colName => $colProps) {
            $args = empty($colProps['params']) ? array() : $colProps['params'];
            $data = call_user_func_array(array($faker, $colProps['method']), $args);
            $entity[$colName] = $data;
        }

        return $entity;
    }
}
