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

namespace Inet\Neuralyzer\Configuration;

use Inet\Neuralyzer\Exception\InetAnonConfigurationException;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration Writer
 */
class Writer
{
    /**
     * Should I protect the cols ? That will also protect the Primary Keys
     *
     * @var boolean
     */
    protected $protectCols = true;

    /**
     * List the cols to protected. Could containe regexp
     *
     * @var array
     */
    protected $protectedCols = array('id', 'parent_id');

    /**
     * List of tables patterns to ignore
     *
     * @var array
     */
    protected $ignoredTables = array();

    /**
     * Store the tables added to the conf
     *
     * @var array
     */
    protected $tablesInConf = array();

    /**
     * Set Change Id to ask the writer to ignore (or not) the protectedCols
     *
     * @param    bool
     */
    public function protectCols($value)
    {
        $this->protectCols = $value;

        return $this;
    }

    /**
     * Set protected cols
     *
     * @param array $cols
     */
    public function setProtectedCols(array $cols)
    {
        $this->protectedCols = $cols;

        return $this;
    }

    /**
     * Set protected cols
     *
     * @param array $tables
     */
    public function setIgnoredTables(array $tables)
    {
        $this->ignoredTables = $tables;

        return $this;
    }

    /**
     * Get Tables List added to the conf
     *
     * @return array
     */
    public function getTablesInConf()
    {
        return $this->tablesInConf;
    }

    /**
     * Generate the configuration by reading tables + cols
     *
     * @param \PDO                              $pdo
     * @param \Inet\Neuralyzer\GuesserInterface $guesser
     *
     * @return array
     */
    public function generateConfFromDB(\PDO $pdo, \Inet\Neuralyzer\GuesserInterface $guesser)
    {

        // First step : get the list of tables
        $tables = $this->getTablesList($pdo);
        if (empty($tables)) {
            throw new InetAnonConfigurationException('No tables to read in that database');
        }

        // For each table, read the cols and guess the Faker
        $data = array();
        foreach ($tables as $table) {
            $cols = $this->getColsList($pdo, $table);
            // No cols because all are ignored ?
            if (empty($cols)) {
                continue;
            }

            $data[$table]['cols'] = $this->guessColsAnonType($table, $cols, $guesser);
        }

        if (empty($data)) {
            throw new InetAnonConfigurationException('All tables or fields have been ignored');
        }

        return array('guesser_version' => $guesser->getVersion(), 'entities' => $data);
    }

    /**
     * Save the data to the file as YAML
     *
     * @param array  $data
     * @param string $filename
     */
    public function save(array $data, $filename)
    {
        if (!is_writeable(dirname($filename))) {
            throw new InetAnonConfigurationException(dirname($filename) . ' is not writeable.');
        }

        file_put_contents($filename, Yaml::dump($data, 4));
    }

    /**
     * Check if that table has to be ignored
     *
     * @param string $table
     *
     * @return bool
     */
    protected function ignoreTable($table)
    {
        foreach ($this->ignoredTables as $ignoredTable) {
            if (preg_match("/^$ignoredTable\$/", $table)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if that col has to be ignored
     *
     * @param string $table
     * @param string $col
     * @param bool   $isPrimary
     *
     * @return bool
     */
    protected function ignoreCol($table, $col, $isPrimary)
    {
        if ($this->protectCols === false) {
            return false;
        }

        foreach ($this->protectedCols as $protectedCol) {
            if ($isPrimary || preg_match("/^$protectedCol\$/", $table. '.' . $col)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the table lists from a connection
     *
     * @param \PDO $pdo
     *
     * @return array
     */
    protected function getTablesList(\PDO $pdo)
    {
        $result = $pdo->query('SHOW TABLES');

        $tables = $result->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $key => $val) {
            if ($this->ignoreTable($val)) {
                unset($tables[$key]);
                continue;
            }

            $this->tablesInConf[] = $val;
        }

        return array_values($tables);
    }

    /**
     * Get the cols lists from a connection + table
     *
     * @param \PDO   $pdo
     * @param string $table
     *
     * @return array
     */
    protected function getColsList(\PDO $pdo, $table)
    {
        $result = $pdo->query("SHOW COLUMNS FROM $table");

        $cols = $result->fetchAll(\PDO::FETCH_ASSOC);
        $colsInfo = array();

        $primary = false;
        foreach ($cols as $col) {
            // get the info that we found a primary key
            $isPrimary = ($col['Key'] === 'PRI'  ? true : false);
            if ($isPrimary) {
                $primary = true;
            }

            // If the col has to be ignored: just leave
            if ($this->ignoreCol($table, $col['Field'], $isPrimary)) {
                continue;
            }

            $length = null;
            $type = $col['Type'];
            preg_match('|^(.*)\((.*)\)|', $col['Type'], $colProps);
            if (!empty($colProps)) {
                $type = $colProps[1];
                $length = $colProps[2];
            }

            $colsInfo[] = array(
                'name' => $col['Field'],
                'type' => $type,
                'len'  => $length,
                'id'   => $isPrimary,
            );
        }

        // No primary ? Exception !
        if ($primary === false) {
            throw new InetAnonConfigurationException("Not able to work with $table, it has no primary key.");
        }

        return $colsInfo;
    }

    /**
     * Guess the cols with the guesser
     *
     * @param string                            $table
     * @param array                             $cols
     * @param \Inet\Neuralyzer\GuesserInterface $guesser
     *
     * @return array
     */
    protected function guessColsAnonType($table, array $cols, \Inet\Neuralyzer\GuesserInterface $guesser)
    {
        $mapping = array();
        foreach ($cols as $colProps) {
            $mapping[$colProps['name']] = $guesser->mapCol(
                $table,
                $colProps['name'],
                $colProps['type'],
                $colProps['len']
            );
        }

        return $mapping;
    }
}
