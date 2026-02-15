<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $ormConfig = [
        'report_fields_where_declared' => true,
        'validate_xml_mapping' => true,
        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
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

    // Detect if we are on ORM 3 (modern) or ORM 2 (legacy)
    $isOrm3 = !method_exists(\Doctrine\ORM\Configuration::class, 'getAutoGenerateProxyClasses');

    if (!$isOrm3) {
        $ormConfig['auto_generate_proxy_classes'] = true;
    } else {
        // ORM 3 specific modern settings
        $ormConfig['controller_resolver'] = ['auto_mapping' => false];
    }

    $container->extension('doctrine', [
        'dbal' => [
            'url' => '%env(resolve:DATABASE_URL)%',
        ],
        'orm' => $ormConfig,
    ]);
};
