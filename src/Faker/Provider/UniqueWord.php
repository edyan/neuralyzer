<?php

namespace Edyan\Neuralyzer\Faker\Provider;

use Faker\Generator;
use Faker\Provider\Base;

/**
 * Class UniqueWord
 */
class UniqueWord extends Base
{
    /** @var int */
    private $nrOfWordsRequired;

    /** @var string */
    private $language;

    /** @var array */
    protected static $wordList = [];

    /**
     * UniqueWord constructor.
     *
     * @param Generator $generator
     * @param int       $nrOfWordsRequired
     * @param string    $language
     */
    public function __construct(Generator $generator, int $nrOfWordsRequired = 150, string $language = 'en_US')
    {
        parent::__construct($generator);
        $this->nrOfWordsRequired = $nrOfWordsRequired;
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
     *  Load the current language dictionary with a lot of words to always be able to get a unique value.
     */
    public function loadFullDictionary()
    {
        static $loaded = false;
        if ($loaded === true) {
            return;
        }
        $numberOfWordsRequired = $this->nrOfWordsRequired * 2; // to cater for multiple loops to ensure uniqueness
        if (\count(static::$wordList) >= $numberOfWordsRequired) {
            return;
        }

        $file = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'Dictionary'.DIRECTORY_SEPARATOR.$this->language;
        if (!file_exists($file)) {
            $loaded = true;

            return;
        }
        $fp = fopen($file, 'rb');
        while (($line = fgets($fp, 4096)) !== false) {
            if (strpos($line, '%') !== false) {
                continue;
            }
            $word = trim($line);
            if (!\in_array($word, static::$wordList, false)) {
                static::$wordList[] = $word;
            }
            if (\count(static::$wordList) >= $numberOfWordsRequired) {
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
