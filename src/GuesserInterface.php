<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 *
 * @copyright 2018 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer;

/**
 * Guesser to map col type to Faker Class
 */
interface GuesserInterface
{
    /**
     * Gives an array of fieldName => Faker class
     *
     * @return array
     */
    public function getColsNameMapping(): array;

    /**
     * Returns an array of fieldType => Faker method
     *
     * @param  mixed $length  Field's length
     *
     * @return array
     */
    public function getColsTypeMapping($length): array;

    /**
     * Will map cols first by looking for field name then by looking for field type
     * if the first returned nothing
     *
     * @return array
     */
    public function mapCol(string $table, string $name, string $type, ?string $len = null): array;

    /**
     * Returns the Guesser version
     */
    public function getVersion(): string;
}
