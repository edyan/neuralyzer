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
     *
     * @var \Doctrine\DBAL\Connection
     */
    private $conn;


    /**
     * Init connection
     *
     * @param $params   Parameters to send to Doctrine DB
     */
    public function __construct(array $params)
    {
        $this->conn = DbalDriverManager::getConnection($params, new DbalConfiguration());
    }


    /**
     * Get Doctrine Connection
     * @return Doctrine\DBAL\Connection
     */
    public function getConn()
    {
        return $this->conn;
    }


    /**
     * Process an entity by reading / writing to the DB
     *
     * @param string        $entity
     * @param callable|null $callback
     * @param bool          $pretend
     * @param bool          $returnRes
     *
     * @return void|array
     */
    public function processEntity(
        string $entity,
        callable $callback = null,
        bool $pretend = true,
        bool $returnRes = false
    ): array {
        $schema = $this->conn->getSchemaManager();
        if ($schema->tablesExist($entity) === false) {
            throw new NeuralizerException("Table $entity does not exist");
        }

        $this->entity = $entity;
        $queries = [];

        $actionsOnThatEntity = $this->whatToDoWithEntity();

        if ($actionsOnThatEntity & self::TRUNCATE_TABLE) {
            $where = $this->getWhereConditionInConfig();
            $query = $this->runDelete($where, $pretend);
            ($returnRes === true ? array_push($queries, $query) : '');
        }

        if ($actionsOnThatEntity & self::UPDATE_TABLE) {
            // I need to read line by line if I have to update the table
            // to make sure I do update by update (slower but no other choice for now)
            $rowNum = 0;

            $key = $this->getPrimaryKey();
            $this->entityCols = $this->getTableCols();

            $queryBuilder = $this->conn->createQueryBuilder();
            $rows = $queryBuilder->select($key)->from($this->entity)->execute();

            foreach ($rows as $row) {
                $data = $this->generateFakeData();
                $queryBuilder = $this->prepareUpdate($data, $key, $row[$key]);

                ($returnRes === true ? array_push($queries, $this->getRawSQL($queryBuilder)) : '');

                if ($pretend === false) {
                    $this->runUpdate($queryBuilder);
                }

                if (!is_null($callback)) {
                    $callback(++$rowNum);
                }
            }
        }

        return $queries;
    }


    /**
     * Identify the primary key for a table
     *
     * @return string Field's name
     */
    private function getPrimaryKey()
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
    private function getTableCols()
    {
        $schema = $this->conn->getSchemaManager();
        $tableCols = $schema->listTableColumns($this->entity);
        $cols = [];
        foreach ($tableCols as $col) {
            $cols[$col->getName()] = [
                'length' => $col->getLength(),
                'type'   => $col->getType(),
            ];
        }

        return $cols;
    }


    /**
     * Execute the Update with PDO
     *
     * @param  array  $data           Array of fields => value to update the table
     * @param  string $primaryKey
     * @param  string $primaryKeyVal  Primary Key's Value
     * @return string                 Doctrine DBAL QueryBuilder
     */
    private function prepareUpdate(array $data, string $primaryKey, $primaryKeyVal)
    {
        $queryBuilder = $this->conn->createQueryBuilder();
        $queryBuilder = $queryBuilder->update($this->entity);
        foreach ($data as $field => $value) {
            $type = $this->entityCols[$field]['type'];
            $queryBuilder = $queryBuilder->set($field, $this->getCondition($field, $type));
            $queryBuilder = $queryBuilder->setParameter(":$field", $value, $type);
        }
        $queryBuilder = $queryBuilder->where("$primaryKey = :$primaryKey");
        $queryBuilder = $queryBuilder->setParameter(":$primaryKey", $primaryKeyVal);

        return $queryBuilder;
    }


    /**
     * Execute the Update with PDO
     *
     * @param QueryBuilder $queryBuilder
     */
    private function runUpdate(QueryBuilder $queryBuilder)
    {
        try {
            $queryBuilder->execute();
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            throw new NeuralizerException(
                "Problem anonymizing {$this->entity} (" . $e->getMessage() . ')'
            );
        }
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
            if (is_object($value)) {
                $value = '{object}';
            }
            $sql = str_replace($parameter, "'$value'", $sql);
        }

        return $sql;
    }


    /**
     * Execute the Delete with PDO
     *
     * @param string $where
     * @param bool   $pretend
     *
     * @return string
     */
    private function runDelete(string $where, bool $pretend): string
    {
        $queryBuilder = $this->conn->createQueryBuilder();
        $queryBuilder = $queryBuilder->delete($this->entity);
        if (!empty($where)) {
            $queryBuilder = $queryBuilder->where($where);
        }
        $sql = $queryBuilder->getSQL();

        if ($pretend === true) {
            return $sql;
        }

        try {
            $queryBuilder->execute();
        } catch (\Exception $e) {
            throw new NeuralizerException('Query DELETE Error (' . $e->getMessage() . ')');
        }

        return $sql;
    }

    private function getCondition(string $field, string $type)
    {
        $type = strtolower($type);
        $condition = "(CASE $field WHEN NULL THEN NULL ELSE :$field END)";
        switch ($type) {
            case 'date':
                return "CAST($condition as DATE)";
            case 'datetime':
                return "CAST($condition as DATETIME)";
            case 'time':
                return "CAST($condition as TIME)";
            case 'decimal':
            case 'float':
                return "CAST($condition as DECIMAL)";
        }

        return $condition;
    }
}
