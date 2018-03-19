<?php

namespace Edyan\Neuralyzer\Tests;

use Edyan\Neuralyzer\Configuration\Reader;
use PHPUnit\Framework\TestCase;

class ConfigReaderTest extends TestCase
{
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |.*does not exist.*|
     */
    public function testReadConfigurationWrongFile()
    {
        new Reader('_files/config.doesntexist.yaml', [__DIR__]);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessageRegExp |.*must be configured|
     */
    public function testReadConfigurationWrongConf()
    {
        new Reader('_files/config.wrong.yaml', [__DIR__]);
    }

    public function testReadConfigurationRightConf()
    {
        $reader = new Reader('_files/config.right.yaml', [__DIR__]);
        $values = $reader->getConfigValues();
        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('guestbook', $values['entities']);
        $this->assertArrayHasKey('cols', $values['entities']['guestbook']);

        $entities = $reader->getEntities();
        $this->assertInternalType('array', $entities);
        $this->assertContains('guestbook', $entities);

        return $reader;
    }

    public function testReadConfigurationRightConfWithEmpty()
    {
        $reader = new Reader('_files/config.right.deleteone.yaml', [__DIR__]);
        $values = $reader->getConfigValues();
        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('guestbook', $values['entities']);
        $this->assertArrayHasKey('delete', $values['entities']['guestbook']);
        $this->assertArrayHasKey('delete_where', $values['entities']['guestbook']);

        $entities = $reader->getEntities();
        $this->assertInternalType('array', $entities);
        $this->assertContains('guestbook', $entities);

        return $reader;
    }
}
