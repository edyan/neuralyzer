<?php

namespace Edyan\Neuralyzer\Tests\Configuration;

use Edyan\Neuralyzer\Configuration\Reader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ReaderTest extends TestCase
{
    public function testReadConfigurationWrongFile()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("|.*does not exist.*|");

        new Reader('_files/config.doesntexist.yaml', [__DIR__ . '/..']);
    }

    public function testReadConfigurationWrongConf()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches("|.*must be configured|");

        new Reader('_files/config.wrong.yaml', [__DIR__ . '/..']);
    }

    public function testReadConfigurationRightConf()
    {
        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);
        $values = $reader->getConfigValues();
        $this->assertIsArray($values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('guestbook', $values['entities']);
        $this->assertArrayHasKey('cols', $values['entities']['guestbook']);

        $entities = $reader->getEntities();
        $this->assertIsArray($entities);
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
        $this->assertIsArray($values['entities']['guestbook']['pre_actions']);
        $this->assertArrayHasKey('post_actions', $values['entities']['guestbook']);
        $this->assertIsArray($values['entities']['guestbook']['post_actions']);

        $entities = $reader->getEntities();
        $this->assertContains('guestbook', $entities);

        return $reader;
    }

    public function testGetEntityNotInConfig()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("|does_not_exist is not set in config|");

        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);
        $reader->getEntityConfig('does_not_exist');
    }
}
