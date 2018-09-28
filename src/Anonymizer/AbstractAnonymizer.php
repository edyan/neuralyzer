<?php
/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author    Emmanuel Dyan
 * @author    Rémi Sauvat
 * @copyright 2018 Emmanuel Dyan
 *
 * @package   edyan/neuralyzer
 *
 * @license   GNU General Public License v2.0
 *
 * @link      https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Anonymizer;

use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Exception\NeuralizerConfigurationException;
use Edyan\Neuralyzer\ExpressionUtils\UtilsInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Abstract Anonymizer, that can be implemented as DB Anonymizer for example
 * Its goal is only to anonymize any data, from a simple array
 * not to write or read it from anywhere
 *
 */
abstract class AbstractAnonymizer
{
    /**
     * Truncate table
     */
    const TRUNCATE_TABLE = 1;

    /**
     * Update data into table
     */
    const UPDATE_TABLE = 2;

    /**
     * Insert data into table
     */
    const INSERT_TABLE = 4;

    /**
     * Tagged services with expression utils in it.
     */
    private $expressionUtils;

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
    protected $configEntites = [];

    /**
     * List of used fakers
     *
     * @var array
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
     * Init connection
     *
     * @param $expressionUtils
     */
    public function __construct($expressionUtils)
    {
        $this->expressionUtils = $expressionUtils;
    }

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
        callable $callback = null
    ): array;


    /**
     * Set the configuration
     *
     * @param Reader $configuration
     */
    public function setConfiguration(Reader $configuration): void
    {
        $this->configuration = $configuration;
        $this->configEntites = $configuration->getConfigValues()['entities'];
    }


    /**
     * Limit of fake generated records for updates and creates
     *
     * @param int $limit
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
     * @param  bool $pretend
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
     * @param  bool $returnRes
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
     * @return int
     * @throws NeuralizerConfigurationException
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
     * Returns the 'delete_where' parameter for an entity in config (or empty)
     *
     * @return string
     * @throws NeuralizerConfigurationException
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
     * @throws NeuralizerConfigurationException
     */
    protected function generateFakeData(): array
    {
        $this->checkEntityIsInConfig();
        $faker = \Faker\Factory::create($this->configuration->getConfigValues()['language']);
        $faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\Base($faker));
        $colsInConfig = $this->configEntites[$this->entity]['cols'];
        $row = [];
        foreach ($colsInConfig as $colName => $colProps) {
            $this->checkColIsInEntity($colName);
            $data = \call_user_func_array(
                [$faker, $colProps['method']],
                $colProps['params']
            );
            if (!is_scalar($data)) {
                $msg = "You must use faker methods that generate strings: '{$colProps['method']}' forbidden";
                throw new NeuralizerConfigurationException($msg);
            }
            $row[$colName] = trim($data);
            $colLength = $this->entityCols[$colName]['length'];
            // Cut the value if too long ...
            if (!empty($colLength) && \strlen($row[$colName]) > $colLength) {
                $row[$colName] = substr($row[$colName], 0, $colLength - 1);
            }
        }

        return $row;
    }


    /**
     * Make sure that entity is defined in the configuration
     *
     * @throws NeuralizerConfigurationException
     */
    protected function checkEntityIsInConfig(): void
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
     *
     * @throws NeuralizerConfigurationException
     *
     * @param  string $colName [description]
     */
    protected function checkColIsInEntity(string $colName): void
    {
        if (!array_key_exists($colName, $this->entityCols)) {
            throw new NeuralizerConfigurationException("Col $colName does not exist");
        }
    }

    /**
     * @param $actions
     */
    protected function evaluateExpressionUtils($actions)
    {
        $expressionLanguage = new ExpressionLanguage();

        $values = [];
        /** @var UtilsInterface $expressionUtil */
        foreach ($this->expressionUtils as $expressionUtil) {
            $name = $expressionUtil->getName();

            foreach ($expressionUtil->getExtraArguments() as $extraArgument) {
                if (property_exists($this, $extraArgument)) {
                    $func = sprintf('get%s', ucfirst($extraArgument));
                    $expressionUtil->$extraArgument = $this->$func();
                }
            }

            $values[$name] = $expressionUtil;
        }

        foreach ($actions as $action) {
            $expressionLanguage->evaluate($action, $values);
        }
    }
}
