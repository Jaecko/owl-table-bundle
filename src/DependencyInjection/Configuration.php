<?php

namespace OwlConcept\TableBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('owl_table');

        $treeBuilder->getRootNode()
            ->children()
                ->enumNode('default_mode')
                    ->values(['server', 'client'])
                    ->defaultValue('server')
                ->end()
                ->integerNode('default_per_page')
                    ->defaultValue(20)
                    ->min(1)
                    ->max(500)
                ->end()
                ->scalarNode('css_class_prefix')
                    ->defaultValue('owl-table')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
