<?php

namespace Edyan\Neuralyzer\Tests\Exception;

use Edyan\Neuralyzer\Exception as NeuralyzerExceptions;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testNeuralizerConfigurationException()
    {
        throw new NeuralyzerExceptions\NeuralizerConfigurationException('test');
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerGuesserException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testNeuralizerGuesserException()
    {
        throw new NeuralyzerExceptions\NeuralizerGuesserException('test');
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testNeuralizerException()
    {
        throw new NeuralyzerExceptions\NeuralizerException('test');
    }
}
