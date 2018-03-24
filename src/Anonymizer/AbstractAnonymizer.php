<?php
/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2018 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Anonymizer;

use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Exception\NeuralizerConfigurationException;

/**
 * Abstract Anonymizer, that can be implemented as DB Anonymizer for example
 * Its goal is only to anonymize any data, from a simple array
 * not to write or read it from anywhere
 *
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
    protected $configEntites = [];

    /**
     * Current table (entity) to process
     * @var string
     */
    protected $entity;

    /**
     * Current table (entity) Columns
     * @var array
     */
    protected $entityCols;


    /**
     * Process the entity according to the anonymizer type
     *
     * @param string        $entity         Entity's name
     * @param callable|null $callback       Callback function with current row num as parameter
     * @param bool          $pretend        Simulate update
     * @param bool          $returnRes      Return queries
     */
    abstract public function processEntity(
        string $entity,
        callable $callback = null,
        bool $pretend = true,
        bool $returnRes = false
    ): array;


    /**
     * Set the configuration
     *
     * @param Reader $configuration
     */
    public function setConfiguration(Reader $configuration)
    {
        $this->configuration = $configuration;
        $this->configEntites = $configuration->getConfigValues()['entities'];
    }


    /**
     * Evaluate, from the configuration if I have to update or Truncate the table
     *
     * @return int
     */
    protected function whatToDoWithEntity(): int
    {
        $this->checkEntityIsInConfig();

        $entityConfig = $this->configEntites[$this->entity];

        $actions = 0;
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
     * @return string
     */
    public function getWhereConditionInConfig(): string
    {
        $this->checkEntityIsInConfig();

        if (!array_key_exists('delete_where', $this->configEntites[$this->entity])) {
            return '';
        }

        return $this->configEntites[$this->entity]['delete_where'];
    }


    /**
     * Generate fake data for an entity and return it as an Array
     *
     * @return array
     */
    protected function generateFakeData(): array
    {
        $this->checkEntityIsInConfig();

        $faker = \Faker\Factory::create($this->configuration->getConfigValues()['language']);

        $colsInConfig = $this->configEntites[$this->entity]['cols'];
        $row = [];
        foreach ($colsInConfig as $colName => $colProps) {
            $this->checkColIsInEntity($colName);
            $args = empty($colProps['params']) ? [] : $colProps['params'];
            $data = call_user_func_array([$faker, $colProps['method']], $args);
            if (!is_scalar($data)) {
                $msg = "You must use faker methods that generate strings: '{$colProps['method']}' forbidden";
                throw new NeuralizerConfigurationException($msg);
            }

            $row[$colName] = $data;

            $colLength = $this->entityCols[$colName]['length'];
            // Cut the value if too long ...
            if (!empty($colLength) && strlen($data) > $colLength) {
                $row[$colName] = substr($data, 0, $this->entityCols[$colName]['length']);
            }
        }

        return $row;
    }


    /**
     * Make sure that entity is defined in the configuration
     *
     * @throws NeuralizerConfigurationException
     */
    private function checkEntityIsInConfig()
    {
        if (empty($this->configEntites)) {
            throw new NeuralizerConfigurationException(
                'No entities found. Have you loaded a configuration file ?'
            );
        }
        if (!array_key_exists($this->entity, $this->configEntites)) {
            throw new NeuralizerConfigurationException(
                "No configuration for that entity ({$this->entity})"
            );
        }
    }

    /**
     * Verify a column is defined in the real entityCols
     * @param  string $colName
     */
    private function checkColIsInEntity(string $colName)
    {
        if (!array_key_exists($colName, $this->entityCols)) {
            throw new NeuralizerConfigurationException("Col $colName does not exist");
        }
    }
}
