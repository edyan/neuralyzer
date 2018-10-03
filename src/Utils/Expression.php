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
    private $values = [];

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
     * Evaluate an expression that would be in the most general case
     * an action coming from an Anonymization config
     *
     * @param  string  $expression
     */
    public function evaluate(string $expression)
    {
        $expressionLanguage = new ExpressionLanguage();

        return $expressionLanguage->evaluate($expression, $this->values);
    }


    /**
    * Evaluate a list of expression
    * @param  array  $expressions
    */
    public function evaluateExpressions(array $expressions)
    {
        foreach ($expressions as $expression) {
            $this->evaluate($expression);
        }
    }

    /**
     * Configure that service by registering all services in an array
     */
    private function configure()
    {
        $services = array_keys($this->container->findTaggedServiceIds('app.service'));
        foreach ($services as $service) {
            $service = $this->container->get($service);
            $this->values[$service->getName()] = $service;
        }
    }
}
