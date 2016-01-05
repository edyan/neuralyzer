<?php

namespace Inet\Neuralyzer\Tests;

use Inet\Neuralyzer\Guesser;

class GuesserTest extends \PHPUnit_Framework_TestCase
{

    public function testInit()
    {
        $guesser = new Guesser;
        $this->assertInstanceOf(
            'Inet\Neuralyzer\GuesserInterface',
            $guesser
        );
        $this->assertInstanceOf(
            'Inet\Neuralyzer\Guesser',
            $guesser
        );
    }

    public function testGetColsNameMapping()
    {
        $guesser = new Guesser;
        $colsNameMapping = $guesser->getColsNameMapping();
        $this->assertInternalType('array', $colsNameMapping);
    }

    public function testGetColsTypeMapping()
    {
        $guesser = new Guesser;
        $colsTypeMapping = $guesser->getColsTypeMapping();
        $this->assertInternalType('array', $colsTypeMapping);
    }

    public function testMapColByName()
    {
        $guesser = new Guesser;
        $mapping = $guesser->mapCol('test', 'my_street_name', 'varchar');
        $this->assertInternalType('array', $mapping);
        $this->assertArrayHasKey('method', $mapping);
    }

    public function testMapColByType()
    {
        $guesser = new Guesser;
        $mapping = $guesser->mapCol('test', 'nothingtocompare', 'varchar');
        $this->assertInternalType('array', $mapping);
        $this->assertArrayHasKey('method', $mapping);
        $this->assertArrayHasKey('params', $mapping);

        // check the version
        $version = $guesser->getVersion();
        $this->assertInternalType('string', $version);
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonGuesserException
     */
    public function testMapColWrongType()
    {
        $guesser = new Guesser;
        $guesser->mapCol('test', 'nothingtocompare', 'nothingtocompare');
    }
}
