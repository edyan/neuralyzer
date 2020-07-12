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

namespace Edyan\Neuralyzer\Utils;

/**
 * A few generic methods to help interacting with DB
 */
class CSVWriter extends \SplFileObject
{
    /**
     * Create a temporary file
     */
    public function __construct()
    {
        parent::__construct(tempnam(sys_get_temp_dir(), 'neuralyzer'), 'w');
    }

    /**
     * Write a CSV line either by PHP standard or manually when no $enclosure
     *
     * @param  array  $fields
     */
    public function write(array $fields): int
    {
        $options = $this->getCsvControl();
        $delimiter = $options[0];
        $enclosure = $options[1];
        if (! empty($enclosure)) {
            return $this->fputcsv($fields, $delimiter, $enclosure);
        }

        $fields = array_map(static function ($field) use ($delimiter) {
            return str_replace([$delimiter, PHP_EOL], ['', ''], $field);
        }, $fields);

        return $this->fwrite(implode($delimiter, $fields) . PHP_EOL);
    }
}
