<?php

namespace Inet\Neuralyzer\Tests;

use Inet\Neuralyzer\Exception as NeuralyzerExceptions;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{

    /**
     * @expectedException Inet\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testInetNeuralizerConfigurationException()
    {
        throw new NeuralyzerExceptions\NeuralizerConfigurationException('test');
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\NeuralizerGuesserException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testNeuralizerGuesserException()
    {
        throw new NeuralyzerExceptions\NeuralizerGuesserException('test');
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testNeuralizerException()
    {
        throw new NeuralyzerExceptions\NeuralizerException('test');
    }
}
