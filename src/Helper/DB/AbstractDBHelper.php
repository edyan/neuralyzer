<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.2
 *
 * @author Emmanuel Dyan
 *
 * @copyright 2020 Emmanuel Dyan
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
     *
     * @var bool
     */
    public $pretend = false;

    /**
     * DBAL connection
     *
     * @var Connection
     */
    public $conn = null;

    /**
     * Requires a connection to work
     */
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Set Pretend to true to simulate queries, false to execute interface
     */
    public function setPretend(bool $pretend): AbstractDBHelper
    {
        $this->pretend = $pretend;

        return $this;
    }

    /**
     * Get, for a driver options for connection (PDO)
     *
     * @return array
     */
    public static function getDriverOptions(): array
    {
        return [];
    }

    /**
     * Set the right enclosure
     */
    public function getEnclosureForCSV(): string
    {
        return '"';
    }

    /**
     * Register doctrine custom types for driver
     */
    public function registerCustomTypes(): void
    {
    }

    /**
     * Load Data from a CSV
     *
     * @param  string  $fname File's name
     * @param  array   $fields
     */
    abstract public function loadData(string $table, string $fname, array $fields, string $mode): string;
}
