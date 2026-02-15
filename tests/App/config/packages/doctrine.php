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

    // Detect if we are on DoctrineBundle 3.0+
    $isBundle3 = !class_exists(\Doctrine\Bundle\DoctrineBundle\Command\Proxy\ImportDoctrineProxyCommand::class);

    if ($isBundle3) {
        // Modern configuration for DoctrineBundle 3.0 (PHP 8.4+)
        $ormConfig['controller_resolver'] = ['auto_mapping' => false];
    } else {
        // Legacy configuration for DoctrineBundle 2.x
        $ormConfig['auto_generate_proxy_classes'] = true;
    }

    $container->extension('doctrine', [
        'dbal' => [
            'url' => '%env(resolve:DATABASE_URL)%',
        ],
        'orm' => $ormConfig,
    ]);
};
