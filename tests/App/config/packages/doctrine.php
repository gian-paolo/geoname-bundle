<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $ormConfig = [
        'auto_mapping' => true,
        'mappings' => [
            'App' => [
                'is_bundle' => false,
                'type' => 'attribute',
                'dir' => '%kernel.project_dir%/Entity',
                'prefix' => 'Pallari\GeonameBundle\Tests\App\Entity',
                'alias' => 'App',
            ],
        ],
    ];

    // Detect Doctrine ORM version
    $isOrm3 = !method_exists(\Doctrine\ORM\Configuration::class, 'getAutoGenerateProxyClasses');
    
    // Detect Doctrine Bundle capabilities by looking at its configuration class
    // This is more reliable than checking the version string
    $bundleConfigClass = new \ReflectionClass(\Doctrine\Bundle\DoctrineBundle\DependencyInjection\Configuration::class);
    $configMethod = $bundleConfigClass->getMethod('getConfigTreeBuilder');
    // We won't parse the whole tree, but we can check for ORM 3.0 specific changes
    $isBundle3 = !class_exists(\Doctrine\Bundle\DoctrineBundle\Command\Proxy\ImportDoctrineProxyCommand::class);

    if ($isOrm3) {
        // ORM 3: most legacy proxy/lazy settings are now handled automatically or moved
        $ormConfig['controller_resolver'] = ['auto_mapping' => false];
    } else {
        // ORM 2
        if (!$isBundle3) {
            // Option only available in Bundle < 3.0
            $ormConfig['auto_generate_proxy_classes'] = true;
        }
        
        // ghost objects option was only relevant for Bundle 2.12+ with ORM 2
        if (class_exists(\Doctrine\Bundle\DoctrineBundle\Controller\ArgumentResolver\EntityValueResolver::class)) {
             $ormConfig['enable_lazy_ghost_objects'] = true;
        }
    }

    $container->extension('doctrine', [
        'dbal' => [
            'url' => '%env(resolve:DATABASE_URL)%',
        ],
        'orm' => $ormConfig,
    ]);
};
