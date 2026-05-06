<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir().'/config/bundles.php';

        foreach ($contents as $class => $environments) {
            if (($environments[$this->environment] ?? $environments['all'] ?? false) === true) {
                yield new $class();
            }
        }
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader): void
    {
        $container->import('../config/{packages}/*.php');
        $container->import('../config/{packages}/'.$this->environment.'/*.php');
        $container->import('../config/{services}.php');
        $container->import('../config/{services}_'.$this->environment.'.php');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('../config/routes.php');
    }
}
