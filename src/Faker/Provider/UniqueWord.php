<?php

declare(strict_types=1);

namespace Edyan\Neuralyzer\Faker\Provider;

use Faker\Generator;
use Faker\Provider\Base;

/**
 * Class UniqueWord.
 */
class UniqueWord extends Base
{
    /**
     * Language to generate words from.
     *
     * @var string
     */
    private $language;


    private static $dictionary = [];

    /**
     * UniqueWord constructor.
     *
     * @param Generator $generator
     * @param string    $language
     */
    public function __construct(Generator $generator, string $language = 'en_US')
    {
        parent::__construct($generator);
        $this->language = $language;
    }

    /**
     * If not already done, load the file in memory as an array, then shuffle it
     *
     * @return string
     */
    public function uniqueWord(): string
    {
        if (empty(self::$dictionary)) {
            $this->loadFullDictionary();
        }

        return $this->extractARowFromDictionnary();
    }

    /**
     *  Load the current language dictionary with a lot of words
     *  to always be able to get a unique value.
     */
    private function loadFullDictionary(): void
    {
        $file = __DIR__ . '/../Dictionary/' . $this->language;
        if (!file_exists($file)) {
            $file = __DIR__ . '/../Dictionary/en_US';
        }

        self::$dictionary = array_filter(explode(PHP_EOL, file_get_contents($file)));
    }

    private function extractARowFromDictionnary(): string
    {
        if (empty(self::$dictionary)) {
            throw new \RuntimeException("Couldn't generate more unique words ... consider using another method");
        }

        $key = array_rand(self::$dictionary);
        $row = self::$dictionary[$key];
        unset(self::$dictionary[$key]);

        return $row;
    }
}
