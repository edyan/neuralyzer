<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.2
 *
 * @package edyan/neuralyzer
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 *
 * @copyright 2020 Emmanuel Dyan
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Configuration;

use Edyan\Neuralyzer\Exception\NeuralyzerConfigurationException;
use Edyan\Neuralyzer\GuesserInterface;
use Edyan\Neuralyzer\Utils\DBUtils;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration Writer
 *
 * @package edyan/neuralyzer
 */
class Writer
{
    /**
     * List of tables patterns to ignore
     *
     * @var array
     */
    protected $ignoredTables = [];

    /**
     * Should I protect the cols ? That will also protect the Primary Keys
     *
     * @var bool
     */
    protected $protectCols = true;

    /**
     * List the cols to protected. Could contain regexp
     *
     * @var array
     */
    protected $protectedCols = ['id', 'parent_id'];

    /**
     * Store the tables added to the conf
     *
     * @var array
     */
    protected $tablesInConf = [];

    /**
     * Doctrine connexion handler
     *
     * @var \Doctrine\DBAL\Connection
     */
    private $conn;

    /**
     * Generate the configuration by reading tables + cols
     *
     * @return array
     *
     * @throws NeuralyzerConfigurationException
     */
    public function generateConfFromDB(DBUtils $dbUtils, GuesserInterface $guesser): array
    {
        $this->conn = $dbUtils->getConn();

        // First step : get the list of tables
        $tables = $this->getTablesList();
        if (empty($tables)) {
            throw new NeuralyzerConfigurationException('No tables to read in that database');
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
            throw new NeuralyzerConfigurationException('All tables or fields have been ignored');
        }

        $config = [
            'entities' => $data,
        ];

        $processor = new Processor();

        return $processor->processConfiguration(new ConfigDefinition(), [$config]);
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
     * Set a flat to protect cols (Primary Key is protected by default)
     */
    public function protectCols(bool $protectCols): Writer
    {
        $this->protectCols = $protectCols;

        return $this;
    }

    /**
     * Save the data to the file as YAML
     *
     * @param array $data
     *
     * @throws NeuralyzerConfigurationException
     */
    public function save(array $data, string $filename): void
    {
        if (! is_writeable(dirname($filename))) {
            throw new NeuralyzerConfigurationException(dirname($filename) . ' is not writable.');
        }

        file_put_contents($filename, Yaml::dump($data, 4));
    }

    /**
     * Set protected cols
     *
     * @param array $ignoredTables
     */
    public function setIgnoredTables(array $ignoredTables): Writer
    {
        $this->ignoredTables = $ignoredTables;

        return $this;
    }

    /**
     * Set protected cols
     *
     * @param array $protectedCols
     */
    public function setProtectedCols(array $protectedCols): Writer
    {
        $this->protectedCols = $protectedCols;

        return $this;
    }

    /**
     * Check if that col has to be ignored
     */
    protected function colIgnored(string $table, string $col): bool
    {
        if ($this->protectCols === false) {
            return false;
        }

        foreach ($this->protectedCols as $protectedCol) {
            if (preg_match("/^${protectedCol}\$/", $table . '.' . $col)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the cols lists from a connection + table
     *
     * @return array
     *
     * @throws NeuralyzerConfigurationException
     */
    protected function getColsList(string $table): array
    {
        $schema = $this->conn->getSchemaManager();
        $tableDetails = $schema->listTableDetails($table);
        // No primary ? Exception !
        if ($tableDetails->hasPrimaryKey() === false) {
            throw new NeuralyzerConfigurationException("Can't work with ${table}, it has no primary key.");
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
                'type' => ltrim(strtolower((string) $col->getType()), '\\'),
                'len' => $col->getLength(),
            ];
        }

        return $colsInfo;
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
     * Guess the cols with the guesser
     *
     * @param  array            $cols
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
     */
    protected function tableIgnored(string $table): bool
    {
        foreach ($this->ignoredTables as $ignoredTable) {
            if (preg_match("/^${ignoredTable}\$/", $table)) {
                return true;
            }
        }

        return false;
    }
}
