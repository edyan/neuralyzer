<?php

namespace Edyan\Neuralyzer\Utils;

class FileLoader
{
    /**
     * Checks if a PHP sourcefile is readable and loads it.
     *
     * @param string $filename
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function checkAndLoad($filename)
    {
        $includePathFilename = \stream_resolve_include_path($filename);

        // As a fallback, PHP looks in the directory of the file executing the stream_resolve_include_path function.
        // We don't want to load the Test.php file here, so skip it if it found that.
        // PHP prioritizes the include_path setting, so if the current directory is in there, it will first look in the
        // current working directory.
        $localFile = __DIR__ . DIRECTORY_SEPARATOR . $filename;

        $isReadable = @\fopen($includePathFilename, 'r') !== false;

        if (!$includePathFilename || !$isReadable || $includePathFilename === $localFile) {
            throw new \Exception(\sprintf('Cannot open file "%s".' . "\n", $filename));
        }

        require_once $includePathFilename;

        return $includePathFilename;
    }
}
