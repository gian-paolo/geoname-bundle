<?php

namespace Gpp\GeonameBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('gpp_geoname');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('entities')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('geoname')->defaultValue('App\Entity\GeoName')->end()
                        ->scalarNode('country')->defaultValue('App\Entity\GeoCountry')->end()
                        ->scalarNode('import')->defaultValue('App\Entity\DataImport')->end()
                        ->scalarNode('admin1')->defaultValue('App\Entity\GeoAdmin1')->end()
                        ->scalarNode('admin2')->defaultValue('App\Entity\GeoAdmin2')->end()
                        ->scalarNode('alternate_name')->defaultValue('App\Entity\GeoAlternateName')->end()
                        ->scalarNode('hierarchy')->defaultValue('App\Entity\GeoHierarchy')->end()
                    ->end()
                ->end()
                ->arrayNode('tables')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('geoname')->defaultValue('gpp_geoname')->end()
                        ->scalarNode('country')->defaultValue('gpp_geocountry')->end()
                        ->scalarNode('import')->defaultValue('gpp_geoimport')->end()
                        ->scalarNode('admin1')->defaultValue('gpp_geoadmin1')->end()
                        ->scalarNode('admin2')->defaultValue('gpp_geoadmin2')->end()
                        ->scalarNode('alternate_name')->defaultValue('gpp_geoalternatename')->end()
                        ->scalarNode('hierarchy')->defaultValue('gpp_geohierarchy')->end()
                    ->end()
                ->arrayNode('alternate_names')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->arrayNode('languages')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
                ->scalarNode('temp_dir')->defaultValue('%kernel.project_dir%/var/tmp/geoname')->end()
            ->end();

        return $treeBuilder;
    }
}
