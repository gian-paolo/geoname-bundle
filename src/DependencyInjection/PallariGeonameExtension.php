<?php

namespace Pallari\GeonameBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class PallariGeonameExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Entities
        foreach ($config['entities'] as $key => $value) {
            $container->setParameter('pallari_geoname.entities.' . $key, $value);
        }

        // Tables
        foreach ($config['tables'] as $key => $value) {
            $container->setParameter('pallari_geoname.tables.' . $key, $value);
        }
        
        // Other config
        $container->setParameter('pallari_geoname.temp_dir', $config['temp_dir']);
        $container->setParameter('pallari_geoname.alternate_names.enabled', $config['alternate_names']['enabled']);
        $container->setParameter('pallari_geoname.search.use_fulltext', $config['search']['use_fulltext']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');
    }
}
