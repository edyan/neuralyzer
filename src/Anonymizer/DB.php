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
     * Primary Key
     * @var string
     */
    private $priKey;


    /**
     * Init connection
     *
     * @param $params   Parameters to send to Doctrine DB
     */
    public function __construct(array $params)
    {
        $this->conn = DbalDriverManager::getConnection($params, new DbalConfiguration());
        $this->conn->setFetchMode(\Doctrine\DBAL\FetchMode::ASSOCIATIVE);
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

        $this->entity = $entity;
        $this->priKey = $this->getPrimaryKey();
        $this->entityCols = $this->getTableCols();

        $queries = [];

        $actionsOnThatEntity = $this->whatToDoWithEntity();

        if ($actionsOnThatEntity & self::TRUNCATE_TABLE) {
            $where = $this->getWhereConditionInConfig();
            $query = $this->runDelete($where);
            ($this->returnRes === true ? array_push($queries, $query) : '');
        }

        if ($actionsOnThatEntity & self::UPDATE_TABLE) {
            $queries = array_merge(
                $queries,
                $this->updateData($callback)
            );
        }

        if ($actionsOnThatEntity & self::INSERT_TABLE) {
            $queries = array_merge(
                $queries,
                $this->insertData($callback)
            );
        }

        return $queries;
    }

    /**
     * Do a simple count for a table
     *
     * @param  string $table
     * @return int
     */
    public function countResults(string $table): int
    {
        $queryBuilder = $this->conn->createQueryBuilder();
        $rows = $queryBuilder->select('COUNT(1)')->from($table)->execute();

        return (int)$rows->fetch(\Doctrine\DBAL\FetchMode::NUMERIC)[0];
    }


    /**
     * Identify the primary key for a table
     *
     * @return string Field's name
     */
    private function getPrimaryKey(): string
    {
        $schema = $this->conn->getSchemaManager();
        $tableDetails = $schema->listTableDetails($this->entity);
        if ($tableDetails->hasPrimaryKey() === false) {
            throw new NeuralizerException("Can't find a primary key for '{$this->entity}'");
        }

        return $tableDetails->getPrimaryKey()->getColumns()[0];
    }


    /**
     * Retrieve columns list for a table with type and length
     *
     * @return array $cols
     */
    private function getTableCols(): array
    {
        $schema = $this->conn->getSchemaManager();
        $tableCols = $schema->listTableColumns($this->entity);
        $cols = [];
        foreach ($tableCols as $col) {
            $cols[$col->getName()] = [
                'length' => $col->getLength(),
                'type'   => $col->getType(),
                'unsigned' => $col->getUnsigned(),
            ];
        }

        return $cols;
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
     * @return array
     */
    private function updateData($callback = null): array
    {
        $queryBuilder = $this->conn->createQueryBuilder();
        if ($this->limit === 0) {
            $this->setLimit($this->countResults($this->entity));
        }

        $startAt = 0; // The first part of the limit (offset)
        $num = 0; // The number of rows updated
        $queries = [];
        while ($num < $this->limit) {
            $rows = $queryBuilder
                        ->select($this->priKey)->from($this->entity)
                        ->setFirstResult($startAt)->setMaxResults($this->batchSize)
                        ->orderBy($this->priKey)
                        ->execute();

            // I need to read line by line if I have to update the table
            // to make sure I do update by update (slower but no other choice for now)
            foreach ($rows as $row) {
                $updateQuery = $this->prepareUpdate($row[$this->priKey]);

                ($this->returnRes === true ? array_push($queries, $this->getRawSQL($updateQuery)) : '');

                if ($this->pretend === false) {
                    $updateQuery->execute();
                }

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

        return $queries;
    }


    /**
     * Execute the Update with Doctrine QueryBuilder
     *
     * @param  string $primaryKeyVal  Primary Key's Value
     * @return QueryBuilder           Doctrine DBAL QueryBuilder
     */
    private function prepareUpdate($primaryKeyVal): QueryBuilder
    {
        $data = $this->generateFakeData();

        $queryBuilder = $this->conn->createQueryBuilder();
        $queryBuilder = $queryBuilder->update($this->entity);
        foreach ($data as $field => $value) {
            $queryBuilder = $queryBuilder->set($field, $this->getCondition($field));
            $queryBuilder = $queryBuilder->setParameter(":$field", $value);
        }
        $queryBuilder = $queryBuilder->where("{$this->priKey} = :{$this->priKey}");
        $queryBuilder = $queryBuilder->setParameter(":{$this->priKey}", $primaryKeyVal);

        return $queryBuilder;
    }


    /**
     * Insert data into table
     *
     * @param  callable $callback
     * @return array
     */
    private function insertData($callback = null): array
    {
        $queries = [];

        $queryBuilder = $this->conn->createQueryBuilder();

        for ($rowNum = 1; $rowNum <= $this->limit; $rowNum++) {
            $queryBuilder = $this->prepareInsert();

            ($this->returnRes === true ? array_push($queries, $this->getRawSQL($queryBuilder)) : '');

            if ($this->pretend === false) {
                $queryBuilder->execute();
            }

            if (!is_null($callback)) {
                $callback($rowNum);
            }
        }

        return $queries;
    }


    /**
     * Execute an INSERT with Doctrine QueryBuilder
     *
     * @return QueryBuilder       Doctrine DBAL QueryBuilder
     */
    private function prepareInsert(): QueryBuilder
    {
        $data = $this->generateFakeData();

        $queryBuilder = $this->conn->createQueryBuilder();
        $queryBuilder = $queryBuilder->insert($this->entity);
        foreach ($data as $field => $value) {
            $queryBuilder = $queryBuilder->setValue($field, ":$field");
            $queryBuilder = $queryBuilder->setParameter(":$field", $value);
        }

        return $queryBuilder;
    }
}
