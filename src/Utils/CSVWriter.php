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

namespace Edyan\Neuralyzer\Utils;

use Doctrine\DBAL\Connection;
use Edyan\Neuralyzer\Exception\NeuralizerException;

/**
 * A few generic methods to help interacting with DB
 */
class CSVWriter extends \SplFileObject
{
    public function __construct()
    {
        parent::__construct(tempnam(sys_get_temp_dir(), 'neuralyzer'), 'w');
    }

    public function write(array $fields)
    {
        $options = $this->getCsvControl();
        $delimiter = $options[0];
        $enclosure = $options[1];
        if (!empty($enclosure)) {
            return $this->fputcsv($fields, $delimiter, $enclosure);
        }

        $fields = array_map(function ($field) use ($delimiter) {
            return str_replace([$delimiter, PHP_EOL], ['', ''], $field);
        }, $fields);

        return $this->fwrite(implode($delimiter, $fields) . PHP_EOL);
    }
}
