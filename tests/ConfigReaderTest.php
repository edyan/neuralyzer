<?php

namespace Inet\Anon\Tests;

use Inet\Anon\Configuration\Reader;

class ConfigReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |.*does not exist.*|
     */
    public function testReadConfigurationWrongFile()
    {
        new Reader('_files/config.doesntexist.yaml', array(dirname(__FILE__)));
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessageRegExp |.*must be configured|
     */
    public function testReadConfigurationWrongConf()
    {
        new Reader('_files/config.wrong.yaml', array(dirname(__FILE__)));
    }

    public function testReadConfigurationRightConf()
    {
        $reader = new Reader('_files/config.right.yaml', array(dirname(__FILE__)));
        $values = $reader->getConfigValues();
        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('entities', $values);

        $entities = $reader->getEntities();
        $this->assertInternalType('array', $entities);
        $this->assertContains('guestbook', $entities);

        return $reader;
    }
}
