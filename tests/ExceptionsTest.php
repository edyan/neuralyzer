<?php

namespace Inet\Neuralyzer\Tests;

use Inet\Neuralyzer\Guesser;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonConfigurationException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testInetInetAnonConfigurationException()
    {
        throw new \Inet\Neuralyzer\Exception\InetAnonConfigurationException('test');
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonGuesserException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testInetAnonGuesserException()
    {
        throw new \Inet\Neuralyzer\Exception\InetAnonGuesserException('test');
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testInetAnonException()
    {
        throw new \Inet\Neuralyzer\Exception\InetAnonException('test');
    }
}
