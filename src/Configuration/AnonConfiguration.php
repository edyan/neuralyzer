<?php
/**
 * Inet Data Anonymization
 *
 * PHP Version 5.3 -> 7.0
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2005-2015 iNet Process
 *
 * @package inetprocess/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link http://www.inetprocess.com
 */

namespace Inet\Anon\Configuration;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Configuration Validation
 */
class AnonConfiguration implements ConfigurationInterface
{
    /**
     * Validate the configuration
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        // The config structure is something like :
        // entities:  # Root
        //    accounts: # Can be repeated : the name of the table, is an array
        //        cols:
        //            name: # Can be repeated : the name of the field, is an array
        //                method: words # Required: name of the method
        //                params: [8] # Optional: parameters (an array)

        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('config');
        $rootNode
            ->children()
                ->scalarNode('guesser_version')->isRequired()->end()
                ->arrayNode('entities')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->arrayNode('cols')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('method')->isRequired()->end()
                                        ->arrayNode('params')
                                            ->requiresAtLeastOneElement()->prototype('scalar')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
