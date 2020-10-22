<?php

namespace Edyan\Neuralyzer\Tests\Faker\Provider;

use PHPUnit\Framework\TestCase;

class UniqueWordTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testIHaveAUniqueWord()
    {
        $faker = \Faker\Factory::create();
        $faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\UniqueWord($faker));
        $words = [];
        for ($i = 0; $i < 10000; $i++) {
            $word = $faker->uniqueWord;
            $this->assertNotEmpty($word);
            $this->assertArrayNotHasKey($word, $words);
            $words[$word] = '';
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testIHaveAUniqueWordBadLanguage()
    {
        $faker = \Faker\Factory::create();
        $faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\UniqueWord($faker, 'bl_BL'));
        $words = [];
        for ($i = 0; $i < 10; $i++) {
            $word = $faker->uniqueWord;
            $this->assertNotEmpty($word);
            $this->assertArrayNotHasKey($word, $words);
            $words[$word] = '';
        }
    }
/*
    public function testICantGenerateTooManyWords()
    {
        $faker = \Faker\Factory::create();
        $faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\UniqueWord($faker));
        for ($i = 0; $i < 1000000; $i++) {
            $word = $faker->uniqueWord;
            $this->assertNotEmpty($word);
        }
    }
*/
}
