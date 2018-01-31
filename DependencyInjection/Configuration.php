<?php

namespace Emhar\HydraClientBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('emhar_hydra_client');

        $rootNode
            ->children()
                ->scalarNode('guzzle_client')->end()
            ->end();

        return $treeBuilder;
    }
}
