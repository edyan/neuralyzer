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
     * @var resource
     */
    private $csvFile;

    /**
     * When we run a batch, with a replace (LOAD DATA REPLACE for MySQL)
     * That's the field we keep the original data from
     * @var array
     */
    private $keepFields = [];


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
            $this->csvFile = new CSVWriter();
            $this->csvFile->setCsvControl('|', chr(0)); // empty enclosure
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
        $schema = $this->conn->getSchemaManager();
        if ($schema->tablesExist($entity) === false) {
            throw new NeuralizerException("Table $entity does not exist");
        }

        $this->priKey = $this->dbUtils->getPrimaryKey($entity);
        $this->entityCols = $this->dbUtils->getTableCols($entity);
        $this->entity = $entity;

        $actionsOnThatEntity = $this->whatToDoWithEntity();
        $this->queries = [];
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

        return $this->queries;
    }


    /**
     * To debug, build the final SQL (can be approximative)
     * @param  QueryBuilder $queryBuilder
     * @return string
     */
    private function getRawSQL(QueryBuilder $queryBuilder)
    {
        $sql = $queryBuilder->getSQL();
        foreach ($queryBuilder->getParameters() as $parameter => $value) {
            $sql = str_replace($parameter, "'$value'", $sql);
        }

        return $sql;
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

        try {
            $queryBuilder->execute();
        } catch (\Exception $e) {
            throw new NeuralizerException('Query DELETE Error (' . $e->getMessage() . ')');
        }

        return $sql;
    }


    /**
     * Build the condition by casting the value if needed
     *
     * @param  string $field
     * @return string
     */
    private function getCondition(string $field): string
    {
        $type = strtolower($this->entityCols[$field]['type']);

        $integerCast = $this->getIntegerCast($field);

        $condition = "(CASE $field WHEN NULL THEN NULL ELSE :$field END)";

        $typeToCast = [
            'date'     => 'DATE',
            'datetime' => 'DATE',
            'time'     => 'TIME',
            'smallint' => $integerCast,
            'integer'  => $integerCast,
            'bigint'   => $integerCast,
            'float'    => 'DECIMAL',
            'decimal'  => 'DECIMAL',
        ];

        // No cast required
        if (!array_key_exists($type, $typeToCast)) {
            return $condition;
        }

        return "CAST($condition AS {$typeToCast[$type]})";
    }


    /**
     * Get the right CAST for an INTEGER
     *
     * @param  string $field
     * @return string
     */
    private function getIntegerCast(string $field): string
    {
        $driver = $this->getConn()->getDriver();
        if ($driver->getName() === 'pdo_mysql') {
            return $this->entityCols[$field]['unsigned'] === true ? 'UNSIGNED' : 'SIGNED';
        }

        return 'INTEGER';
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

        $this->keepFields = array_diff(
            array_keys($this->entityCols),
            array_keys($this->configEntites[$this->entity]['cols'])
        );

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
            $queryBuilder = $queryBuilder->set($field, $this->getCondition($field));
            $queryBuilder = $queryBuilder->setParameter(":$field", $value);
        }
        $queryBuilder = $queryBuilder->where("{$this->priKey} = :{$this->priKey}");
        $queryBuilder = $queryBuilder->setParameter(":{$this->priKey}", $row[$this->priKey]);

        $this->returnRes === true ?
            array_push($this->queries, $this->getRawSQL($queryBuilder)) :
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
        $data = $this->generateFakeData();
        // Values to change, take values from the SELECT
        // Change it for the one generated by faker
        // only if it's not empty
        foreach ($row as $field => $value) {
            if (empty($value)) {
                $data[$field] = '';
            }
        }

        // Now handle extra fields, to keep
        // That's the primary key + other fields not anonymized
        foreach ($this->keepFields as $field) {
            $data[$field] = $row[$field];
        }

        $this->csvFile->write($data);
    }


    /**
     * Insert data into table
     * @param  callable $callback
     */
    private function insertData($callback = null): void
    {
        for ($rowNum = 1; $rowNum <= $this->limit; $rowNum++) {
            // Call the right method according to the mode
            $this->{$this->insertMode[$this->mode]}();

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
            array_push($this->queries, $this->getRawSQL($queryBuilder)) :
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
        $this->csvFile->write($data);
    }


    /**
     * If a file has been created for the batch mode, destroy it
     * @SuppressWarnings("unused") - Used dynamically
     */
    private function loadDataInBatch(string $mode): void
    {
        $dbType = substr($this->getConn()->getDriver()->getName(), 4);
        $method = 'loadDataFor' . ucfirst($dbType);

        $fields = array_keys($this->configEntites[$this->entity]['cols']);
        if ($mode === 'update') {
            $fields = array_merge($fields, $this->keepFields);
        }

        $sql = $this->{$method}($fields, $mode);

        $this->returnRes === true ? array_push($this->queries, $sql) : '';

        // Destroy the file
        unlink($this->csvFile->getRealPath());
    }


    /**
     * Load Data for MySQL (Specific Query)
     * @SuppressWarnings("unused") - Used dynamically
     * @param  array  $fields
     * @return string
     */
    private function loadDataForMysql(array $fields, string $mode): string
    {
        $sql ="LOAD DATA LOCAL INFILE '" . $this->csvFile->getRealPath() . "'
     REPLACE INTO TABLE {$this->entity}
     FIELDS TERMINATED BY '|' ENCLOSED BY ''
     (`" . implode("`, `", $fields) . "`)";

        // Run the query if asked
        $conn = $this->getConn();
        if ($this->pretend === false) {
            $conn->beginTransaction();
            try {
                $conn->query($sql);
            } catch (\Exception $e) {
                $conn->rollBack();
                throw $e;
            }

            $conn->commit();
        }

        return $sql;
    }


    /**
     * Load Data for Postgres (Specific Query)
     * @SuppressWarnings("unused") - Used dynamically
     * @param  array  $fields
     * @return string
     */
    private function loadDataForPgsql(array $fields, string $mode): string
    {
        $conn = $this->getConn();
        $pdo = $conn->getWrappedConnection();
        $fields = implode(', ', $fields);

        $conn->beginTransaction();
        try {
            if ($mode === 'update') {
                $conn->query("TRUNCATE {$this->entity}");
            }

            $filename = $this->csvFile->getRealPath();
            if ($this->pretend === false) {
                $pdo->pgsqlCopyFromFile($this->entity, $filename, '|', '\\\\N', $fields);
            }
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }

        $conn->commit();

        $sql = "COPY {$this->entity} ($fields) FROM '{$filename}' ";
        $sql.= '... Managed by pgsqlCopyFromFile';

        return $sql;
    }
}
