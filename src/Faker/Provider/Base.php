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

namespace Edyan\Neuralyzer\Faker\Provider;

use Faker\Provider\Base as BaseProvider;

/**
 * Extends the base provider of faker to add an empty value Generator
 */
class Base extends BaseProvider
{
    /**
     * Simply generate an empty string
     * @return string
     */
    public static function emptyString(): string
    {
        return '';
    }
}
