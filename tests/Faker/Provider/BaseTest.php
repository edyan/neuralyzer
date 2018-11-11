<?php

namespace Edyan\Neuralyzer\Tests\Faker\Provider;

use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    public function testNullValue()
    {
        $faker = \Faker\Factory::create();
        $faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\Base($faker));
        $this->assertEmpty($faker->emptyString);
    }
}
