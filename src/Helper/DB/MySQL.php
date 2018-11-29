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
     * Send options to be able to load data set
     *
     * @return array
     */
    public static function getDriverOptions(): array
    {
        if (!defined('\PDO::MYSQL_ATTR_LOCAL_INFILE')) {
            return;
        }

        return [
            \PDO::MYSQL_ATTR_LOCAL_INFILE => true
        ];
    }


    /**
     * Add a custom enum type
     *
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     */
    public function registerCustomTypes(): void
    {
        // already registered
        if (\Doctrine\DBAL\Types\Type::hasType('neuralyzer_enum')) {
            return;
        }

        // Else register
        // Manage specific types such as enum
        \Doctrine\DBAL\Types\Type::addType(
            'neuralyzer_enum',
            'Edyan\Neuralyzer\Doctrine\Type\Enum'
        );
        $platform = $this->conn->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'neuralyzer_enum');
        $platform->registerDoctrineTypeMapping('bit', 'boolean');
    }


    /**
     * {@inheritdoc}
     */
    public function loadData(string $table, string $fname, array $fields, string $mode): string
    {
        $sql ="LOAD DATA LOCAL INFILE '{$fname}'
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
