<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.2
 *
 * @author    Emmanuel Dyan
 *
 * @copyright 2020 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Service;

/**
 * Extend utils available from the pre and post actions (symfony language expression)
 */
interface ServiceInterface
{
    public function getName(): string;
}
