<?php

namespace Edyan\Neuralyzer\ExpressionUtils;

/**
 * Interface UtilsInterface
 *
 * @package Edyan\Neuralyzer\Utils
 */
interface UtilsInterface
{
    /**
     * Returns the name to use in the neuralyzer.yml file.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Returns an array of properties that need to be set when the expression language gets evaluated.
     *
     * @return array
     */
    public function getExtraArguments(): array;
}
