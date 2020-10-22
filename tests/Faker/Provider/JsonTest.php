<?php

namespace Edyan\Neuralyzer\Tests\Faker\Provider;

use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testIHaveAJsonList()
    {
        $faker = \Faker\Factory::create();
        $faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\Json($faker));
        $words = [];
        for ($i = 0; $i < 100; $i++) {
            $words = json_decode($faker->jsonWordsList);
            $this->assertNotEmpty($words);
            $this->assertIsArray($words);
            $this->assertCount(3, $words);
            $this->assertArrayHasKey(0, $words);
            $this->assertNotEmpty($words[0]);
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testIHaveAJsonObject()
    {
        $faker = \Faker\Factory::create();
        $faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\Json($faker));
        $words = [];
        for ($i = 0; $i < 100; $i++) {
            $words = json_decode($faker->jsonWordsObject);
            $this->assertIsObject($words);
            $keys = array_keys(get_object_vars($words));
            $this->assertCount(3, $keys);
            $this->assertNotEmpty($words->{$keys[0]});
        }
    }
}
