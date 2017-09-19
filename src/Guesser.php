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

namespace Inet\Neuralyzer;

use Inet\Neuralyzer\Exception\NeuralizerGuesserException;

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
    public function getVersion(): string
    {
        return '1.0.0b';
    }

    /**
     * Returns an array of fieldName => Faker class
     *
     * @return array
     */
    public function getColsNameMapping(): array
    {
        // can contain regexp
        return [
            // Adress and coordinates
            '.*\..*street.*'              => ['method' => 'streetAddress'],
            '.*\..*postalcode.*'          => ['method' => 'postcode'],
            '.*\..*city.*'                => ['method' => 'city'],
            '.*\..*state.*'               => ['method' => 'state'],
            '.*\..*country.*'             => ['method' => 'country'],
            '.*\..*phone.*'               => ['method' => 'phoneNumber'],

            // Internet
            '.*\.email_address'           => ['method' => 'email'],
            '.*\.url'                     => ['method' => 'url'],

            // Text
            '.*\.(comments|description)'  => ['method' => 'sentence', 'params' => [20]],

            // Person
            '.*\.first_name'              => ['method' => 'firstName'],
            '.*\.last_name'               => ['method' => 'lastName'],
        ];
    }

    /**
     * Retruns an array of fieldType => Faker class
     *
     * @return array
     */
    public function getColsTypeMapping(): array
    {
        return [
            // Strings
            'char'      => ['method' => 'sentence', 'params' => [4]],
            'varchar'   => ['method' => 'sentence', 'params' => [4]],

            // Text & Blobs
            'text'      => ['method' => 'sentence', 'params' => [20]],
            'blob'      => ['method' => 'sentence', 'params' => [20]],
            'longtext'  => ['method' => 'sentence', 'params' => [70]],
            'longblob'  => ['method' => 'sentence', 'params' => [70]],

            // DateTime
            'date'      => ['method' => 'date', 'params' => ['Y-m-d', 'now']],
            'datetime'  => ['method' => 'date', 'params' => ['Y-m-d H:i:s', 'now']],
            'timestamp' => ['method' => 'date', 'params' => ['Y-m-d H:i:s', 'now']],
            'time'      => ['method' => 'date', 'params' => ['H:i:s', 'now']],

            // Integer
            'tinyint'   => ['method' => 'randomNumber', 'params' => [2]],
            'smallint'  => ['method' => 'randomNumber', 'params' => [4]],
            'mediumint' => ['method' => 'randomNumber', 'params' => [6]],
            'int'       => ['method' => 'randomNumber', 'params' => [9]],
            'bigint'    => ['method' => 'randomNumber', 'params' => [strlen(mt_getrandmax()) - 1]],

            // Decimal
            'float'     => ['method' => 'randomFloat', 'params' => [2, 0, 999999]],
            'decimal'   => ['method' => 'randomFloat', 'params' => [2, 0, 999999]],
            'double'    => ['method' => 'randomFloat', 'params' => [2, 0, 999999]],
        ];
    }

    /**
     * Will map cols first by looking for field name then by looking for field type
     * if the first returned nothing
     *
     * @param string $table
     * @param string $name
     * @param string $type
     * @param mixed  $len    Used to get options from enum (stored in length)
     *
     * @return array
     */
    public function mapCol(string $table, string $name, string $type, string $len = null): array
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
            return [
                'method' => 'randomElement',
                'params' => [explode("','", substr($len, 1, -1))]
            ];
        }

        // Try to find by fieldType
        $colsType = $this->getColsTypeMapping();
        if (!array_key_exists($type, $colsType)) {
            throw new NeuralizerGuesserException("Can't guess the type $type");
        }

        return $colsType[$type];
    }
}
