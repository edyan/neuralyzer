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

use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager as DbalDriverManager;
use Edyan\Neuralyzer\Exception\NeuralizerConfigurationException;
use Edyan\Neuralyzer\Exception\NeuralizerException;
use Edyan\Neuralyzer\Helper\DB as DBHelper;
use Edyan\Neuralyzer\Utils\CSVWriter;
use Edyan\Neuralyzer\Utils\DBUtils;

/**
 * Implement AbstractAnonymizer for DB, to read and write data via Doctrine DBAL
 */
class DB extends AbstractAnonymizer
{
    /**
     * Doctrine DB Adapter
     *
     * @var Connection
     */
    private $conn;

    /**
     * A helper for the current driver
     *
     * @var DBHelper\AbstractDBHelper
     */
    private $dbHelper;

    /**
     * Various generic utils
     *
     * @var DBUtils
     */
    private $dbUtils;

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

    /**
     * Init connection
     *
     * @param array $params Parameters to send to Doctrine DB
     */
    public function __construct(array $params)
    {
        $dbHelperClass = DBHelper\DriverGuesser::getDBHelper($params['driver']);

        // Set specific options
        $params['driverOptions'] = $dbHelperClass::getDriverOptions();
        $this->conn = DbalDriverManager::getConnection($params, new DbalConfiguration());
        $this->conn->setFetchMode(\Doctrine\DBAL\FetchMode::ASSOCIATIVE);

        $this->dbUtils = new DBUtils($this->conn);
        $this->dbHelper = new $dbHelperClass($this->conn);
    }


    /**
     * Get Doctrine Connection
     *
     * @return Connection
     */
    public function getConn(): Connection
    {
        return $this->conn;
    }


    /**
     * Set the mode for update / insert
     *
     * @param string $mode
     *
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
     *
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
        try {
            $this->conn->beginTransaction();

            if ($actionsOnThatEntity & self::TRUNCATE_TABLE) {
                $where = $this->getWhereConditionInConfig();
                $query = $this->runDelete($where);
                ($this->returnRes === true ? array_push($this->queries, $query) : '');
            }

            if ($actionsOnThatEntity & self::UPDATE_TABLE) {
                $this->updateData($callback);
            }

            if ($actionsOnThatEntity & self::INSERT_TABLE) {
                $this->insertData($callback);
            }

            $this->conn->commit();
        } catch (\Exception $e) {
            $this->conn->rollback();
            $this->conn->close(); // To avoid locks

            throw $e;
        }

        return $this->queries;
    }


    /**
     * Execute the Delete with Doctrine Query Builder
     *
     * @param string $where
     *
     * @return string
     */
    private function runDelete(string $where): string
    {
        $queryBuilder = $this->conn->createQueryBuilder();
        $queryBuilder = $queryBuilder->delete($this->entity);
        if (!empty($where)) {
            $queryBuilder = $queryBuilder->where($where);
        }
        $sql = $queryBuilder->getSQL();

        if ($this->pretend === true) {
            return $sql;
        }

        $queryBuilder->execute();

        return $sql;
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
        $queryBuilder = $this->conn->createQueryBuilder();
        if ($this->limit === 0) {
            $this->setLimit($this->dbUtils->countResults($this->entity));
        }

        foreach ($this->configuration->getPreQueries() as $preQuery) {
            try {
                $this->conn->query($preQuery);
            } catch (\Exception $e) {
                throw new NeuralizerException($e->getMessage());
            }
        }

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

                if (!is_null($callback)) {
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

        foreach ($this->configuration->getPostQueries() as $postQuery) {
            try {
                $this->conn->query($postQuery);
            } catch (\Exception $e) {
                throw new NeuralizerException($e->getMessage());
            }
        }
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

        $queryBuilder = $this->conn->createQueryBuilder();
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
    }


    /**
     * Execute an INSERT with Doctrine QueryBuilder
     * @SuppressWarnings("unused") - Used dynamically
     */
    private function doInsertByQueries(): void
    {
        $data = $this->generateFakeData();

        $queryBuilder = $this->conn->createQueryBuilder();
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

            // Check if faker is already used for this column name, so we can use the same faker.
            if (!isset($this->fakers[$colName][$colProps['method']])) {
                $language = $this->configuration->getConfigValues()['language'];
                $faker = \Faker\Factory::create($language);
                $faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\Base($faker));

                // Check if column should be unique.
                if ($colProps['method'] === 'uniqueWord') {
                    // Count number of unique words to be taken from the dictionary.
                    $count = $this->dbUtils->countResults($this->entity);
                    $faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\UniqueWord($faker, $count, $language));
                    $faker = $faker->unique(true);
                }
                $this->fakers[$colName][$colProps['method']] = $faker;
            }

            $faker = $this->fakers[$colName][$colProps['method']];

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
            if (!empty($colLength) && strlen($row[$colName]) > $colLength) {
                $row[$colName] = substr($row[$colName], 0, ($colLength - 1));
            }
        }

        return $row;
    }

}
