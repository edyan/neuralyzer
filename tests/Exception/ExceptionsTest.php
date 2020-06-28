<?php

namespace Edyan\Neuralyzer\Tests\Exception;

use Edyan\Neuralyzer\Exception as NeuralyzerExceptions;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{

    public function testNeuralyzerConfigurationException()
    {
        $this->expectException(NeuralyzerExceptions\NeuralyzerConfigurationException::class);
        $this->expectExceptionMessageMatches("|test|");

        throw new NeuralyzerExceptions\NeuralyzerConfigurationException('test');
    }

    public function testNeuralyzerGuesserException()
    {
        $this->expectException(NeuralyzerExceptions\NeuralyzerGuesserException::class);
        $this->expectExceptionMessageMatches("|test|");

        throw new NeuralyzerExceptions\NeuralyzerGuesserException('test');
    }

    public function testNeuralyzerException()
    {
        $this->expectException(NeuralyzerExceptions\NeuralyzerException::class);
        $this->expectExceptionMessageMatches("|test|");

        throw new NeuralyzerExceptions\NeuralyzerException('test');
    }
}
