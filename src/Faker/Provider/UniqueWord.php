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
     * List of words built.
     *
     * @var array
     */
    protected static $wordList = [];

    /**
     * Number of words to generate.
     *
     * @var int
     */
    private $numWords;

    /**
     * Language to generate words from.
     *
     * @var string
     */
    private $language;

    /**
     * UniqueWord constructor.
     *
     * @param Generator $generator
     * @param int       $numWords
     * @param string    $language
     */
    public function __construct(Generator $generator, int $numWords = 150, string $language = 'en_US')
    {
        parent::__construct($generator);
        $this->numWords = $numWords;
        $this->language = $language;
    }

    /**
     * Get a random element from the loaded dictionary.
     *
     * @return string
     */
    public function uniqueWord()
    {
        $this->loadFullDictionary();

        return static::randomElement(static::$wordList);
    }

    /**
     *  Load the current language dictionary with a lot of words
     *  to always be able to get a unique value.
     */
    public function loadFullDictionary()
    {
        static $loaded = false;
        if (true === $loaded) {
            return;
        }

        $numWords = $this->numWords * 2; // to cater for multiple loops to ensure uniqueness
        if (\count(static::$wordList) >= $numWords) {
            return;
        }

        $file = __DIR__.'/../Dictionary/'.$this->language;
        if (!file_exists($file)) {
            $loaded = true;
            return;
        }

        $fp = fopen($file, 'rb');
        while (false !== ($line = fgets($fp, 4096))) {
            if (false !== strpos($line, '%')) {
                continue;
            }

            $word = trim($line);
            if (!in_array($word, static::$wordList, false)) {
                static::$wordList[] = $word;
            }

            if (count(static::$wordList) >= $numWords) {
                fclose($fp);
                $loaded = true;
                return;
            }
        }

        fclose($fp);
        $loaded = true;

        echo 'Dictionary loaded'.PHP_EOL;
    }
}
