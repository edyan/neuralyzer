<?php
/**
 * Created by IntelliJ IDEA.
 * User: Emmanuel
 * Date: 11/11/18
 * Time: 17:08
 */

namespace Edyan\Neuralyzer\Tests\Faker\Provider;

use PHPUnit\Framework\TestCase;

class UniqueWordTest extends TestCase
{
    public function testIHaveAUniqueWord()
    {
        $faker = \Faker\Factory::create();
        $faker->addProvider(new \Edyan\Neuralyzer\Faker\Provider\UniqueWord($faker));
        $words = [];
        $working = true;
        for ($i = 0; $i < 99999; $i++) {
            $word = $faker->uniqueWord;
            if (array_key_exists($word, $words)) {
                $working = false;
                break;
            }

            $words[$word] = '';
        }

        $this->assertTrue($working);
    }
}
