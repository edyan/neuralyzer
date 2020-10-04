<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.2
 *
 * @author    Emmanuel Dyan
 * @author    Rémi Sauvat
 *
 * @copyright 2020 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Utils;

use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Edyan\Neuralyzer\Exception\NeuralyzerException;
use Edyan\Neuralyzer\Helper\DB as DBHelper;

/**
 * A few generic methods to help interacting with DB
 */
class DBUtils
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
     * Set the connection (dependency)
     *
     * @param array $params
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function configure(array $params): void
    {
        $dbHelperClass = DBHelper\DriverGuesser::getDBHelper($params['driver']);

        // Set specific options
        $params['driverOptions'] = $dbHelperClass::getDriverOptions();
        $this->conn = DriverManager::getConnection($params, new DbalConfiguration());
        $this->conn->setFetchMode(\Doctrine\DBAL\FetchMode::ASSOCIATIVE);

        $this->dbHelper = new $dbHelperClass($this->conn);
    }

    public function getDBHelper(): DBHelper\AbstractDBHelper
    {
        return $this->dbHelper;
    }

    /**
     * Get Doctrine Connection
     */
    public function getConn(): Connection
    {
        if (empty($this->conn)) {
            throw new \RuntimeException('Make sure you have called $dbUtils->configure($params) first');
        }
        return $this->conn;
    }

    /**
     * Do a simple count for a table
     */
    public function countResults(string $table): int
    {
        $queryBuilder = $this->conn->createQueryBuilder();
        $rows = $queryBuilder->select('COUNT(1)')->from($table)->execute();

        return (int) $rows->fetch(\Doctrine\DBAL\FetchMode::NUMERIC)[0];
    }

    /**
     * Identify the primary key for a table
     *
     * @throws NeuralyzerException
     */
    public function getPrimaryKey(string $table): string
    {
        $schema = $this->conn->getSchemaManager();
        $tableDetails = $schema->listTableDetails($table);
        if ($tableDetails->hasPrimaryKey() === false) {
            throw new NeuralyzerException("Can't find a primary key for '{$table}'");
        }

        return $tableDetails->getPrimaryKey()->getColumns()[0];
    }

    /**
     * Retrieve columns list for a table with type and length
     *
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
                'type' => $col->getType(),
                'unsigned' => $col->getUnsigned(),
            ];
        }

        return $cols;
    }

    /**
     * To debug, build the final SQL (can be approximate)
     */
    public function getRawSQL(QueryBuilder $queryBuilder): string
    {
        $sql = $queryBuilder->getSQL();
        foreach ($queryBuilder->getParameters() as $parameter => $value) {
            $sql = str_replace($parameter, "'${value}'", $sql);
        }

        return $sql;
    }

    /**
     * Make sure a table exists
     *
     * @param  string $table [description]
     *
     * @throws NeuralyzerException
     */
    public function assertTableExists(string $table): void
    {
        if ($this->conn->getSchemaManager()->tablesExist([$table]) === false) {
            throw new NeuralyzerException("Table ${table} does not exist");
        }
    }

    /**
     * Build the condition by casting the value if needed
     *
     * @param  array  $fieldConf Various values about the field
     */
    public function getCondition(string $field, array $fieldConf): string
    {
        $type = ltrim(strtolower((string) $fieldConf['type']), '\\');
        $unsigned = $fieldConf['unsigned'];

        $integerCast = $this->getIntegerCast($unsigned);

        $condition = "(CASE ${field} WHEN NULL THEN NULL ELSE :${field} END)";

        $typeToCast = [
            'date' => 'DATE',
            'datetime' => 'DATE',
            'time' => 'TIME',
            'smallint' => $integerCast,
            'integer' => $integerCast,
            'bigint' => $integerCast,
            'float' => 'DECIMAL',
            'decimal' => 'DECIMAL',
        ];

        // No cast required
        if (! array_key_exists($type, $typeToCast)) {
            return $condition;
        }

        return "CAST(${condition} AS {$typeToCast[$type]})";
    }

    /**
     * Gives an empty value according to the field (example : numeric = 0)
     *
     * @return mixed
     */
    public function getEmptyValue( $type)
    {
        $type = strtolower($type);
        $typeToValue = [
            'date' => '1970-01-01',
            'datetime' => '1970-01-01 00:00:00',
            'time' => '00:00:00',
            'smallint' => 0,
            'integer' => 0,
            'bigint' => 0,
            'float' => 0,
            'decimal' => 0,
        ];

        // Value is simply an empty string
        if (! array_key_exists($type, $typeToValue)) {
            return '';
        }

        return $typeToValue[$type];
    }

    /**
     * Get the right CAST for an INTEGER
     */
    private function getIntegerCast(bool $unsigned): string
    {
        $driver = $this->conn->getDriver()->getName();
        if ($driver === 'pdo_mysql') {
            return $unsigned === true ? 'UNSIGNED INTEGER' : 'SIGNED INTEGER';
        }

        return 'INTEGER';
    }
}
