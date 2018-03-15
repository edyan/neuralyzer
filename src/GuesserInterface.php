<?php
/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2018 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Inet\Neuralyzer;

/**
 * Guesser to map col type to Faker Class
 */
interface GuesserInterface
{
    /**
     * Retruns an array of fieldName => Faker class
     *
     * @return array
     */
    public function getColsNameMapping(): array;

    /**
     * Retruns an array of fieldType => Faker class
     *
     * @return array
     */
    public function getColsTypeMapping(): array;

    /**
     * Will map cols first by looking for field name then by looking for field type
     * if the first returned nothing
     *
     * @param string $table
     * @param string $name
     * @param string $type
     * @param string $len
     *
     * @return array
     */
    public function mapCol(string $table, string $name, string $type, string $len = null): array;

    /**
     * Returns the Guesser version
     *
     * @return string
     */
    public function getVersion(): string;
}
