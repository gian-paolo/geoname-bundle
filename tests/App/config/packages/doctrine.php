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

    // Detect Doctrine ORM version safely
    $isOrm2 = method_exists(\Doctrine\ORM\Configuration::class, 'getAutoGenerateProxyClasses');
    
    // Detect Doctrine Bundle version
    // enable_lazy_ghost_objects was added in 2.12
    $hasGhostObjectsOption = class_exists(\Doctrine\Bundle\DoctrineBundle\Controller\ArgumentResolver\EntityValueResolver::class);

    if (!$isOrm2) {
        // ORM 3: Many legacy options are gone
        $ormConfig['controller_resolver'] = ['auto_mapping' => false];
    } else {
        // ORM 2
        $ormConfig['auto_generate_proxy_classes'] = true;
        if ($hasGhostObjectsOption) {
            // We enable it ONLY if we are on ORM 2 and the bundle supports it.
            // On ORM 3 it's mandatory and cannot be disabled.
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
