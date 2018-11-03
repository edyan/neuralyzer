<?php

namespace Edyan\Neuralyzer\Utils;

use Psr\Container\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class Expression
{
    /**
     * Container injected by autowiring
     * @var ContainerInterface
     */
    private $container;

    /**
     * List of "things" to inject in expressions evaluation
     * @var array
     */
    private $services = [];

    /**
     * Used for autowiring
     * @param  ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->configure();
    }

    /**
     * Return all registered services
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
     * @param  string  $expression
     */
    public function evaluateExpression(string $expression)
    {
        $expressionLanguage = new ExpressionLanguage();

        return $expressionLanguage->evaluate($expression, $this->services);
    }


    /**
    * Evaluate a list of expression
    * @param  array  $expressions
    */
    public function evaluateExpressions(array $expressions)
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
    private function configure()
    {
        $services = array_keys($this->container->findTaggedServiceIds('app.service'));
        foreach ($services as $service) {
            $service = $this->container->get($service);
            $this->services[$service->getName()] = $service;
        }
    }
}
