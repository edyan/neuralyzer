<?php

namespace Edyan\Neuralyzer\Tests\Exception;

use Edyan\Neuralyzer\Exception as NeuralyzerExceptions;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralyzerConfigurationException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testNeuralyzerConfigurationException()
    {
        throw new NeuralyzerExceptions\NeuralyzerConfigurationException('test');
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralyzerGuesserException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testNeuralyzerGuesserException()
    {
        throw new NeuralyzerExceptions\NeuralyzerGuesserException('test');
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralyzerException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testNeuralyzerException()
    {
        throw new NeuralyzerExceptions\NeuralyzerException('test');
    }
}
