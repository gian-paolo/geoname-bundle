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

    // Detect if we are on Doctrine ORM 3
    $isOrm3 = !method_exists(\Doctrine\ORM\Configuration::class, 'getAutoGenerateProxyClasses');

    if ($isOrm3) {
        // In ORM 3, many legacy options are gone and handled by DoctrineBundle
        $ormConfig['controller_resolver'] = ['auto_mapping' => false];
        // We explicitly avoid any 'enable_lazy_ghost_objects' here
    } else {
        // ORM 2 specific safe settings
        $ormConfig['auto_generate_proxy_classes'] = true;
        // This is the key: we explicitly set it to false to avoid the internal call 
        // to enableNativeLazyObjects in some versions of DoctrineBundle
        $ormConfig['enable_lazy_ghost_objects'] = false;
    }

    $container->extension('doctrine', [
        'dbal' => [
            'url' => '%env(resolve:DATABASE_URL)%',
        ],
        'orm' => $ormConfig,
    ]);
};
