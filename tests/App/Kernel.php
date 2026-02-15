<?php

namespace Pallari\GeonameBundle\Tests\App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new \Pallari\GeonameBundle\PallariGeonameBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/PallariGeonameBundle/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/PallariGeonameBundle/logs';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('config/packages/*.yaml');
        $container->import('config/services.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // No routes needed for now
    }
}
