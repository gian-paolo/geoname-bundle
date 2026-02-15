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

    // Check if we are on Doctrine ORM 3
    $isOrm3 = !method_exists(\Doctrine\ORM\Configuration::class, 'getAutoGenerateProxyClasses');
    
    // Check if we are on a modern Doctrine Bundle that supports ghost objects
    // The option "enable_lazy_ghost_objects" was introduced in DoctrineBundle 2.12
    $hasGhostObjectsSupport = class_exists(\Doctrine\Bundle\DoctrineBundle\Controller\ArgumentResolver\EntityValueResolver::class);

    if ($isOrm3) {
        // ORM 3 settings
        $ormConfig['controller_resolver'] = ['auto_mapping' => false];
    } else {
        // ORM 2 settings
        $ormConfig['auto_generate_proxy_classes'] = true;
        if ($hasGhostObjectsSupport) {
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
