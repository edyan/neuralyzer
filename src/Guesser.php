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

use Inet\Neuralyzer\Exception\InetAnonGuesserException;

/**
 * Guesser to map field type to Faker Class
 */
class Guesser implements GuesserInterface
{

    /**
     * Returns the version of your guesser
     *
     * @return string
     */
    public function getVersion()
    {
        return '1.0.0b';
    }

    /**
     * Returns an array of fieldName => Faker class
     *
     * @return array
     */
    public function getColsNameMapping()
    {
        // can contain regexp
        return array(
            // Adress and coordinates
            '.*\..*street.*' => array(
                'method' => 'streetAddress',
            ),
            '.*\..*postalcode.*' => array(
                'method' => 'postcode',
            ),
            '.*\..*city.*' => array(
                'method' => 'city',
            ),
            '.*\..*state.*' => array(
                'method' => 'state',
            ),
            '.*\..*country.*' => array(
                'method' => 'country',
            ),
            '.*\..*phone.*' => array(
                'method' => 'phoneNumber',
            ),
            // Internet
            '.*\.email_address' => array(
                'method' => 'email',
            ),
            '.*\.url' => array(
                'method' => 'url',
            ),
            // Text
            '.*\.(comments|description)' => array(
                'method' => 'sentence',
                'params' => array(20),
            ),
            // Person
            '.*\.first_name' => array(
                'method' => 'firstName',
            ),
            '.*\.last_name' => array(
                'method' => 'lastName',
            ),
        );
    }

    /**
     * Retruns an array of fieldType => Faker class
     *
     * @return array
     */
    public function getColsTypeMapping()
    {
        return array(
            // Strings
            'char' => array(
                'method' => 'sentence',
                'params' => array(8),
            ),
            'varchar' => array(
                'method' => 'sentence',
                'params' => array(8),
            ),
            // Text & Blobs
            'text' => array(
                'method' => 'sentence',
                'params' => array(20),
            ),
            'blob' => array(
                'method' => 'sentence',
                'params' => array(20),
            ),
            'longtext' => array(
                'method' => 'sentence',
                'params' => array(70),
            ),
            'longblob' => array(
                'method' => 'sentence',
                'params' => array(70),
            ),
            // DateTime
            'date' => array(
                'method' => 'date',
                'params' => array('Y-m-d', 'now')
            ),
            'datetime' => array(
                'method' => 'date',
                'params' => array('Y-m-d H:i:s', 'now')
            ),
            'timestamp' => array(
                'method' => 'date',
                'params' => array('Y-m-d H:i:s', 'now')
            ),
            'time' => array(
                'method' => 'date',
                'params' => array('H:i:s', 'now')
            ),
            // Integer
            'tinyint' => array(
                'method' => 'randomNumber',
                'params' => array(2),
            ),
            'smallint' => array(
                'method' => 'randomNumber',
                'params' => array(4),
            ),
            'mediumint' => array(
                'method' => 'randomNumber',
                'params' => array(6),
            ),
            'int' => array(
                'method' => 'randomNumber',
                'params' => array(9),
            ),
            'bigint' => array(
                'method' => 'randomNumber',
                'params' => array(15),
            ),
            // Decimal
            'float' => array(
                'method' => 'randomFloat',
                'params' => array(2, 0, 999999),
            ),
            'decimal' => array(
                'method' => 'randomFloat',
                'params' => array(2, 0, 999999),
            ),
            'double' => array(
                'method' => 'randomFloat',
                'params' => array(2, 0, 999999),
            ),
        );
    }

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
    public function mapCol($table, $name, $type, $len)
    {
        // Try to find by colsName
        $colsName = $this->getColsNameMapping();
        foreach ($colsName as $colRegex => $params) {
            preg_match("/^$colRegex\$/", $table. '.' . $name, $matches);
            if (!empty($matches)) {
                return $params;
            }
        }

        // Hardcoded types
        if ($type === 'enum') {
            return array(
                'method' => 'randomElement',
                'params' => array(array(explode("','", substr($len, 1, -1))))
            );
        }

        // Try to find by fieldType
        $colsType = $this->getColsTypeMapping();
        if (!array_key_exists($type, $colsType)) {
            throw new InetAnonGuesserException("Can't guess the type $type");
        }

        return $colsType[$type];
    }
}
