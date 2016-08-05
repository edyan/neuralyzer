<?php
/**
 * Inet Data Anonymization
 *
 * PHP Version 5.3 -> 7.0
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2005-2015 iNet Process
 *
 * @package inetprocess/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link http://www.inetprocess.com
 */

namespace Inet\Neuralyzer\Anonymizer;

use Inet\Neuralyzer\Exception\InetAnonException;

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
    public function processEntity($table, $callback = null, $pretend = true, $returnResult = false)
    {
        $queries = array();
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
                $val = $row['id'];
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
    protected function getPrimaryKey($table)
    {
        try {
            $res = $this->pdo->query("SHOW COLUMNS FROM $table WHERE `Key` = 'Pri'");
        } catch (\Exception $e) {
            throw new \PDOException('Query Error : ' . $e->getMessage());
        }

        $primary = $res->fetchAll(\PDO::FETCH_COLUMN);
        // Didn't find a primary key !
        if (empty($primary)) {
            throw new InetAnonException("Can't find a primary key for '$table'");
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
    private function runUpdate($table, array $data, $primaryKey, $val)
    {
        if (is_null($this->preparedStmt)) {
            $this->prepareStmt($table, $primaryKey, array_keys($data));
        }

        $values = array(":$primaryKey" => $val);
        foreach ($data as $field => $value) {
            $values[":$field"] = $value;
        }
        $this->preparedStmt->execute($values);
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
    private function prepareStmt($table, $primaryKey, array $fieldNames)
    {
        $fields = array();
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
     */
    private function runDelete($table, $where, $pretend)
    {
        $where = empty($where) ? '' : " WHERE $where";
        $sql = "DELETE FROM {$table}{$where}";

        if ($pretend === true) {
            return $sql;
        }

        try {
            $this->pdo->query($sql);
        } catch (\Exception $e) {
            throw new \PDOException('Query Error : ' . $e->getMessage());
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
    private function buildUpdateSQL($table, array $data, $where)
    {
        $fieldsVals = array();
        foreach ($data as $field => $value) {
            $fieldsVals[] = "$field = IF($field IS NOT NULL, '" . addslashes($value) . "', NULL)";
        }

        $sql = "UPDATE $table SET " . implode(', ', $fieldsVals) . " WHERE $where";

        return $sql;
    }
}
