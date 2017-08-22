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

namespace Inet\Neuralyzer\Configuration;

use Inet\Neuralyzer\GuesserInterface;
use Inet\Neuralyzer\Exception\NeuralizerConfigurationException;
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
    protected $protectedCols = ['id', 'parent_id'];

    /**
     * List of tables patterns to ignore
     *
     * @var array
     */
    protected $ignoredTables = [];

    /**
     * Store the tables added to the conf
     *
     * @var array
     */
    protected $tablesInConf = [];

    /**
     * Set Change Id to ask the writer to ignore (or not) the protectedCols
     *
     * @param    bool
     */
    public function protectCols(bool $protect): Writer
    {
        $this->protectCols = $protect;

        return $this;
    }

    /**
     * Set protected cols
     *
     * @param array $cols
     */
    public function setProtectedCols(array $cols): Writer
    {
        $this->protectedCols = $cols;

        return $this;
    }

    /**
     * Set protected cols
     *
     * @param array $tables
     */
    public function setIgnoredTables(array $tables): Writer
    {
        $this->ignoredTables = $tables;

        return $this;
    }

    /**
     * Get Tables List added to the conf
     *
     * @return array
     */
    public function getTablesInConf(): array
    {
        return $this->tablesInConf;
    }

    /**
     * Generate the configuration by reading tables + cols
     *
     * @param \PDO                              $pdo
     * @param GuesserInterface $guesser
     *
     * @return array
     */
    public function generateConfFromDB(\PDO $pdo, GuesserInterface $guesser): array
    {

        // First step : get the list of tables
        $tables = $this->getTablesList($pdo);
        if (empty($tables)) {
            throw new NeuralizerConfigurationException('No tables to read in that database');
        }

        // For each table, read the cols and guess the Faker
        $data = [];
        foreach ($tables as $table) {
            $cols = $this->getColsList($pdo, $table);
            // No cols because all are ignored ?
            if (empty($cols)) {
                continue;
            }

            $data[$table]['cols'] = $this->guessColsAnonType($table, $cols, $guesser);
        }

        if (empty($data)) {
            throw new NeuralizerConfigurationException('All tables or fields have been ignored');
        }

        return ['guesser_version' => $guesser->getVersion(), 'entities' => $data];
    }

    /**
     * Save the data to the file as YAML
     *
     * @param array  $data
     * @param string $filename
     */
    public function save(array $data, string $filename)
    {
        if (!is_writeable(dirname($filename))) {
            throw new NeuralizerConfigurationException(dirname($filename) . ' is not writeable.');
        }

        file_put_contents($filename, Yaml::dump($data, 4));
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
    protected function colIgnored(string $table, string $col, bool $isPrimary): bool
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
    protected function getTablesList(\PDO $pdo): array
    {
        $result = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        $tables = $result->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $key => $val) {
            if ($this->tableIgnored($val)) {
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
    protected function getColsList(\PDO $pdo, string $table): array
    {
        $result = $pdo->query("SHOW COLUMNS FROM $table");

        $cols = $result->fetchAll(\PDO::FETCH_ASSOC);
        $colsInfo = [];

        $hasPrimary = false;
        foreach ($cols as $col) {
            // get the info that we found a primary key
            $isPrimary = ($col['Key'] === 'PRI'  ? true : false);
            if ($isPrimary) {
                $hasPrimary = true;
            }

            // If the col has to be ignored: just leave
            if ($this->colIgnored($table, $col['Field'], $isPrimary)) {
                continue;
            }

            $length = null;
            $type = $col['Type'];
            preg_match('|^(.*)\((.*)\)|', $col['Type'], $colProps);
            if (!empty($colProps)) {
                $type = $colProps[1];
                $length = $colProps[2];
            }

            $colsInfo[] = [
                'name' => $col['Field'],
                'type' => $type,
                'len'  => $length,
                'id'   => $isPrimary,
            ];
        }

        // No primary ? Exception !
        if ($hasPrimary === false) {
            throw new NeuralizerConfigurationException("Can't work with $table, it has no primary key.");
        }

        return $colsInfo;
    }

    /**
     * Guess the cols with the guesser
     *
     * @param string                            $table
     * @param array                             $cols
     * @param GuesserInterface $guesser
     *
     * @return array
     */
    protected function guessColsAnonType(string $table, array $cols, GuesserInterface $guesser): array
    {
        $mapping = [];
        foreach ($cols as $props) {
            $mapping[$props['name']] = $guesser->mapCol($table, $props['name'], $props['type'], $props['len']);
        }

        return $mapping;
    }

    /**
     * Check if that table has to be ignored
     *
     * @param string $table
     *
     * @return bool
     */
    protected function tableIgnored(string $table): bool
    {
        foreach ($this->ignoredTables as $ignoredTable) {
            if (preg_match("/^$ignoredTable\$/", $table)) {
                return true;
            }
        }

        return false;
    }
}
