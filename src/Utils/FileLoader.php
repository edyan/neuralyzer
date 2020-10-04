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

namespace Edyan\Neuralyzer\Utils;

/**
 * Class FileLoader
 *
 * @package edyan/neuralyzer
 */
class FileLoader
{
    /**
     * Checks if a PHP source file is readable and loads it.
     *
     * @throws \Exception
     */
    public static function checkAndLoad(string $filename): string
    {
        $includePathFilename = \stream_resolve_include_path($filename);

        // As a fallback, PHP looks in the directory of the file executing the stream_resolve_include_path function.
        // We don't want to load the Test.php file here, so skip it if it found that.
        // PHP prioritizes the include_path setting, so if the current directory is in there, it will first look in the
        // current working directory.
        $localFile = __DIR__ . DIRECTORY_SEPARATOR . $filename;

        $isReadable = @\fopen($includePathFilename, 'r') !== false;

        if (! $includePathFilename || ! $isReadable || $includePathFilename === $localFile) {
            throw new \Exception(\sprintf('Cannot open file "%s".' . "\n", $filename));
        }

        require_once $includePathFilename;

        return $includePathFilename;
    }
}
