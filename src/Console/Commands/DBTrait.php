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

namespace Inet\Neuralyzer\Console\Commands;

/**
 * Trait with basic function to connect to DB
 */
trait DBTrait
{
    /**
     * PDO Object Initialized
     * @var \PDO
     */
    private $pdo;

    /**
     * Initialize a PDO Object
     */
    private function connectToDB(string $host, $dbName, string $user, $password)
    {
        // Throw an exception immediately if we dont have the required DB parameter
        if (empty($dbName)) {
            throw new \InvalidArgumentException('Database name is required (--db)');
        }

        try {
            $this->pdo = new \PDO("mysql:dbname=$dbName;host=" . $host, $user, $password);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            throw new \RuntimeException("Can't connect to the database. Check your credentials");
        }
    }
}
