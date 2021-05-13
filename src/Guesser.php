<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.2
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 *
 * @copyright 2020 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer;

use Edyan\Neuralyzer\Exception\NeuralyzerGuesserException;

/**
 * Guesser to map field type to Faker Class
 */
class Guesser implements GuesserInterface
{
    /**
     * Returns the version of your guesser
     */
    public function getVersion(): string
    {
        return '3.0';
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
            // Internet
            '.*email.*' => ['method' => 'email'],
            '.*url' => ['method' => 'url'],

            // Address and coordinates
            '.*address.*' => ['method' => 'streetAddress'],
            '.*street.*' => ['method' => 'streetAddress'],
            '.*postalcode.*' => ['method' => 'postcode'],
            '.*city.*' => ['method' => 'city'],
            '.*state.*' => ['method' => 'state'],
            '.*country.*' => ['method' => 'country'],
            '.*phone.*' => ['method' => 'phoneNumber'],

            // Text
            '.*\.(comments|description)' => ['method' => 'sentence', 'params' => [20]],

            // Person
            '.*first_?name' => ['method' => 'firstName'],
            '.*last_?name' => ['method' => 'lastName'],
        ];
    }

    /**
     * Returns an array of fieldType => Faker method
     *
     * @param  mixed $length  Field's length
     *
     * @return array
     */
    public function getColsTypeMapping($length): array
    {
        return [
            // Strings
            'string' => ['method' => 'sentence', 'params' => [$length]],
            'enum' => [
                'method' => 'randomElement',
                'params' => [['SET', 'YOUR', 'VALUES', 'HERE']]
            ],
            'simplearray' => [
                'method' => 'randomElement',
                'params' => [['SET', 'YOUR', 'VALUES', 'HERE']]
            ],

            // Text & Blobs
            'text' => ['method' => 'sentence',        'params' => [20]],
            'blob' => ['method' => 'sentence',        'params' => [20]],
            'json' => ['method' => 'jsonWordsObject', 'params' => [5]],

            // DateTime
            'date' => ['method' => 'date',     'params' => ['Y-m-d']],
            'datetime' => ['method' => 'date', 'params' => ['Y-m-d H:i:s']],
            'time' => ['method' => 'time',     'params' => ['H:i:s']],

            // Integer
            'boolean' => ['method' => 'randomElement',  'params' => [[0, 1]]],
            'smallint' => ['method' => 'randomNumber', 'params' => [4]],
            'integer' => ['method' => 'randomNumber',  'params' => [9]],
            'bigint' => [
                'method' => 'randomNumber',
                'params' => [strlen(strval(mt_getrandmax())) - 1]
            ],

            // Decimal
            'float' => ['method' => 'randomFloat',   'params' => [2, 0, 999999]],
            'decimal' => ['method' => 'randomFloat', 'params' => [2, 0, 999999]],
        ];
    }

    /**
     * Will map cols first by looking for field name then by looking for field type
     * if the first returned nothing
     *
     * @param mixed $len Used to get options from enum (stored in length)
     *
     * @return array
     *
     * @throws NeuralyzerGuesserException
     */
    public function mapCol(string $table, string $name, string $type, $len = null): array
    {
        // Try to find by colsName
        $colsName = $this->getColsNameMapping();
        foreach ($colsName as $colRegex => $params) {
            preg_match("/^${colRegex}\$/i", $table. '.' . $name, $matches);
            if (! empty($matches)) {
                return $params;
            }
        }

        // Hardcoded type, we have an enum with values
        // into the len
        if ($type === 'enum' && is_string($len)) {
            return [
                'method' => 'randomElement',
                'params' => [explode("','", substr($len, 1, -1))],
            ];
        }

        // Try to find by fieldType
        $colsType = $this->getColsTypeMapping($len);
        if (! array_key_exists($type, $colsType)) {
            $msg = "Can't guess the type ${type} ({$table}.{$name})" . PHP_EOL;
            $msg .= print_r($colsType, true);
            throw new NeuralyzerGuesserException($msg);
        }

        return $colsType[$type];
    }
}
