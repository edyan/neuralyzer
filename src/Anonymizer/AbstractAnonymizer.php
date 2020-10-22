<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.2
 *
 * @author    Emmanuel Dyan
 * @author    Rémi Sauvat
 *
 * @copyright 2020 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Anonymizer;

use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Exception\NeuralyzerConfigurationException;

/**
 * Abstract Anonymizer, that can be implemented as DB Anonymizer for example
 * Its goal is only to anonymize any data, from a simple array
 * not to write or read it from anywhere
 */
abstract class AbstractAnonymizer
{
    /**
     * Update data into table
     */
    public const UPDATE_TABLE = 1;

    /**
     * Insert data into table
     */
    public const INSERT_TABLE = 2;

    /**
     * Set the batch size for updates
     *
     * @var int
     */
    protected $batchSize = 1000;

    /**
     * Contains the configuration object
     *
     * @var Reader
     */
    protected $configuration;

    /**
     * Configuration of entities
     *
     * @var array
     */
    protected $configEntities = [];

    /**
     * List of used fakers
     *
     * @var array<\Faker\Generator>|array<\Faker\UniqueGenerator>
     */
    protected $fakers = [];

    /**
     * Current table (entity) to process
     *
     * @var string
     */
    protected $entity;

    /**
     * Current table (entity) Columns
     *
     * @var array
     */
    protected $entityCols;

    /**
     * Limit the number of updates or create
     *
     * @var int
     */
    protected $limit = 0;

    /**
     * Pretend we do the update, but do nothing
     *
     * @var bool
     */
    protected $pretend = true;

    /**
     * Return the generated SQL
     *
     * @var bool
     */
    protected $returnRes = false;

    /**
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * Process the entity according to the anonymizer type
     *
     * @param string        $entity   Entity's name
     * @param callable|null $callback Callback function with current row num as parameter
     *
     * @return array
     */
    abstract public function processEntity(
        string $entity,
        ?callable $callback = null
    ): array;

    /**
     * Set the configuration
     */
    public function setConfiguration(Reader $configuration): void
    {
        $this->configuration = $configuration;
        $this->configEntities = $configuration->getConfigValues()['entities'];
        $this->initFaker();
    }

    /**
     * Limit of fake generated records for updates and creates
     *
     * @return mixed
     */
    public function setLimit(int $limit)
    {
        $this->limit = $limit;
        if ($this->limit < $this->batchSize) {
            $this->batchSize = $this->limit;
        }

        return $this;
    }

    /**
     * Activate or deactivate the pretending mode (dry run)
     *
     * @return mixed
     */
    public function setPretend(bool $pretend)
    {
        $this->pretend = $pretend;

        return $this;
    }

    /**
     * Return or not a result (like an SQL Query that has
     * been generated with fake data)
     *
     * @return mixed
     */
    public function setReturnRes(bool $returnRes)
    {
        $this->returnRes = $returnRes;

        return $this;
    }

    /**
     * Evaluate, from the configuration if I have to update or Truncate the table
     *
     * @throws NeuralyzerConfigurationException
     */
    protected function whatToDoWithEntity(): int
    {
        $this->checkEntityIsInConfig();

        $entityConfig = $this->configEntities[$this->entity];

        $actions = 0;
        if (array_key_exists('cols', $entityConfig)) {
            switch ($entityConfig['action']) {
                case 'update':
                    $actions |= self::UPDATE_TABLE;
                    break;
                case 'insert':
                    $actions |= self::INSERT_TABLE;
                    break;
            }
        }

        return $actions;
    }

    /**
     * Generate fake data for an entity and return it as an Array
     *
     * @return array
     *
     * @throws NeuralyzerConfigurationException
     */
    protected function generateFakeData(): array
    {
        $this->checkEntityIsInConfig();
        $colsInConfig = $this->configEntities[$this->entity]['cols'];
        $row = [];
        foreach ($colsInConfig as $colName => $colProps) {
            $this->checkColIsInEntity($colName);
            $data = \call_user_func_array(
                [$this->getFakerObject($this->entity, $colName, $colProps), $colProps['method']],
                $colProps['params']
            );
            if (! is_scalar($data)) {
                $msg = "You must use faker methods that generate strings: '{$colProps['method']}' forbidden";
                throw new NeuralyzerConfigurationException($msg);
            }
            $row[$colName] = trim($data);
            $colLength = $this->entityCols[$colName]['length'];
            // Cut the value if too long ...
            if (! empty($colLength) && \strlen($row[$colName]) > $colLength) {
                $row[$colName] = substr($row[$colName], 0, $colLength - 1);
            }
        }

        return $row;
    }

    /**
     * Make sure that entity is defined in the configuration
     *
     * @throws NeuralyzerConfigurationException
     */
    protected function checkEntityIsInConfig(): void
    {
        if (empty($this->configEntities)) {
            throw new NeuralyzerConfigurationException(
                'No entities found. Have you loaded a configuration file ?'
            );
        }
        if (! array_key_exists($this->entity, $this->configEntities)) {
            throw new NeuralyzerConfigurationException(
                "No configuration for that entity ({$this->entity})"
            );
        }
    }

    /**
     * Verify a column is defined in the real entityCols
     *
     * @throws NeuralyzerConfigurationException
     */
    protected function checkColIsInEntity(string $colName): void
    {
        if (! array_key_exists($colName, $this->entityCols)) {
            throw new NeuralyzerConfigurationException("Col ${colName} does not exist");
        }
    }

    /**
     * Init Faker and add additional methods
     */
    protected function initFaker(): void
    {
        $language = $this->configuration->getConfigValues()['language'];
        $this->faker = \Faker\Factory::create($language);
        $this->faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\Base($this->faker));
        $this->faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\Json($this->faker));
        $this->faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\UniqueWord($this->faker, $language));
    }

    /**
     * Get the faker object for a entity column
     *
     * @param array  $colProps
     *
     * @return \Faker\Generator|\Faker\UniqueGenerator
     */
    protected function getFakerObject(string $entityName, string $colName, array $colProps)
    {
        if (! isset($this->fakers[$entityName][$colName])) {
            $fakerClone = clone $this->faker;
            $this->fakers[$entityName][$colName] = isset($colProps['unique']) && $colProps['unique'] === true ? $fakerClone->unique() : $fakerClone;
        }

        return $this->fakers[$entityName][$colName];
    }
}
