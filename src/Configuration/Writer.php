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

namespace Inet\Neuralyzer\Configuration;

use Inet\Neuralyzer\GuesserInterface;
use Inet\Neuralyzer\Anonymizer\DB;
use Inet\Neuralyzer\Exception\NeuralizerConfigurationException;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration Writer
 */
class Writer
{
    /**
     * Doctrine conection handler
     * @var \Doctrine\DBAL\Connection
     */
    private $conn;

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
     * @param DB               $db
     * @param GuesserInterface $guesser
     *
     * @return array
     */
    public function generateConfFromDB(DB $db, GuesserInterface $guesser): array
    {
        $this->conn = $db->getConn();

        // First step : get the list of tables
        $tables = $this->getTablesList();
        if (empty($tables)) {
            throw new NeuralizerConfigurationException('No tables to read in that database');
        }

        // For each table, read the cols and guess the Faker
        $data = [];
        foreach ($tables as $table) {
            $cols = $this->getColsList($table);
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
    protected function colIgnored(string $table, string $col): bool
    {
        if ($this->protectCols === false) {
            return false;
        }

        foreach ($this->protectedCols as $protectedCol) {
            if (preg_match("/^$protectedCol\$/", $table. '.' . $col)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the table lists from a connection
     *
     * @return array
     */
    protected function getTablesList(): array
    {
        $schemaManager = $this->conn->getSchemaManager();

        $tablesInDB = $schemaManager->listTables();
        $tables = [];
        foreach ($tablesInDB as $table) {
            if ($this->tableIgnored($table->getName())) {
                continue;
            }

            $tables[] = $this->tablesInConf[] = $table->getName();
        }

        return array_values($tables);
    }

    /**
     * Get the cols lists from a connection + table
     *
     * @param string $table
     *
     * @return array
     */
    protected function getColsList(string $table): array
    {
        $schema = $this->conn->getSchemaManager();
        $tableDetails = $schema->listTableDetails($table);
        // No primary ? Exception !
        if ($tableDetails->hasPrimaryKey() === false) {
            throw new NeuralizerConfigurationException("Can't work with $table, it has no primary key.");
        }
        $primaryKey = $tableDetails->getPrimaryKey()->getColumns()[0];

        $cols = $schema->listTableColumns($table);
        $colsInfo = [];
        foreach ($cols as $col) {
            // If the col has to be ignored: just leave
            if ($primaryKey === $col->getName() || $this->colIgnored($table, $col->getName())) {
                continue;
            }

            $colsInfo[] = [
                'name' => $col->getName(),
                'type' => strtolower((string)$col->getType()),
                'len'  => $col->getLength()
            ];
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
