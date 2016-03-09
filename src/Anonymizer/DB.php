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
        // Get the primary key
        $id = $this->getPrimaryKey($table);

        // I need to read line by line
        $res = $this->pdo->query("SELECT $id FROM $table");
        $res->setFetchMode(\PDO::FETCH_ASSOC);
        $i = 0;
        $queries = array();
        while ($row = $res->fetch()) {
            // Then write something else
            $data = $this->generateFakeData($table);

            if ($pretend === false) {
                $this->runUpdate($table, $data, "$id = '{$row['id']}'");
            }

            // return the query if verbose = true
            if ($returnResult === true) {
                $queries[] = $this->buildUpdateSQL($table, $data, "$id = '{$row['id']}'");
            }

            if (!is_null($callback)) {
                $callback(++$i);
            }
        }

        return ($returnResult === true ? $queries : '');
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
            $result = $this->pdo->query("SHOW COLUMNS FROM $table WHERE `Key` = 'Pri'");
        } catch (\Exception $e) {
            throw new \PDOException('Query Error : ' . $e->getMessage());
        }

        $primary = $result->fetchAll(\PDO::FETCH_COLUMN);
        // Didn't find a primary key !
        if (empty($primary)) {
            throw new InetAnonException("Can't find a primary key for '$table'");
        }

        return $primary[0];
    }

    /**
     * Prepare the Update with PDO
     *
     * @param string $table
     * @param array  $data
     * @param string $where
     *
     * @return \PDOStatement
     */
    protected function runUpdate($table, array $data, $where)
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
     * Build the SQL just for debug
     *
     * @param string $table
     * @param array  $data
     * @param string $where
     *
     * @return string
     */
    protected function buildUpdateSQL($table, array $data, $where)
    {
        $fieldsVals = array();
        foreach ($data as $field => $value) {
            $fieldsVals[] = "$field = IF($field IS NOT NULL, '" . addslashes($value) . "', NULL)";
        }

        $sql = "UPDATE $table SET " . implode(', ', $fieldsVals) . " WHERE $where";

        return $sql;
    }
}
