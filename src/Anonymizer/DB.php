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
     * Filename for the csv (batch mode)
     * @var string
     */
    private $csvFileName;

    /**
     * File resource for the csv (batch mode)
     * @var resource
     */
    private $csvFile;

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
     * Final method to launch once everything is done
     * @var array
     */
    private $insertModeFinalize = [
        'batch' => 'loadDataInBatch'
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
            $this->csvFileName = tempnam(sys_get_temp_dir(), 'neuralyzer');
            $this->csvFile = fopen($this->csvFileName, 'w');
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
    }


    /**
     * Execute the Update with Doctrine QueryBuilder
     * @param  array $row  Full row
     */
    private function doUpdateByQueries(array $row): void
    {
        $data = $this->generateFakeData();

        $queryBuilder = $this->conn->createQueryBuilder();
        $queryBuilder = $queryBuilder->update($this->entity);
        foreach ($data as $field => $value) {
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
     * @param  array $row  Full row
     */
    private function doBatchUpdate(array $row): void
    {
        $data = $this->generateFakeData();
        foreach ($row as $field => $value) {
            if (empty($value)) {
                $data[$field] = '';
            }
        }
        fputcsv($this->csvFile, $data);
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
        if (array_key_exists($this->mode, $this->insertModeFinalize)) {
            $this->{$this->insertModeFinalize[$this->mode]}();
        }
    }


    /**
     * Execute an INSERT with Doctrine QueryBuilder
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
     */
    private function doBatchInsert(): void
    {
        $data = $this->generateFakeData();
        fputcsv($this->csvFile, $data);
    }


    /**
     * If a file has been created for the batch mode, destroy it
     */
    private function loadDataInBatch(): void
    {
        // Run a query for each type of DB
        $fields = array_keys($this->configEntites[$this->entity]['cols']);
        $fields = implode(', ', $fields);
        $loadDataFor = [
            'pdo_mysql' => "LOAD DATA LOCAL INFILE ? INTO TABLE {$this->entity} FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ({$fields})",
        ];

        $driver = $this->getConn()->getDriver();

        // Run the query if asked
        if ($this->pretend === false) {
            $stmt = $this->getConn()->prepare($loadDataFor[$driver->getName()]);
            $stmt->bindValue(1, $this->csvFileName);
            $stmt->execute();
        }

        $sql = str_replace('?', "'{$this->csvFileName}'", $loadDataFor[$driver->getName()]);
        $this->returnRes === true ? array_push($this->queries, $sql) : '';

        // Destroy the file
        unlink($this->csvFileName);
    }
}
