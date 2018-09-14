<?php
/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2018 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Configuration;

use Edyan\Neuralyzer\Guesser;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

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
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
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
                    ->defaultValue((new Guesser)->getVersion())
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('language')
                    ->info("Faker's language, make sure all your methods have a translation")
                    ->defaultValue('en_US')
                ->end()
                ->arrayNode('pre_queries')
                    ->defaultValue(array())
                    ->normalizeKeys(false)
                    ->info('The list of queries to execute before neuralyzing')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('entities')
                    ->info("List all entities, theirs cols and actions")
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
                                ->defaultValue(false)
                            ->end()
                            ->scalarNode('delete_where')
                                ->cannotBeEmpty()
                                ->info('Condition applied in a WHERE if delete is set to "true"')
                                ->example("'1 = 1'")
                            ->end()
                            ->arrayNode('cols')
                                ->example([
                                    'first_name' => ['method' => 'firstName'],
                                    'last_name' => ['method' => 'lastName']
                                ])
                                ->requiresAtLeastOneElement()
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('method')->isRequired()->end()
                                        ->arrayNode('params')
                                            ->defaultValue([])
                                            ->prototype('variable')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('post_queries')
                    ->defaultValue(array())
                    ->normalizeKeys(false)
                    ->info('The list of queries to execute after neuralyzing')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
