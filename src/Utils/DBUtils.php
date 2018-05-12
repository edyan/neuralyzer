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

namespace Edyan\Neuralyzer\Utils;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Edyan\Neuralyzer\Exception\NeuralizerException;

/**
 * A few generic methods to help interacting with DB
 */
class DBUtils
{
    /**
     * Doctrine DBAL Connection
     * @var Connection
     */
    private $conn;

    /**
     * Set the connection (dependency)
     * @param Connection $conn
     */
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }


    /**
     * Do a simple count for a table
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
     * @param  string $table
     * @return string Field's name
     */
    public function getPrimaryKey(string $table): string
    {
        $schema = $this->conn->getSchemaManager();
        $tableDetails = $schema->listTableDetails($table);
        if ($tableDetails->hasPrimaryKey() === false) {
            throw new NeuralizerException("Can't find a primary key for '{$table}'");
        }

        return $tableDetails->getPrimaryKey()->getColumns()[0];
    }


    /**
     * Retrieve columns list for a table with type and length
     * @param  string $table
     * @return array $cols
     */
    public function getTableCols(string $table): array
    {
        $schema = $this->conn->getSchemaManager();
        $tableCols = $schema->listTableColumns($table);
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
    public function getRawSQL(QueryBuilder $queryBuilder)
    {
        $sql = $queryBuilder->getSQL();
        foreach ($queryBuilder->getParameters() as $parameter => $value) {
            $sql = str_replace($parameter, "'$value'", $sql);
        }

        return $sql;
    }


    public function assertTableExists(string $table)
    {
        $schema = $this->conn->getSchemaManager();
        if ($schema->tablesExist($table) === false) {
            throw new NeuralizerException("Table $table does not exist");
        }
    }


    /**
     * Build the condition by casting the value if needed
     *
     * @param  string $field
     * @return string
     */
    public function getCondition(string $field, array $fieldConf): string
    {
        $type = strtolower($fieldConf['type']);
        $unsigned = $fieldConf['unsigned'];

        $integerCast = $this->getIntegerCast($unsigned);

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
    private function getIntegerCast(bool $unsigned): string
    {
        $driver = $this->conn->getDriver();
        if (strpos($driver->getName(), 'mysql')) {
            return $unsigned === true ? 'UNSIGNED' : 'SIGNED';
        }

        return 'INTEGER';
    }
}
