<?php
/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author Emmanuel Dyan
 * @copyright 2018 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Helper\DB;

use Doctrine\DBAL\Connection;

/**
 * Various methods to help interacting with DB servers
 */
abstract class AbstractDBHelper
{
    /**
     * Execute (or not) queries
     * @var bool
     */
    public $pretend = false;

    /**
     * DBAL connection
     * @var Connection
     */
    public $conn = null;


    /**
     * Requires a connection to work
     * @param Connection $conn
     */
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Set Pretend to true to simulate queries, false to execute interface
     * @param bool $pretend
     */
    public function setPretend(bool $pretend)
    {
        $this->pretend = $pretend;

        return $this;
    }

    /**
     * Get, for a driver options for connection (PDO)
     * @return array
     */
    public static function getDriverOptions(): array
    {
        return [];
    }

    /**
     * Set the right enclosure
     * @return string
     */
    public function getEnclosureForCSV(): string
    {
        return '"';
    }

    /**
     * Register doctrine custom types for driver
     *
     * @return void
     */
    public function registerCustomTypes(): void
    {
    }

    /**
     * Load Data from a CSV
     * @param  string  $table
     * @param  string  $filename
     * @param  array   $fields
     * @param  string  $mode  Not in used here
     * @return string
     */
    abstract public function loadData(
        string $table,
        string $filename,
        array $fields,
        string $mode
    ): string;
}
