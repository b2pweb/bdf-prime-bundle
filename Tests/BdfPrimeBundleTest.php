<?php

namespace Bdf\PrimeBundle\Tests;

require_once __DIR__.'/TestKernel.php';

use Bdf\Prime\Cache\ArrayCache;
use Bdf\Prime\Cache\DoctrineCacheAdapter;
use Bdf\Prime\Connection\SimpleConnection;
use Bdf\Prime\Migration\MigrationManager;
use Bdf\Prime\ServiceLocator;
use Bdf\Prime\Sharding\ShardingConnection;
use Bdf\Prime\Sharding\ShardingQuery;
use Bdf\PrimeBundle\Collector\PrimeDataCollector;
use Bdf\PrimeBundle\DependencyInjection\Compiler\PrimeConnectionFactoryPass;
use Bdf\PrimeBundle\PrimeBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\DoctrineProvider;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

/**
 * BdfSerializerBundleTest
 */
class BdfPrimeBundleTest extends TestCase
{
    public function test_default_config()
    {
        $builder = new ContainerBuilder();
        $bundle = new PrimeBundle();
        $bundle->build($builder);

        $compilerPasses = $builder->getCompiler()->getPassConfig()->getPasses();
        $found = 0;

        foreach ($compilerPasses as $pass) {
            if ($pass instanceof PrimeConnectionFactoryPass) {
                $found++;
            }
        }

        $this->assertSame(1, $found);
    }

    /**
     *
     */
    public function test_kernel()
    {
        $kernel = new \TestKernel('dev', true);
        $kernel->boot();

        $this->assertInstanceOf(ServiceLocator::class, $kernel->getContainer()->get('prime'));
        $this->assertSame($kernel->getContainer()->get('prime'), $kernel->getContainer()->get(ServiceLocator::class));
        $this->assertInstanceOf(MigrationManager::class, $kernel->getContainer()->get(MigrationManager::class));
        $this->assertInstanceOf(SimpleConnection::class, $kernel->getContainer()->get(ServiceLocator::class)->connection('test'));
    }

    /**
     *
     */
    public function test_collector()
    {
        $kernel = new \TestKernel('dev', true);
        $kernel->boot();

        $collector = $kernel->getContainer()->get(PrimeDataCollector::class);

        $this->assertInstanceOf(PrimeDataCollector::class, $collector);
        $kernel->handle(Request::create('http://127.0.0.1/'));

        $this->assertEquals(3, $collector->getQueryCount());
        $this->assertEquals('SELECT * FROM test_ WHERE id = ? LIMIT 1', $collector->getQueries()[''][3]['sql']);
    }

    /**
     *
     */
    public function test_functional()
    {
        $kernel = new \TestKernel('dev', true);
        $kernel->boot();

        \TestEntity::repository()->schema()->migrate();

        $entity = new \TestEntity(['name' => 'foo']);
        $entity->insert();

        $this->assertEquals([$entity], \TestEntity::all());
        $this->assertEquals($entity, \TestEntity::where('name', 'foo')->first());
    }

    /**
     *
     */
    public function test_sharding_connection()
    {
        $kernel = new class('test', true) extends Kernel {
            use \Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

            public function registerBundles()
            {
                return [
                    new FrameworkBundle(),
                    new PrimeBundle(),
                ];
            }

            protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
            {
                $loader->import(__DIR__.'/Fixtures/sharding.yaml');
            }

            protected function configureRoutes(RouteCollectionBuilder $routes) { }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        $this->assertInstanceOf(ShardingConnection::class, $prime->connection('test'));
        $this->assertInstanceOf(ShardingQuery::class, $prime->connection('test')->builder());
    }

    /**
     *
     */
    public function test_array_cache()
    {
        $kernel = new class('test', true) extends Kernel {
            use \Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

            public function registerBundles()
            {
                return [
                    new FrameworkBundle(),
                    new PrimeBundle(),
                ];
            }

            protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
            {
                $loader->import(__DIR__.'/Fixtures/array_cache.yaml');
            }

            protected function configureRoutes(RouteCollectionBuilder $routes) { }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        $this->assertEquals(new ArrayCache(), $prime->mappers()->getResultCache());
    }

    /**
     *
     */
    public function test_pool_cache()
    {
        $kernel = new class('test', true) extends Kernel {
            use \Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

            public function registerBundles()
            {
                return [
                    new FrameworkBundle(),
                    new PrimeBundle(),
                ];
            }

            protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
            {
                $c->register('custom.cache', FilesystemAdapter::class);

                $loader->import(__DIR__.'/Fixtures/pool_cache.yaml');
            }

            protected function configureRoutes(RouteCollectionBuilder $routes) { }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        $this->assertEquals(new DoctrineCacheAdapter(new DoctrineProvider(new FilesystemAdapter())), $prime->mappers()->getResultCache());
    }
}
