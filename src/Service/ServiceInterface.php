<?php

namespace Edyan\Neuralyzer\Service;

/**
 * Extend utils available from the symfony langage expression
 */
interface ServiceInterface
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
