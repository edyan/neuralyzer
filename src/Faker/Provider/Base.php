<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.2
 *
 * @author Emmanuel Dyan
 *
 * @copyright 2020 Emmanuel Dyan
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
     */
    public static function emptyString(): string
    {
        return '';
    }
}
