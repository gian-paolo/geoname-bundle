<?php

namespace Gpp\GeonameBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class GppGeonameExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('gpp_geoname.entities.geoname', $config['entities']['geoname']);
        $container->setParameter('gpp_geoname.entities.country', $config['entities']['country']);
        $container->setParameter('gpp_geoname.entities.import', $config['entities']['import']);
        
        $container->setParameter('gpp_geoname.tables.geoname', $config['tables']['geoname']);
        $container->setParameter('gpp_geoname.tables.country', $config['tables']['country']);
        $container->setParameter('gpp_geoname.tables.import', $config['tables']['import']);
        
        $container->setParameter('gpp_geoname.temp_dir', $config['temp_dir']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');
    }
}
