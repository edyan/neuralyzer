<?php

namespace Inet\Anon\Tests;

use Inet\Anon\Guesser;

class ExceptionsTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException Inet\Anon\Exception\InetAnonConfigurationException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testInetInetAnonConfigurationException()
    {
        throw new \Inet\Anon\Exception\InetAnonConfigurationException('test');
    }

    /**
     * @expectedException Inet\Anon\Exception\InetAnonGuesserException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testInetAnonGuesserException()
    {
        throw new \Inet\Anon\Exception\InetAnonGuesserException('test');
    }

    /**
     * @expectedException Inet\Anon\Exception\InetAnonException
     * @expectedExceptionMessageRegExp |test|
     */
    public function testInetAnonException()
    {
        throw new \Inet\Anon\Exception\InetAnonException('test');
    }
}
