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

/**
 * Various methods related to MySQL
 */
class MySQL extends AbstractDBHelper
{
    /**
     * Send options to be able to load dataset
     * @return array
     */
    public static function getDriverOptions(): array
    {
        return [
            \PDO::MYSQL_ATTR_LOCAL_INFILE => true
        ];
    }


    /**
     * Load Data from a CSV
     * @param  string  $table
     * @param  string  $filename
     * @param  array   $fields
     * @param  string  $mode  Not in used here
     * @return string
     */
    public function loadData(
        string $table,
        string $filename,
        array $fields,
        string $mode
    ): string {
        $sql ="LOAD DATA LOCAL INFILE '{$filename}'
     REPLACE INTO TABLE {$table}
     FIELDS TERMINATED BY '|' ENCLOSED BY '\"' LINES TERMINATED BY '" . PHP_EOL . "'
     (`" . implode("`, `", $fields) . "`)";
        // Run the query if asked
        if ($this->pretend === false) {
            $this->conn->query($sql);
        }

        return $sql;
    }
}
