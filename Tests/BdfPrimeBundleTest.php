<?php

namespace Bdf\PrimeBundle\Tests;

require_once __DIR__.'/TestKernel.php';

use Bdf\Prime\Cache\ArrayCache;
use Bdf\Prime\Cache\DoctrineCacheAdapter;
use Bdf\Prime\Connection\SimpleConnection;
use Bdf\Prime\Console\UpgraderCommand;
use Bdf\Prime\Migration\MigrationManager;
use Bdf\Prime\Schema\RepositoryUpgrader;
use Bdf\Prime\Schema\StructureUpgraderResolverAggregate;
use Bdf\Prime\Schema\StructureUpgraderResolverInterface;
use Bdf\Prime\ServiceLocator;
use Bdf\Prime\Sharding\ShardingConnection;
use Bdf\Prime\Sharding\ShardingQuery;
use Bdf\Prime\Types\ArrayType;
use Bdf\Prime\Types\TypeInterface;
use Bdf\PrimeBundle\Collector\PrimeDataCollector;
use Bdf\PrimeBundle\DependencyInjection\Compiler\PrimeConnectionFactoryPass;
use Bdf\PrimeBundle\PrimeBundle;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\DBAL\Driver;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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
     * @return void
     */
    public function test_structure_upgrader()
    {
        if (!interface_exists(StructureUpgraderResolverInterface::class)) {
            $this->markTestSkipped('StructureUpgraderResolverInterface not present');
        }

        $kernel = new \TestKernel('dev', true);
        $kernel->boot();

        $this->assertInstanceOf(StructureUpgraderResolverAggregate::class, $kernel->getContainer()->get(StructureUpgraderResolverInterface::class));
        $this->assertInstanceOf(StructureUpgraderResolverAggregate::class, $kernel->getContainer()->get(StructureUpgraderResolverAggregate::class));

        $console = new Application($kernel);
        $command = $console->get(UpgraderCommand::getDefaultName());
        $r = new \ReflectionProperty($command, 'resolver');
        $r->setAccessible(true);

        $this->assertSame(
            $kernel->getContainer()->get(StructureUpgraderResolverAggregate::class),
            $r->getValue($command)
        );

        $upgrader = $kernel->getContainer()->get(StructureUpgraderResolverAggregate::class)->resolveByDomainClass(\TestEntity::class);

        $this->assertInstanceOf(RepositoryUpgrader::class, $upgrader);
        $this->assertEquals('test_', $upgrader->table()->name());

        $this->assertEquals($upgrader, $kernel->getContainer()->get(StructureUpgraderResolverAggregate::class)->resolveByMapperClass(\TestEntityMapper::class));
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

        $isDoctrine2 = method_exists(Driver::class, 'getDatabase');

        $this->assertEquals($isDoctrine2 ? 3 : 4, $collector->getQueryCount()); // Doctrine 3 always perform a select query on Connection::getDatabase()
        $this->assertEquals('SELECT * FROM test_ WHERE id = ? LIMIT 1', $collector->getQueries()[''][$isDoctrine2 ? 3 : 4]['sql']);
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

            public function registerBundles(): iterable
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

        /** @var SimpleConnection $connection */
        $connection = $prime->connection('test.shard1');
        $this->assertSame($prime->connection('test')->getConfiguration(), $connection->getConfiguration());
    }

    /**
     *
     */
    public function test_array_cache()
    {
        $kernel = new class('test', true) extends Kernel {
            use \Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

            public function registerBundles(): iterable
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

            protected function configureRoutes($routes) { }
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

            public function registerBundles(): iterable
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

            protected function configureRoutes($routes) { }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        $this->assertEquals(new DoctrineCacheAdapter(DoctrineProvider::wrap(new FilesystemAdapter())), $prime->mappers()->getResultCache());
    }

    /**
     *
     */
    public function test_global_config()
    {
        $kernel = new class('test', true) extends Kernel {
            use \Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

            public function registerBundles(): iterable
            {
                return [
                    new FrameworkBundle(),
                    new PrimeBundle(),
                ];
            }

            protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
            {
                $loader->import(__DIR__.'/Fixtures/config.yaml');
            }

            protected function configureRoutes($routes) { }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        /** @var SimpleConnection $connection */
        $connection = $prime->connection('test2');

        $this->assertNotNull($connection->getConfiguration()->getSQLLogger());
        $this->assertTrue($connection->getConfiguration()->getAutoCommit());
        $this->assertInstanceOf(FooType::class, $connection->getConfiguration()->getTypes()->get('foo'));
        $this->assertInstanceOf(BarType::class, $connection->getConfiguration()->getTypes()->get('bar'));
        $this->assertInstanceOf(ArrayType::class, $connection->getConfiguration()->getTypes()->get('array'));
    }

    /**
     *
     */
    public function test_connection_config()
    {
        $kernel = new class('test', true) extends Kernel {
            use \Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

            public function registerBundles(): iterable
            {
                return [
                    new FrameworkBundle(),
                    new PrimeBundle(),
                ];
            }

            protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
            {
                $loader->import(__DIR__.'/Fixtures/config.yaml');
            }

            protected function configureRoutes($routes) { }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        /** @var SimpleConnection $connection */
        $connection = $prime->connection('test');

        $this->assertNull($connection->getConfiguration()->getSQLLogger());
        $this->assertFalse($connection->getConfiguration()->getAutoCommit());
        $this->assertInstanceOf(BarType::class, $connection->getConfiguration()->getTypes()->get('foo'));
        $this->assertInstanceOf(BarType::class, $connection->getConfiguration()->getTypes()->get('bar'));
        $this->assertInstanceOf(ArrayType::class, $connection->getConfiguration()->getTypes()->get('array'));
    }
}

class FooType implements TypeInterface
{
    public function fromDatabase($value, array $fieldOptions = [])
    {
        // TODO: Implement fromDatabase() method.
    }

    public function toDatabase($value)
    {
        // TODO: Implement toDatabase() method.
    }

    public function name(): string
    {
        // TODO: Implement name() method.
    }

    public function phpType(): string
    {
        // TODO: Implement phpType() method.
    }
}
class BarType implements TypeInterface
{
    public function fromDatabase($value, array $fieldOptions = [])
    {
        // TODO: Implement fromDatabase() method.
    }

    public function toDatabase($value)
    {
        // TODO: Implement toDatabase() method.
    }

    public function name(): string
    {
        // TODO: Implement name() method.
    }

    public function phpType(): string
    {
        // TODO: Implement phpType() method.
    }
}
