<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.2
 *
 * @author    Emmanuel Dyan
 *
 * @copyright 2020 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

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
     */
    public function __construct(Generator $generator, string $language = 'en_US')
    {
        parent::__construct($generator);
        $this->language = $language;
    }

    /**
     * If not already done, load the file in memory as an array, then shuffle it
     */
    public function uniqueWord(): string
    {
        if (empty(self::$dictionary)) {
            $this->loadFullDictionary();
        }

        return $this->extractARowFromDictionary();
    }

    /**
     *  Load the current language dictionary with a lot of words
     *  to always be able to get a unique value.
     */
    private function loadFullDictionary(): void
    {
        $file = __DIR__ . '/../Dictionary/' . $this->language;
        if (! file_exists($file)) {
            $file = __DIR__ . '/../Dictionary/en_US';
        }

        self::$dictionary = array_filter(explode(PHP_EOL, file_get_contents($file)));
    }

    /**
     * @throws \RuntimeException
     */
    private function extractARowFromDictionary(): string
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
