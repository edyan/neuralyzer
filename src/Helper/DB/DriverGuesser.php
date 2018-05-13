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
 * Help to find the right DB Type from a driver
 */
class DriverGuesser
{
    public static function getDBHelper(string $driver)
    {
        $drivers = [
            'pdo_mysql'  => MySQL::class,
            'mysql'      => MySQL::class,
            'mysql2'     => MySQL::class,

            'pdo_pgsql'  => PostgreSQL::class,
            'pgsql'      => PostgreSQL::class,
            'postgres'   => PostgreSQL::class,
            'postgresql' => PostgreSQL::class,

            'pdo_sqlsrv' => SQLServer::class,
            'mssql'      => SQLServer::class,
        ];

        if (!array_key_exists($driver, $drivers)) {
            throw new \InvalidArgumentException("$driver unknown");
        }

        return $drivers[$driver];
    }
}
