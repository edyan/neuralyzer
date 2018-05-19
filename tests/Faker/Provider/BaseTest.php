<?php

namespace Edyan\Neuralyzer\Tests\Faker\Provider;

use Edyan\Neuralyzer\Faker\Provider\Base as BaseProvider;
use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    public function testNullValue()
    {
        $this->assertEmpty(BaseProvider::emptyString());
    }
}
