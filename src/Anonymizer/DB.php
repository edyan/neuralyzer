<?php
/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.0
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2017 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Inet\Neuralyzer\Anonymizer;

use Inet\Neuralyzer\Exception\NeuralizerException;

/**
 * DB Anonymizer
 */
class DB extends AbstractAnonymizer
{
    /**
     * The PDO connection
     *
     * @var \PDO
     */
    private $pdo;

    /**
     * Prepared statement is stored for the update, to make the queries faster
     *
     * @var \PDOStatement
     */
    private $preparedStmt;

    /**
     * Constructor
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
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
            $i = 0;
            $this->preparedStmt = null;

            $key = $this->getPrimaryKey($table);
            $res = $this->pdo->query("SELECT $key FROM $table");
            $res->setFetchMode(\PDO::FETCH_ASSOC);

            $this->pdo->beginTransaction();

            while ($row = $res->fetch()) {
                $val = $row[$key];
                $data = $this->generateFakeData($table);

                if ($pretend === false) {
                    $this->runUpdate($table, $data, $key, $val);
                }
                ($returnResult === true ? array_push($queries, $this->buildUpdateSQL($table, $data, "$key = '$val'")) : '');

                if (!is_null($callback)) {
                    $callback(++$i);
                }
            }

            // Commit, even if I am in pretend (that will do ... nothing)
            $this->pdo->commit();
        }

        return $queries;
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
        try {
            $res = $this->pdo->query("SHOW COLUMNS FROM $table WHERE `Key` = 'Pri'");
        } catch (\Exception $e) {
            throw new NeuralizerException('Query Error : ' . $e->getMessage());
        }

        $primary = $res->fetchAll(\PDO::FETCH_COLUMN);
        // Didn't find a primary key !
        if (empty($primary)) {
            throw new NeuralizerException("Can't find a primary key for '$table'");
        }

        return $primary[0];
    }

    /**
     * Execute the Update with PDO
     *
     * @param string $table      Name of the table
     * @param array  $data       Array of fields => value to update the table
     * @param string $primaryKey
     * @param string $val        Primary Key's Value
     */
    private function runUpdate(string $table, array $data, string $primaryKey, $val)
    {
        if (is_null($this->preparedStmt)) {
            $this->prepareStmt($table, $primaryKey, array_keys($data));
        }

        $values = [":$primaryKey" => $val];
        foreach ($data as $field => $value) {
            $values[":$field"] = $value;
        }

        try {
            $this->preparedStmt->execute($values);
        } catch (\PDOException $e) {
            $this->pdo->rollback();
            throw new NeuralizerException("Problem anonymizing $table (" . $e->getMessage() . ')');
        }
    }

    /**
     * Prepare the statement if asked
     *
     * @param string $table
     * @param string $primaryKey
     * @param array  $fieldNames
     *
     * @return \PDOStatement
     */
    private function prepareStmt(string $table, string $primaryKey, array $fieldNames)
    {
        $fields = [];
        foreach ($fieldNames as $field) {
            $fields[] = "$field = IF($field IS NOT NULL, :$field, NULL)";
        }
        $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE $primaryKey = :$primaryKey";
        $this->preparedStmt = $this->pdo->prepare($sql);
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
        $where = empty($where) ? '' : " WHERE $where";
        $sql = "DELETE FROM {$table}{$where}";

        if ($pretend === true) {
            return $sql;
        }

        try {
            $this->pdo->query($sql);
        } catch (\Exception $e) {
            throw new NeuralizerException('Query DELETE Error (' . $e->getMessage() . ')');
        }

        return $sql;
    }

    /**
     * Build the SQL just for debug
     *
     * @param string $table
     * @param array  $data
     * @param string $where
     *
     * @return string
     */
    private function buildUpdateSQL(string $table, array $data, string $where): string
    {
        $fieldsVals = [];
        foreach ($data as $field => $value) {
            $fieldsVals[] = "$field = IF($field IS NOT NULL, '" . $this->pdo->quote($value) . "', NULL)";
        }

        $sql = "UPDATE $table SET " . implode(', ', $fieldsVals) . " WHERE $where";

        return $sql;
    }
}
