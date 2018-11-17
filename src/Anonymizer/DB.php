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

use Edyan\Neuralyzer\Exception\NeuralizerConfigurationException;
use Edyan\Neuralyzer\Exception\NeuralizerException;
use Edyan\Neuralyzer\Utils\CSVWriter;
use Edyan\Neuralyzer\Utils\Expression;
use Edyan\Neuralyzer\Utils\DBUtils;

/**
 * Implement AbstractAnonymizer for DB, to read and write data via Doctrine DBAL
 */
class DB extends AbstractAnonymizer
{
    /**
     * Various generic utils
     *
     * @var Expression
     */
    private $expression;

    /**
     * Various generic utils
     *
     * @var DBUtils
     */
    private $dbUtils;

    /**
     * Various generic utils
     *
     * @var DBHelper
     */
    private $dbHelper;

    /**
     * Primary Key
     *
     * @var string
     */
    private $priKey;

    /**
     * Define the way we update / insert data
     *
     * @var string
     */
    private $mode = 'queries';

    /**
     * Contains queries if returnRes is true
     *
     * @var array
     */
    private $queries = [];

    /**
     * File resource for the csv (batch mode)
     *
     * @var CSVWriter
     */
    private $csv;

    /**
     * Define available update modes
     *
     * @var array
     */
    private $updateMode = [
        'queries' => 'doUpdateByQueries',
        'batch' => 'doBatchUpdate',
    ];

    /**
     * Define available insert modes
     *
     * @var array
     */
    private $insertMode = [
        'queries' => 'doInsertByQueries',
        'batch' => 'doBatchInsert',
    ];


    public function __construct(Expression $expression, DBUtils $dbUtils)
    {
        $this->expression = $expression;
        $this->dbUtils = $dbUtils;
        $this->dbHelper = $this->dbUtils->getDBHelper();
        $this->dbHelper->registerCustomTypes();
    }

    /**
     * Returns the dependency
     * @return DBUtils
     */
    public function getDbUtils(): DBUtils
    {
        return $this->dbUtils;
    }

    /**
     * Set the mode for update / insert
     *
     * @param string $mode
     * @throws NeuralizerException
     * @return DB
     */
    public function setMode(string $mode): DB
    {
        if (!in_array($mode, ['queries', 'batch'])) {
            throw new NeuralizerException('Mode could be only queries or batch');
        }

        if ($mode === 'batch') {
            $this->csv = new CSVWriter();
            $this->csv->setCsvControl('|', $this->dbHelper->getEnclosureForCSV());
        }

        $this->mode = $mode;

        return $this;
    }


    /**
     * Process an entity by reading / writing to the DB
     *
     * @param string        $entity
     * @param callable|null $callback
     * @throws \Exception
     * @return void|array
     */
    public function processEntity(string $entity, callable $callback = null): array
    {
        $this->dbUtils->assertTableExists($entity);

        $this->priKey = $this->dbUtils->getPrimaryKey($entity);
        $this->entityCols = $this->dbUtils->getTableCols($entity);
        $this->entity = $entity;

        $actionsOnThatEntity = $this->whatToDoWithEntity();
        $this->queries = [];

        // Wrap everything in a transaction
        $conn = $this->dbUtils->getConn();
        try {
            $conn->beginTransaction();

            if ($actionsOnThatEntity & self::UPDATE_TABLE) {
                $this->updateData($callback);
            }

            if ($actionsOnThatEntity & self::INSERT_TABLE) {
                $this->insertData($callback);
            }

            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollBack();
            $conn->close(); // To avoid locks

            throw $e;
        }

        return $this->queries;
    }


    /**
     * Update data of db table.
     *
     * @param  callable $callback
     *
     * @throws NeuralizerException
     */
    private function updateData($callback = null): void
    {
        $queryBuilder = $this->dbUtils->getConn()->createQueryBuilder();
        if ($this->limit === 0) {
            $this->setLimit($this->dbUtils->countResults($this->entity));
        }

        $this->expression->evaluateExpressions($this->configuration->getPreActions());

        $startAt = 0; // The first part of the limit (offset)
        $num = 0; // The number of rows updated
        while ($num < $this->limit) {
            $rows = $queryBuilder
                ->select('*')->from($this->entity)
                ->setFirstResult($startAt)->setMaxResults($this->batchSize)
                ->orderBy($this->priKey)
                ->execute();

            // I need to read line by line if I have to update the table
            // to make sure I do update by update (slower but no other choice for now)
            foreach ($rows as $row) {
                // Call the right method according to the mode
                $this->{$this->updateMode[$this->mode]}($row);

                if ($callback !== null) {
                    $callback(++$num);
                }
                // Have to exit now as we have reached the max
                if ($num >= $this->limit) {
                    break 2;
                }
            }
            // Move the offset
            // Make sure the loop ends if we have nothing to process
            $num = $startAt += $this->batchSize;
        }
        // Run a final method if defined
        if ($this->mode === 'batch') {
            $this->loadDataInBatch('update');
        }

        $this->expression->evaluateExpressions($this->configuration->getPostActions());
    }


    /**
     * Execute the Update with Doctrine QueryBuilder
     * @SuppressWarnings("unused") - Used dynamically
     *
     * @param  array $row Full row
     */
    private function doUpdateByQueries(array $row): void
    {
        $data = $this->generateFakeData();

        $queryBuilder = $this->dbUtils->getConn()->createQueryBuilder();
        $queryBuilder = $queryBuilder->update($this->entity);
        foreach ($data as $field => $value) {
            $value = empty($row[$field]) ?
                $this->dbUtils->getEmptyValue($this->entityCols[$field]['type']) :
                $value;

            $condition = $this->dbUtils->getCondition($field, $this->entityCols[$field]);
            $queryBuilder = $queryBuilder->set($field, $condition);
            $queryBuilder = $queryBuilder->setParameter(":$field", $value);
        }
        $queryBuilder = $queryBuilder->where("{$this->priKey} = :{$this->priKey}");
        $queryBuilder = $queryBuilder->setParameter(":{$this->priKey}", $row[$this->priKey]);

        $this->returnRes === true ?
            array_push($this->queries, $this->dbUtils->getRawSQL($queryBuilder)) :
            '';

        if ($this->pretend === false) {
            $queryBuilder->execute();
        }
    }


    /**
     * Write the line required for a later LOAD DATA (or \copy)
     * @SuppressWarnings("unused") - Used dynamically
     *
     * @param  array $row Full row
     */
    private function doBatchUpdate(array $row): void
    {
        $fakeData = $this->generateFakeData();
        $data = [];
        // Go trough all fields, and take a value by priority
        foreach (array_keys($this->entityCols) as $field) {
            // First take the fake data
            $data[$field] = $row[$field];
            if (!empty($row[$field]) && array_key_exists($field, $fakeData)) {
                $data[$field] = $fakeData[$field];
            }
        }

        $this->csv->write($data);
    }


    /**
     * Insert data into table
     *
     * @param  callable $callback
     */
    private function insertData($callback = null): void
    {
        $this->expression->evaluateExpressions($this->configuration->getPreActions());

        for ($rowNum = 1; $rowNum <= $this->limit; $rowNum++) {
            // Call the right method according to the mode
            $this->{$this->insertMode[$this->mode]}($rowNum);

            if (!is_null($callback)) {
                $callback($rowNum);
            }
        }

        // Run a final method if defined
        if ($this->mode === 'batch') {
            $this->loadDataInBatch('insert');
        }

        $this->expression->evaluateExpressions($this->configuration->getPostActions());
    }


    /**
     * Execute an INSERT with Doctrine QueryBuilder
     * @SuppressWarnings("unused") - Used dynamically
     */
    private function doInsertByQueries(): void
    {
        $data = $this->generateFakeData();

        $queryBuilder = $this->dbUtils->getConn()->createQueryBuilder();
        $queryBuilder = $queryBuilder->insert($this->entity);
        foreach ($data as $field => $value) {
            $queryBuilder = $queryBuilder->setValue($field, ":$field");
            $queryBuilder = $queryBuilder->setParameter(":$field", $value);
        }

        $this->returnRes === true ?
            array_push($this->queries, $this->dbUtils->getRawSQL($queryBuilder)) :
            '';

        if ($this->pretend === false) {
            $queryBuilder->execute();
        }
    }


    /**
     * Write the line required for a later LOAD DATA (or \copy)
     * @SuppressWarnings("unused") - Used dynamically
     */
    private function doBatchInsert(): void
    {
        $data = $this->generateFakeData();
        $this->csv->write($data);
    }


    /**
     * If a file has been created for the batch mode, destroy it
     * @SuppressWarnings("unused") - Used dynamically
     *
     * @param string $mode "update" or "insert"
     */
    private function loadDataInBatch(string $mode): void
    {
        $fields = array_keys($this->configEntites[$this->entity]['cols']);
        // Replace by all fields if update as we have to load everything
        if ($mode === 'update') {
            $fields = array_keys($this->entityCols);
        }

        // Load the data from the helper, only if pretend is false
        $filename = $this->csv->getRealPath();
        $this->dbHelper->setPretend($this->pretend);
        $sql = $this->dbHelper->loadData($this->entity, $filename, $fields, $mode);

        $this->returnRes === true ? array_push($this->queries, $sql) : '';

        // Destroy the file
        unlink($this->csv->getRealPath());
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

        $colsInConfig = $this->configEntites[$this->entity]['cols'];
        $row = [];
        foreach ($colsInConfig as $colName => $colProps) {
            $this->checkColIsInEntity($colName);

            $data = \call_user_func_array(
                [$this->faker, $colProps['method']],
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
}
