<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author    Emmanuel Dyan
 * @author    Rémi Sauvat
 *
 * @copyright 2018 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Console;

use Symfony\Component\Console\Application as BaseApplication;

/**
 * Run console application.
 */
class Application extends BaseApplication
{
    const VERSION = 'v3.1.1';
}
