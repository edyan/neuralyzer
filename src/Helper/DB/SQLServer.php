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

use Edyan\Neuralyzer\Exception\NeuralyzerException;

/**
 * Various methods related to SQLServer
 */
class SQLServer extends AbstractDBHelper
{
    /**
     * Set the right enclosure
     */
    public function getEnclosureForCSV(): string
    {
        return chr(0);
    }

    /**
     * {@inheritdoc}
     */
    public function loadData(string $table, string $fname, array $fields, string $mode): string
    {
        if (substr(gethostbyname($this->conn->getHost()), 0, 3) !== '127') {
            throw new NeuralyzerException('SQL Server must be on the same host than PHP');
        }

        $sql = "BULK INSERT {$table} FROM '{$fname}' WITH (
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
