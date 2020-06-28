<?php

namespace Edyan\Neuralyzer\Tests\Utils;

use Edyan\Neuralyzer\Utils\FileLoader;

class FileLoaderTest extends \PHPUnit\Framework\TestCase
{
    public function testLoadableFile()
    {
        $filepath = FileLoader::checkAndLoad(__FILE__);
        $this->assertSame(__FILE__, $filepath);
    }

    public function testUnloadableFile()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('|Cannot open file.*badext|');

        FileLoader::checkAndLoad(__FILE__ . '.badext');
    }
}
