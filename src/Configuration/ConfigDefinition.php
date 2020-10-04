<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.2
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 *
 * @copyright 2020 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Configuration;

use Edyan\Neuralyzer\Guesser;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration Validation
 */
class ConfigDefinition implements ConfigurationInterface
{
    /**
     * Validate the configuration
     *
     * The config structure is something like :
     * ## Root
     * entities:
     *    ## Can be repeated : the name of the table, is an array
     *    accounts:
     *        cols:
     *            ## Can be repeated : the name of the field, is an array
     *            name:
     *                method: words # Required: name of the method
     *                params: [8] # Optional: parameters (an array)
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('config');
        $rootNode
            ->children()
            ->scalarNode('guesser')
            ->info('Set the guesser class')
            ->defaultValue(Guesser::class)
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('guesser_version')
            ->info('Set the version of the guesser the conf has been written with')
            ->defaultValue((new Guesser())->getVersion())
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('language')
            ->info("Faker's language, make sure all your methods have a translation")
            ->defaultValue('en_US')
            ->end()
            ->arrayNode('entities')
            ->info('List all entities, theirs cols and actions')
            ->example('people')
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->prototype('array')
            ->children()
            ->scalarNode('action')
            ->info('Either "update" or "insert" data')
            ->defaultValue('update')
            ->validate()
            ->ifNotInArray(['update', 'insert'])
            ->thenInvalid('Action is either "update" or "insert"')
            ->end()
            ->end()
            ->scalarNode('delete')
            ->info('Should we delete data with what is defined in "delete_where" ?')
            ->setDeprecated('delete and delete_where have been deprecated. Use now pre and post_actions')
            ->end()
            ->scalarNode('delete_where')
            ->cannotBeEmpty()
            ->info('Condition applied in a WHERE if delete is set to "true"')
            ->example("'1 = 1'")
            ->setDeprecated('delete and delete_where have been deprecated. Use now pre and post_actions')
            ->end()
            ->arrayNode('cols')
            ->example([
                'first_name' => ['method' => 'firstName'],
                'last_name' => ['method' => 'lastName'],
            ])
            ->requiresAtLeastOneElement()
            ->prototype('array')
            ->children()
            ->scalarNode('method')
            ->isRequired()
            ->info('Faker method to use, see doc : https://github.com/fzaninotto/Faker')
            ->end()
            ->booleanNode('unique')
            ->defaultFalse()
            ->info('Set this option to true to generate unique values for that field (see faker->unique() generator)')
            ->end()
            ->arrayNode('params')
            ->defaultValue([])
            ->info("Faker's parameters, see Faker's doc")
            ->prototype('variable')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->integerNode('limit')
            ->defaultValue(0)
            ->info('Limit the number of written records (update or insert). 100 by default for insert')
            ->min(0)
            ->end()
            ->arrayNode('pre_actions')
            ->defaultValue([])
            ->normalizeKeys(false)
            ->info('The list of expressions language actions to executed before neuralyzing. Be careful that "pretend" has no effect here.')
            ->prototype('scalar')->end()
            ->end()
            ->arrayNode('post_actions')
            ->defaultValue([])
            ->normalizeKeys(false)
            ->info('The list of expressions language actions to executed after neuralyzing. Be careful that "pretend" has no effect here.')
            ->prototype('scalar')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
