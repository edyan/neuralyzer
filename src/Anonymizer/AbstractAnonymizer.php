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
     * Constant to define the type of action for that table
     */
    const TRUNCATE_TABLE = 1;

    /**
     * Constant to define the type of action for that table
     */
    const UPDATE_TABLE = 2;

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
     * Evaluate, from the configuration if I have to update or Truncate the table
     *
     * @param string $entity
     *
     * @return int
     */
    public function whatToDoWithEntity($entity)
    {
        $this->checkEntityIsInConfig($entity);

        $entityConfig = $this->configEntites[$entity];

        $actions = '';
        if (array_key_exists('delete', $entityConfig) && $entityConfig['delete'] === true) {
            $actions |= self::TRUNCATE_TABLE;
        }

        if (array_key_exists('cols', $entityConfig)) {
            $actions |= self::UPDATE_TABLE;
        }

        return $actions;
    }

    /**
     * Returns the 'delete_where' parameter for an entity in config (or empty)
     *
     * @param string $entity
     *
     * @return string
     */
    public function getWhereConditionInConfig($entity)
    {
        $this->checkEntityIsInConfig($entity);

        if (!array_key_exists('delete_where', $this->configEntites[$entity])) {
            return '';
        }

        return $this->configEntites[$entity]['delete_where'];
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
        $this->checkEntityIsInConfig($entity);

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

    /**
     * Make sure that entity is defined in the configuration
     *
     * @param string $entity
     *
     * @throws InetAnonConfigurationException
     */
    private function checkEntityIsInConfig($entity)
    {
        if (empty($this->configEntites)) {
            throw new InetAnonConfigurationException('No entities found. Have you loaded a configuration file ?');
        }
        if (!array_key_exists($entity, $this->configEntites)) {
            throw new InetAnonConfigurationException("No configuration for that entity ($entity)");
        }
    }
}
