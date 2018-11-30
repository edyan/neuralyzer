<?php

namespace Edyan\Neuralyzer\Tests\Configuration;

use Edyan\Neuralyzer\Configuration\Reader;
use PHPUnit\Framework\TestCase;

class ReaderTest extends TestCase
{
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |.*does not exist.*|
     */
    public function testReadConfigurationWrongFile()
    {
        new Reader('_files/config.doesntexist.yaml', [__DIR__ . '/..']);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessageRegExp |.*must be configured|
     */
    public function testReadConfigurationWrongConf()
    {
        new Reader('_files/config.wrong.yaml', [__DIR__ . '/..']);
    }

    public function testReadConfigurationRightConf()
    {
        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);
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
        $reader = new Reader('_files/config.right.deleteone.yaml', [__DIR__ . '/..']);
        $values = $reader->getConfigValues();
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('guestbook', $values['entities']);
        $this->assertArrayHasKey('pre_actions', $values['entities']['guestbook']);
        $this->assertInternalType('array', $values['entities']['guestbook']['pre_actions']);
        $this->assertArrayHasKey('post_actions', $values['entities']['guestbook']);
        $this->assertInternalType('array', $values['entities']['guestbook']['post_actions']);

        $entities = $reader->getEntities();
        $this->assertContains('guestbook', $entities);

        return $reader;
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |does_not_exist is not set in config|
     */
    public function testGetEntityNotInConfig()
    {
        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);
        $reader->getEntityConfig('does_not_exist');
    }
}
