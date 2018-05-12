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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\DriverManager as DbalDriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Edyan\Neuralyzer\Exception\NeuralizerException;
use Edyan\Neuralyzer\Utils\CSVWriter;
use Edyan\Neuralyzer\Utils\DBUtils;

/**
 * Implement AbstractAnonymizer for DB, to read and write data via Doctrine DBAL
 */
class DB extends AbstractAnonymizer
{
    /**
     * Doctrine DB Adapter
     * @var Connection
     */
    private $conn;

    /**
     * Various Options for drivers
     * @var array
     */
    private $driverOptions = [
        'pdo_mysql' => [1001 => true], // 1001 = \PDO::MYSQL_ATTR_LOCAL_INFILE
    ];

    /**
     * Various generic utils
     * @var DBUtils
     */
    private $dbUtils;

    /**
     * Primary Key
     * @var string
     */
    private $priKey;

    /**
     * Define the way we update / insert data
     * @var string
     */
    private $mode = 'queries';

    /**
     * Contains queries if returnRes is true
     * @var array
     */
    private $queries = [];

    /**
     * File resource for the csv (batch mode)
     * @var CSVWriter
     */
    private $csv;

    /**
     * Define available update modes
     * @var array
     */
    private $updateMode = [
        'queries' => 'doUpdateByQueries',
        'batch' => 'doBatchUpdate'
    ];

    /**
     * Define available insert modes
     * @var array
     */
    private $insertMode = [
        'queries' => 'doInsertByQueries',
        'batch' => 'doBatchInsert'
    ];

    /**
     * Init connection
     *
     * @param $params   Parameters to send to Doctrine DB
     */
    public function __construct(array $params)
    {
        // Set specific options
        $params['driverOptions'] = array_key_exists($params['driver'], $this->driverOptions)
            ? $this->driverOptions[$params['driver']]
            : [];

        $this->conn = DbalDriverManager::getConnection($params, new DbalConfiguration());
        $this->conn->setFetchMode(\Doctrine\DBAL\FetchMode::ASSOCIATIVE);

        $this->dbUtils = new DBUtils($this->conn);
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
     * @param string $mode
     * @return DB
     */
    public function setMode(string $mode): DB
    {
        if (!in_array($mode, ['queries', 'batch'])) {
            throw new NeuralizerException('Mode could be only queries or batch');
        }

        if ($mode === 'batch') {
            $driver = $this->conn->getDriver()->getName();
            $enclosure = (strpos($driver, 'pgsql') || strpos($driver, 'sqlsrv'))
                ? chr(0)
                : '"';
            $this->csv = new CSVWriter();
            $this->csv->setCsvControl('|', $enclosure);
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
     * Update data of table
     *
     * @param  callable $callback
     */
    private function updateData($callback = null): void
    {
        $queryBuilder = $this->conn->createQueryBuilder();
        if ($this->limit === 0) {
            $this->setLimit($this->dbUtils->countResults($this->entity));
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
    }


    /**
     * Execute the Update with Doctrine QueryBuilder
     * @SuppressWarnings("unused") - Used dynamically
     * @param  array $row  Full row
     */
    private function doUpdateByQueries(array $row): void
    {
        $data = $this->generateFakeData();

        $queryBuilder = $this->conn->createQueryBuilder();
        $queryBuilder = $queryBuilder->update($this->entity);
        foreach ($data as $field => $value) {
            $value = empty($row[$field]) ? '' : $value;
            $queryBuilder = $queryBuilder->set(
                $field,
                $this->dbUtils->getCondition($field, $this->entityCols[$field])
            );
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
     * @param  array $row  Full row
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
     * @param string $mode "update" or "insert"
     */
    private function loadDataInBatch(string $mode): void
    {
        $dbType = substr($this->conn->getDriver()->getName(), 4);
        $method = 'loadDataFor' . ucfirst($dbType);

        $fields = array_keys($this->configEntites[$this->entity]['cols']);
        // Replace by all fields if update as we have to load everything
        if ($mode === 'update') {
            $fields = array_keys($this->entityCols);
        }

        $sql = $this->{$method}($fields, $mode);

        $this->returnRes === true ? array_push($this->queries, $sql) : '';

        // Destroy the file
        unlink($this->csv->getRealPath());
    }


    /**
     * Load Data for MySQL (Specific Query)
     * @SuppressWarnings("unused") - Used dynamically
     * @param  array   $fields
     * @param  string  $mode  Not in used here
     * @return string
     */
    private function loadDataForMysql(array $fields, string $mode): string
    {
        $sql ="LOAD DATA LOCAL INFILE '" . $this->csv->getRealPath() . "'
     REPLACE INTO TABLE {$this->entity}
     FIELDS TERMINATED BY '|' ENCLOSED BY '\"'
     (`" . implode("`, `", $fields) . "`)";

        // Run the query if asked
        if ($this->pretend === false) {
            $this->conn->query($sql);
        }

        return $sql;
    }


    /**
     * Load Data for Postgres (Specific Query)
     * @SuppressWarnings("unused") - Used dynamically
     * @param  array   $fields
     * @param  string  $mode   "update" or "insert" to know if we truncate or not
     * @return string
     */
    private function loadDataForPgsql(array $fields, string $mode): string
    {
        $fields = implode(', ', $fields);

        $filename = $this->csv->getRealPath();
        if ($this->pretend === false) {
            if ($mode === 'update') {
                $this->conn->query("TRUNCATE {$this->entity}");
            }
            $pdo = $this->conn->getWrappedConnection();
            $pdo->pgsqlCopyFromFile($this->entity, $filename, '|', '\\\\N', $fields);
        }

        $sql = "COPY {$this->entity} ($fields) FROM '{$filename}' ";
        $sql.= '... Managed by pgsqlCopyFromFile';

        return $sql;
    }


    /**
     * Load Data for SQLServer (Specific Query)
     * @SuppressWarnings("unused") - Used dynamically
     * @param  array   $fields
     * @param  string  $mode   "update" or "insert" to know if we truncate or not
     * @return string
     */
    private function loadDataForSqlsrv(array $fields, string $mode): string
    {
        if (substr(gethostbyname($this->conn->getHost()), 0, 3) !== '127') {
            throw new NeuralizerException('SQL Server must be on the same host than PHP');
        }

        $sql ="BULK INSERT {$this->entity}
     FROM '" . $this->csv->getRealPath() . "' WITH (
         FIELDTERMINATOR = '|', DATAFILETYPE = 'widechar', ROWTERMINATOR = '" . PHP_EOL . "'
     )";

        if ($this->pretend === false) {
            if ($mode === 'update') {
                $this->conn->query("TRUNCATE TABLE {$this->entity}");
            }

            $this->conn->query($sql);
        }

        return $sql;
    }
}
