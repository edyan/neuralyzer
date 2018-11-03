<?php

namespace Edyan\Neuralyzer\Utils;

use Psr\Container\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class Expression
{
    private $container;

    /**
     * Create an expression language object and inject all services to it
     * @param  ContainerInterface $container Container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Get available services in container to inject it
     * @return void
     */
    public function getServices(): array
    {
        $availServices = array_keys($this->container->findTaggedServiceIds('app.service'));
        $services = [];
        foreach ($availServices as $availService) {
            $service = $this->container->get($availService);
            $services[$service->getName()] = $service;
        }

        return $services;
    }

    /**
     * Evaluate an array of expression that would be in the most general case
     * a list of actions coming from an Anonymiser
     *
     * @param  array  $expressions List of formulas
     */
    public function evaluateExpressions(array $expressions): array
    {
        $expressionLanguage = new ExpressionLanguage();

        $services = $this->getServices();
        $res = [];
        foreach ($expressions as $expression) {
            $res[] = $expressionLanguage->evaluate($expression, $services);
        }

        return $res;
    }
}
