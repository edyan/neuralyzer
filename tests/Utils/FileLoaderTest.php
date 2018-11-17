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

    /**
     * @expectedException \Exception
     * @expectedExceptionMessageRegExp |Cannot open file.*badext|
     */
    public function testUnloadableFile()
    {
        FileLoader::checkAndLoad(__FILE__ . '.badext');
    }
}
