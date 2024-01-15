<?php

namespace Bdf\PrimeBundle\Tests;

require_once __DIR__.'/TestKernel.php';

use Bdf\Prime\Cache\ArrayCache;
use Bdf\Prime\Cache\DoctrineCacheAdapter;
use Bdf\Prime\Configuration;
use Bdf\Prime\Connection\Middleware\LoggerMiddleware;
use Bdf\Prime\Connection\SimpleConnection;
use Bdf\Prime\Console\CriteriaCommand;
use Bdf\Prime\Console\UpgraderCommand;
use Bdf\Prime\Locatorizable;
use Bdf\Prime\Mapper\ContainerMapperFactory;
use Bdf\Prime\Migration\MigrationManager;
use Bdf\Prime\Platform\Sql\Types\SqlStringType;
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
use Bdf\PrimeBundle\Tests\Fixtures\A;
use Bdf\PrimeBundle\Tests\Fixtures\DummyMiddleware;
use Bdf\PrimeBundle\Tests\Fixtures\TestEntity;
use Bdf\PrimeBundle\Tests\Fixtures\TestEntityMapper;
use Bdf\PrimeBundle\Tests\Fixtures\WithInjection;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\DBAL\Driver\Middleware;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

/**
 * BdfSerializerBundleTest.
 */
class BdfPrimeBundleTest extends TestCase
{
    public function testDefaultConfig()
    {
        $builder = new ContainerBuilder();
        $bundle = new PrimeBundle();
        $bundle->build($builder);

        $compilerPasses = $builder->getCompiler()->getPassConfig()->getPasses();
        $found = 0;

        foreach ($compilerPasses as $pass) {
            if ($pass instanceof PrimeConnectionFactoryPass) {
                ++$found;
            }
        }

        $this->assertSame(1, $found);
    }

    public function testKernel()
    {
        $kernel = new TestKernel('dev', true);
        $kernel->boot();

        $this->assertInstanceOf(ServiceLocator::class, $kernel->getContainer()->get('prime'));
        $this->assertSame($kernel->getContainer()->get('prime'), $kernel->getContainer()->get(ServiceLocator::class));
        $this->assertInstanceOf(MigrationManager::class, $kernel->getContainer()->get(MigrationManager::class));
        $this->assertInstanceOf(SimpleConnection::class, $kernel->getContainer()->get(ServiceLocator::class)->connection('test'));
    }

    public function testConsole()
    {
        $kernel = new TestKernel('dev', true);
        $kernel->boot();
        $console = new Application($kernel);

        $this->assertInstanceOf(UpgraderCommand::class, $this->getCommand($console, 'prime:upgrade'));

        if (class_exists(CriteriaCommand::class)) {
            $this->assertInstanceOf(CriteriaCommand::class, $this->getCommand($console, 'prime:criteria'));
        }

        $reflection = new \ReflectionClass(UpgraderCommand::class);
        if ($reflection->hasProperty('migrationManager')) {
            $prop = $reflection->getProperty('migrationManager');
            $prop->setAccessible(true);
            $this->assertSame($kernel->getContainer()->get(MigrationManager::class), $prop->getValue($this->getCommand($console, 'prime:upgrade')));
        }
    }

    /**
     * @return void
     */
    public function testStructureUpgrader()
    {
        if (!interface_exists(StructureUpgraderResolverInterface::class)) {
            $this->markTestSkipped('StructureUpgraderResolverInterface not present');
        }

        $kernel = new TestKernel('dev', true);
        $kernel->boot();

        $this->assertInstanceOf(StructureUpgraderResolverAggregate::class, $kernel->getContainer()->get(StructureUpgraderResolverInterface::class));
        $this->assertInstanceOf(StructureUpgraderResolverAggregate::class, $kernel->getContainer()->get(StructureUpgraderResolverAggregate::class));

        $console = new Application($kernel);
        $command = $this->getCommand($console, UpgraderCommand::getDefaultName());

        $r = new \ReflectionProperty($command, 'resolver');
        $r->setAccessible(true);

        $this->assertSame(
            $kernel->getContainer()->get(StructureUpgraderResolverAggregate::class),
            $r->getValue($command)
        );

        $upgrader = $kernel->getContainer()->get(StructureUpgraderResolverAggregate::class)->resolveByDomainClass(TestEntity::class);

        $this->assertInstanceOf(RepositoryUpgrader::class, $upgrader);
        $this->assertEquals('test_', $upgrader->table()->name());

        $this->assertEquals($upgrader, $kernel->getContainer()->get(StructureUpgraderResolverAggregate::class)->resolveByMapperClass(TestEntityMapper::class));
    }

    public function testCollector()
    {
        $kernel = new TestKernel('dev', true);
        $kernel->boot();

        $collector = $kernel->getContainer()->get(PrimeDataCollector::class);

        $this->assertInstanceOf(PrimeDataCollector::class, $collector);
        $kernel->handle(Request::create('http://127.0.0.1/'));

        $this->assertGreaterThanOrEqual(3, $collector->getQueryCount());

        $queries = array_map(function ($entry) { return $entry['sql']; }, $collector->getQueries()['']);
        $this->assertContains('SELECT * FROM test_ WHERE id = ? LIMIT 1', $queries);
    }

    public function testFunctional()
    {
        $kernel = new TestKernel('dev', true);
        $kernel->boot();

        TestEntity::repository()->schema()->migrate();

        $entity = new TestEntity(['name' => 'foo']);
        $entity->insert();

        $this->assertEquals([$entity], TestEntity::all());
        $this->assertEquals($entity, TestEntity::where('name', 'foo')->first());
    }

    public function testShardingConnection()
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

            protected function configureRoutes(RouteCollectionBuilder $routes)
            {
            }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        $this->assertInstanceOf(ShardingConnection::class, $prime->connection('test'));
        $this->assertInstanceOf(ShardingQuery::class, $prime->connection('test')->builder());

        /** @var SimpleConnection $connection */
        $connection = $prime->connection('test.shard1');

        $expectedConfig = $prime->connection('test')->getConfiguration();

        if (method_exists($expectedConfig, 'withName')) {
            $expectedConfig = $expectedConfig->withName('test.shard1');
        }

        $this->assertEquals($expectedConfig, $connection->getConfiguration());
    }

    public function testArrayCache()
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

            protected function configureRoutes($routes)
            {
            }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        $this->assertEquals(new ArrayCache(), $prime->mappers()->getResultCache());
    }

    public function testPoolCache()
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

            protected function configureRoutes($routes)
            {
            }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        $this->assertEquals(new DoctrineCacheAdapter(DoctrineProvider::wrap(new FilesystemAdapter())), $prime->mappers()->getResultCache());
    }

    public function testGlobalConfig()
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

            protected function configureRoutes($routes)
            {
            }
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

        if (class_exists(LoggerMiddleware::class) && (!class_exists(\Bdf\Prime\MongoDB\Collection\MongoCollectionLocator::class) || class_exists(\Bdf\Prime\MongoDB\Driver\MongoConnectionFactory::class))) {
            $middlewares = $connection->getConfiguration()->getMiddlewares();
            $middlewares = array_values(array_filter($middlewares, function ($middleware) { return $middleware instanceof LoggerMiddleware; }));

            $this->assertNotEmpty($middlewares);
            $this->assertEquals($middlewares[0]->withConfiguration($connection->getConfiguration()), $middlewares[0]);
        }
    }

    public function testMiddlewares()
    {
        if (!interface_exists(Middleware::class)) {
            $this->markTestSkipped('Middlewares are not available');
        }

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
                $loader->import(__DIR__.'/Fixtures/config_with_middleware.yaml');
            }

            protected function configureRoutes($routes)
            {
            }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        /** @var SimpleConnection $connection */
        $test = $prime->connection('test');
        $test2 = $prime->connection('test2');

        $testMiddlewares = $test->getConfiguration()->getMiddlewares();
        $test2Middlewares = $test2->getConfiguration()->getMiddlewares();

        $this->assertContainsOnlyInstancesOf(DummyMiddleware::class, $testMiddlewares);
        $this->assertContainsOnlyInstancesOf(DummyMiddleware::class, $test2Middlewares);

        $testMiddlewares = array_map(function (DummyMiddleware $middleware) { return $middleware->foo; }, $testMiddlewares);
        $test2Middlewares = array_map(function (DummyMiddleware $middleware) { return $middleware->foo; }, $test2Middlewares);

        $this->assertEquals(['global2', 'test', 'global', 'global3'], $testMiddlewares);
        $this->assertEquals(['global2', 'global', 'global3'], $test2Middlewares);
    }

    public function testConnectionConfig()
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

            protected function configureRoutes($routes)
            {
            }
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

    /**
     * Check that destroying entity with prime unconfigured will works.
     */
    public function testEntityDestroyAfterShutdown()
    {
        $this->expectNotToPerformAssertions();

        $kernel = new TestKernel('test', true);
        $kernel->boot();
        TestEntity::repository()->schema()->migrate();
        $entity = new TestEntity(['name' => 'foo']);
        $entity->insert();
        $kernel->shutdown();
        $kernel->boot();
        $kernel->shutdown();

        unset($entity);
    }

    public function testShutdownShouldDisableActiveRecord()
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $this->assertTrue(Locatorizable::isActiveRecordEnabled());
        $kernel->shutdown();
        $this->assertFalse(Locatorizable::isActiveRecordEnabled());
    }

    public function testCustomPlatformTypesNotSupported()
    {
        $this->expectExceptionMessage('Define platform types is only supported by bdf-prime version >= 2.1');

        if (method_exists(Configuration::class, 'addPlatformType')) {
            $this->markTestSkipped();
        }

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
                $loader->import(__DIR__.'/Fixtures/config_with_platform_types.yaml');
            }

            protected function configureRoutes($routes)
            {
            }
        };

        $kernel->boot();
    }

    public function testCustomPlatformTypes()
    {
        if (!method_exists(Configuration::class, 'addPlatformType')) {
            $this->markTestSkipped();
        }

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
                $loader->import(__DIR__.'/Fixtures/config_with_platform_types.yaml');
            }

            protected function configureRoutes($routes)
            {
            }
        };

        $kernel->boot();

        $this->assertInstanceOf(OverriddenString::class, $kernel->getContainer()->get('prime')->connection('test')->platform()->types()->resolve(''));
        $this->assertSame('foo', $kernel->getContainer()->get('prime')->connection('test')->toDatabase(''));
    }

    public function testMapperDependencyInjection()
    {
        if (!class_exists(ContainerMapperFactory::class)) {
            $this->markTestSkipped('ContainerMapperFactory is not available');
        }

        $kernel = new TestKernel('dev', true);
        $kernel->boot();

        $this->assertInstanceOf(A::class, WithInjection::repository()->mapper()->a);
        $this->assertSame('bar', WithInjection::repository()->mapper()->a->foo);
    }

    private function getCommand(Application $console, string $name): Command
    {
        $command = $console->get($name);

        if ($command instanceof LazyCommand) {
            return $command->getCommand();
        }

        return $command;
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

class OverriddenString extends SqlStringType
{
    public function toDatabase($value)
    {
        return 'foo';
    }
}
