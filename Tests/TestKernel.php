<?php

namespace Bdf\PrimeBundle\Tests;

use Bdf\PrimeBundle\Tests\Fixtures\TestEntity;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouteCollectionBuilder;

class TestKernel extends \Symfony\Component\HttpKernel\Kernel
{
    use \Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Symfony\Bundle\WebProfilerBundle\WebProfilerBundle(),
            new \Symfony\Bundle\TwigBundle\TwigBundle(),
            // new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new \Bdf\PrimeBundle\PrimeBundle(),
        ];
    }

    protected function configureRoutes($routes)
    {
        // $routes->add('index', '/')->controller([$this, 'indexAction']);
        if ($routes instanceof RouteCollectionBuilder) {
            $routes->add('/', 'kernel::indexAction');
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml', '/_wdt');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml', '/_profiler');
        } else {
            $routes->add('index', '/')->controller([$this, 'indexAction']);
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml');
        }
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/conf.yaml');
        // $c->import(__DIR__.'/conf.yaml');
    }

    public function indexAction()
    {
        TestEntity::repository()->schema()->migrate();
        TestEntity::findById(5);

        return new \Symfony\Component\HttpFoundation\Response(<<<HTML
<!DOCTYPE html>
<html>
    <body>Hello World !</body>
</html>
HTML
        );
    }
}
