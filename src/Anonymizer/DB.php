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
                $queryBuilder = $this->prepareUpdate($key, $row[$key]);

                ($returnRes === true ? array_push($queries, $this->getRawSQL($queryBuilder)) : '');

                if ($pretend === false) {
                    $queryBuilder->execute();
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
                'unsigned' => $col->getUnsigned(),
            ];
        }

        return $cols;
    }


    /**
     * Execute the Update with Doctrine QueryBuilder
     *
     * @param  string $primaryKey
     * @param  string $primaryKeyVal  Primary Key's Value
     * @return string                 Doctrine DBAL QueryBuilder
     */
    private function prepareUpdate(string $primaryKey, $primaryKeyVal): QueryBuilder
    {
        $data = $this->generateFakeData();

        $queryBuilder = $this->conn->createQueryBuilder();
        $queryBuilder = $queryBuilder->update($this->entity);
        foreach ($data as $field => $value) {
            $queryBuilder = $queryBuilder->set($field, $this->getCondition($field));
            $queryBuilder = $queryBuilder->setParameter(":$field", $value);
        }
        $queryBuilder = $queryBuilder->where("$primaryKey = :$primaryKey");
        $queryBuilder = $queryBuilder->setParameter(":$primaryKey", $primaryKeyVal);

        return $queryBuilder;
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
}
