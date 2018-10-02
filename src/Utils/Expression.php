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

    public function getServices()
    {
        print_r($this->container->getServiceIds()); die();
        foreach ($this->container->getParameter('app.service.ids') as $service) {
            echo $service . PHP_EOL;
        }

        // getServiceIds
        // getDefinitions
        // getExtensions
        $services = $this->container->getExpressionLanguageProviders();

    }

    /**
     * Evaluate an array of expression that would be in the most general case
     * a list of actions coming from an Anonymiser
     *
     * @param  array  $expressions List of formulas
     */
    public function evaluateExpressionUtils(array $expressions)
    {
        $expressionLanguage = new ExpressionLanguage();

        $values = [];
        /** @var UtilsInterface $expressionUtil */
        foreach ($this->expressionUtils as $expressionUtil) {
            $name = $expressionUtil->getName();

            foreach ($expressionUtil->getExtraArguments() as $extraArgument) {
                if (property_exists($this, $extraArgument)) {
                    $func = sprintf('get%s', ucfirst($extraArgument));
                    $expressionUtil->$extraArgument = $this->$func();
                }
            }

            $values[$name] = $expressionUtil;
        }

        foreach ($actions as $action) {
            $expressionLanguage->evaluate($action, $values);
        }
    }
}
