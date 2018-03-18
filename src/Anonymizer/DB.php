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

namespace Inet\Neuralyzer\Anonymizer;

use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Query\QueryBuilder;
use Inet\Neuralyzer\Exception\NeuralizerException;

/**
 * DB Anonymizer
 */
class DB extends AbstractAnonymizer
{
    /**
     * Zend DB Adapter
     *
     * @var \Doctrine\DBAL\Connection
     */
    private $conn;

    /**
     * Constructor
     *
     * @param $params   Parameters to send to Doctrine DB
     */
    public function __construct(array $params)
    {
        $this->conn = \Doctrine\DBAL\DriverManager::getConnection($params, new DbalConfiguration());
    }

    /**
     * Process an entity by reading / writing to the DB
     *
     * @param string        $table
     * @param callable|null $callback
     * @param bool          $pretend
     * @param bool          $returnResult
     *
     * @return void|array
     */
    public function processEntity(
        string $table,
        callable $callback = null,
        bool $pretend = true,
        bool $returnResult = false
    ) {
        $schema = $this->conn->getSchemaManager();
        if ($schema->tablesExist($table) === false) {
            throw new NeuralizerException("Table $table does not exist");
        }

        $queries = [];
        $actionsOnThatEntity = $this->whatToDoWithEntity($table);

        if ($actionsOnThatEntity & self::TRUNCATE_TABLE) {
            $where = $this->getWhereConditionInConfig($table);
            $query = $this->runDelete($table, $where, $pretend);
            ($returnResult === true ? array_push($queries, $query) : '');
        }

        if ($actionsOnThatEntity & self::UPDATE_TABLE) {
            // I need to read line by line if I have to update the table
            // to make sure I do update by update (slower but no other choice for now)
            $rowNum = 0;

            $key = $this->getPrimaryKey($table);
            $tableCols = $this->getTableCols($table);

            $queryBuilder = $this->conn->createQueryBuilder();
            $rows = $queryBuilder->select($key)->from($table)->execute();

            foreach ($rows as $row) {
                $val = $row[$key];
                $data = $this->generateFakeData($table, $tableCols);
                $queryBuilder = $this->prepareUpdate($table, $data, $key, $val);

                ($returnResult === true ? array_push($queries, $this->getRawSQL($queryBuilder)) : '');

                if ($pretend === false) {
                    $this->runUpdate($queryBuilder, $table);
                }

                if (!is_null($callback)) {
                    $callback(++$rowNum);
                }
            }
        }

        return $queries;
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
     * Identify the primary key for a table
     *
     * @param string $table
     *
     * @return string Field's name
     */
    protected function getPrimaryKey(string $table)
    {
        $schema = $this->conn->getSchemaManager();
        $tableDetails = $schema->listTableDetails($table);
        if ($tableDetails->hasPrimaryKey() === false) {
            throw new NeuralizerException("Can't find a primary key for '$table'");
        }

        return $tableDetails->getPrimaryKey()->getColumns()[0];
    }


    /**
     * Identify the primary key for a table
     *
     * @param string $table
     *
     * @return array $cols
     */
    protected function getTableCols(string $table)
    {
        $schema = $this->conn->getSchemaManager();
        $tableCols = $schema->listTableColumns($table);
        $cols = [];
        foreach ($tableCols as $col) {
            $cols[$col->getName()] = [
                'length' => $col->getLength(),
            ];
        }

        return $cols;
    }


    /**
     * Execute the Update with PDO
     *
     * @param  string $table      Name of the table
     * @param  array  $data       Array of fields => value to update the table
     * @param  string $primaryKey
     * @param  string $val        Primary Key's Value
     * @return string             Doctrine DBAL QueryBuilder
     */
    private function prepareUpdate(string $table, array $data, string $primaryKey, $val)
    {
        $queryBuilder = $this->conn->createQueryBuilder();
        $queryBuilder = $queryBuilder->update($table);
        foreach ($data as $field => $value) {
            $condition = "(CASE $field WHEN NULL THEN NULL ELSE :$field END)";
            $queryBuilder = $queryBuilder->set($field, $condition);
            $queryBuilder = $queryBuilder->setParameter(":$field", $value);
        }
        $queryBuilder = $queryBuilder->where("$primaryKey = :$primaryKey");
        $queryBuilder = $queryBuilder->setParameter(":$primaryKey", $val);

        return $queryBuilder;
    }


    /**
     * Execute the Update with PDO
     *
     * @param QueryBuilder $queryBuilder
     * @param string                     $table          Name of the table
     */
    private function runUpdate(QueryBuilder $queryBuilder, string $table)
    {
        try {
            $queryBuilder->execute();
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            throw new NeuralizerException("Problem anonymizing $table (" . $e->getMessage() . ')');
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
            $sql = str_replace($parameter, "'$value'", $sql);
        }

        return $sql;
    }


    /**
     * Execute the Delete with PDO
     *
     * @param string $table
     * @param string $where
     * @param bool   $pretend
     *
     * @return string
     */
    private function runDelete(string $table, string $where, bool $pretend): string
    {
        $queryBuilder = $this->conn->createQueryBuilder();
        $queryBuilder = $queryBuilder->delete($table);
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
}
