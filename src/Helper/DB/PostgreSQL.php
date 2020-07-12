<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author Emmanuel Dyan
 *
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
 * Various methods related to PostgreSQL
 */
class PostgreSQL extends AbstractDBHelper
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
        $fields = implode(', ', $fields);

        if ($this->pretend === false) {
            if ($mode === 'update') {
                $this->conn->query("TRUNCATE {$table}");
            }
            $pdo = $this->conn->getWrappedConnection();
            $pdo->pgsqlCopyFromFile($table, $fname, '|', '\\\\N', $fields);
        }

        $sql = "COPY {$table} (${fields}) FROM '{$fname}' ";
        $sql .= '... Managed by pgsqlCopyFromFile';

        return $sql;
    }
}
