<?php
/**
 * Inet Data Anonymization
 *
 * PHP Version 5.3 -> 7.0
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2005-2015 iNet Process
 *
 * @package inetprocess/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link http://www.inetprocess.com
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
    public function getColsNameMapping();

    /**
     * Retruns an array of fieldType => Faker class
     *
     * @return array
     */
    public function getColsTypeMapping();

    /**
     * Will map cols first by looking for field name then by looking for field type
     * if the first returned nothing
     *
     * @param string $table
     * @param string $name
     * @param string $type
     *
     * @return array
     */
    public function mapCol($table, $name, $type, $len);

    /**
     * Returns the Guesser version
     *
     * @return string
     */
    public function getVersion();
}
