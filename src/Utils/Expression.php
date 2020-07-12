<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author    Emmanuel Dyan
 *
 * @copyright 2018 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Utils;

use Psr\Container\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Class Expression
 *
 * @package edyan/neuralyzer
 */
class Expression
{
    /**
     * Container injected by autowiring
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * List of "things" to inject in expressions evaluation
     *
     * @var array
     */
    private $services = [];

    /**
     * Used for autowiring
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->configure();
    }

    /**
     * Return all registered services
     *
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * Evaluate an expression that would be in the most general case
     * an action coming from an Anonymization config
     *
     * @return mixed
     */
    public function evaluateExpression(string $expression)
    {
        $expressionLanguage = new ExpressionLanguage();

        return $expressionLanguage->evaluate($expression, $this->services);
    }

    /**
     * Evaluate a list of expression
     *
     * @param  array  $expressions
     *
     * @return array
     */
    public function evaluateExpressions(array $expressions): array
    {
        $res = [];
        foreach ($expressions as $expression) {
            $res[] = $this->evaluateExpression($expression);
        }

        return $res;
    }

    /**
     * Configure that service by registering all services in an array
     */
    private function configure(): void
    {
        $services = array_keys($this->container->findTaggedServiceIds('app.service'));
        foreach ($services as $service) {
            $service = $this->container->get($service);
            $this->services[$service->getName()] = $service;
        }
    }
}
