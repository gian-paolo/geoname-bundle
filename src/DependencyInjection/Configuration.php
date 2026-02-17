<?php

namespace Pallari\GeonameBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pallari_geoname');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('entities')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('geoname')->defaultValue('App\Entity\GeoName')->end()
                        ->scalarNode('country')->defaultValue('App\Entity\GeoCountry')->end()
                        ->scalarNode('import')->defaultValue('App\Entity\GeoImport')->end()
                        ->scalarNode('admin1')->defaultValue('App\Entity\GeoAdmin1')->end()
                        ->scalarNode('admin2')->defaultValue('App\Entity\GeoAdmin2')->end()
                        ->scalarNode('admin3')->defaultValue('App\Entity\GeoAdmin3')->end()
                        ->scalarNode('admin4')->defaultValue('App\Entity\GeoAdmin4')->end()
                        ->scalarNode('admin5')->defaultValue('App\Entity\GeoAdmin5')->end()
                        ->scalarNode('language')->defaultValue('App\Entity\GeoLanguage')->end()
                        ->scalarNode('alternate_name')->defaultValue('App\Entity\GeoAlternateName')->end()
                        ->scalarNode('hierarchy')->defaultValue('App\Entity\GeoHierarchy')->end()
                    ->end()
                ->end()
                ->arrayNode('tables')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('geoname')->defaultValue('geoname')->end()
                        ->scalarNode('country')->defaultValue('country')->end()
                        ->scalarNode('import')->defaultValue('import')->end()
                        ->scalarNode('admin1')->defaultValue('admin1')->end()
                        ->scalarNode('admin2')->defaultValue('admin2')->end()
                        ->scalarNode('admin3')->defaultValue('admin3')->end()
                        ->scalarNode('admin4')->defaultValue('admin4')->end()
                        ->scalarNode('admin5')->defaultValue('admin5')->end()
                        ->scalarNode('alternate_name')->defaultValue('alternate_name')->end()
                        ->scalarNode('hierarchy')->defaultValue('hierarchy')->end()
                        ->scalarNode('language')->defaultValue('language')->end()
                    ->end()
                ->end()
                ->scalarNode('table_prefix')->defaultValue('geoname_')->end()
                ->arrayNode('alternate_names')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('admin5')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('search')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('use_fulltext')->defaultFalse()->end()
                        ->integerNode('max_results')
                            ->defaultValue(100)
                            ->min(1)
                            ->max(1000)
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('temp_dir')->defaultValue('%kernel.project_dir%/var/tmp/geoname')->end()
            ->end();

        return $treeBuilder;
    }
}
