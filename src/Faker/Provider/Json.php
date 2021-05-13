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

use Faker\Provider\Lorem;

/**
 * Class Json.
 */
class Json extends Lorem
{
    /**
     * Generate a json list containing words
     *
     * @example ["ut", "eaque", "rerum", "voluptatem"]
     * @param  int      $nb     how many words to return
     *
     * @return array|string
     */
    public static function jsonWordsList($nb = 3)
    {
        $words = [];
        for ($i = 0; $i < $nb; $i++) {
            $words[] = static::word();
        }

        return json_encode($words);
    }

    /**
     * Generate a json containing words as an object
     *
     * @example ["ut", "eaque", "rerum", "voluptatem"]
     * @param  int      $nb     how many words to return
     *
     * @return array|string
     */
    public static function jsonWordsObject($nb = 3)
    {
        $words = [];
        for ($i = 0; $i < $nb; $i++) {
            $word = static::word();
            $words[\substr($word, 1, 1) . $i] = $word;
        }

        return json_encode($words);
    }
}
