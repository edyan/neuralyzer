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
 * Various methods related to SQLServer
 */
class SQLServer extends AbstractDBHelper
{
    /**
     * Set the right enclosure
     * @return string
     */
    public function getEnclosureForCSV(): string
    {
        return chr(0);
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
        if (substr(gethostbyname($this->conn->getHost()), 0, 3) !== '127') {
            throw new NeuralizerException('SQL Server must be on the same host than PHP');
        }

        $sql ="BULK INSERT {$table} FROM '{$filename}' WITH (
            FIELDTERMINATOR = '|', DATAFILETYPE = 'widechar', ROWTERMINATOR = '" . PHP_EOL . "'
        )";

        if ($this->pretend === false) {
            if ($mode === 'update') {
                $this->conn->query("TRUNCATE TABLE {$table}");
            }

            $this->conn->query($sql);
        }

        return $sql;
    }
}
