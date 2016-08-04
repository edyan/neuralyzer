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
    protected $pdo;

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
        $key = $this->getPrimaryKey($table);

        if ($this->whatToDoWithEntity($table) === self::TRUNCATE_TABLE) {
            $where = $this->getWhereConditionInConfig($table);
            $query = $this->runDelete($table, $where, $pretend);

            return ($returnResult === true ? array($query) : '');
        }

        // I need to read line by line if I have to update the table
        // to make sure I do update by update (slower but no other choice for now)
        $res = $this->pdo->query("SELECT $key FROM $table");

        $res->setFetchMode(\PDO::FETCH_ASSOC);
        $i = 0;
        $queries = array();
        while ($row = $res->fetch()) {
            $val = $row['id'];

            $data = $this->generateFakeData($table);

            if ($pretend === false) {
                $this->runUpdate($table, $data, "$key = '$val'");
            }

            ($returnResult === true ? array_push($queries, $this->buildUpdateSQL($table, $data, "$key = '$val'")) : '');

            if (!is_null($callback)) {
                $callback(++$i);
            }
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
     * @param string $table
     * @param array  $data
     * @param string $where
     */
    private function runUpdate($table, array $data, $where)
    {
        $fields = array();
        $values = array();
        foreach ($data as $field => $value) {
            $fields[] = "$field = IF($field  IS NOT NULL, :$field, NULL)";
            $values[":$field"] = $value;
        }

        $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE $where";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }

    /**
     * Execute the Delete with PDO
     *
     * @param string $table
     * @param string $where
     */
    private function runDelete($table, $where, $pretend)
    {
        $where = empty($where) ? '' : " WHERE $where";
        $sql = "DELETE FROM {$table}{$where}";

        if ($pretend === true) {
            return $sql;
        }

        try {
            $res = $this->pdo->query($sql);
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
