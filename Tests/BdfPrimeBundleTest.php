<?php

namespace Bdf\PrimeBundle\Tests\DependencyInjection;

require_once __DIR__.'/TestKernel.php';

use Bdf\Prime\Connection\SimpleConnection;
use Bdf\Prime\Migration\MigrationManager;
use Bdf\Prime\ServiceLocator;
use Bdf\Prime\Sharding\ShardingConnection;
use Bdf\Prime\Sharding\ShardingQuery;
use Bdf\PrimeBundle\Collector\PrimeDataCollector;
use Bdf\PrimeBundle\PrimeBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;

/**
 * BdfSerializerBundleTest
 */
class BdfPrimeBundleTest extends TestCase
{
    public function test_default_config()
    {
        $builder = $this->createMock(ContainerBuilder::class);

        $bundle = new PrimeBundle();

        $this->assertNull($bundle->build($builder));
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

            public function configureContainer(ContainerConfigurator $c)
            {
                $c->import(__DIR__.'/sharding.yaml');
            }
        };

        $kernel->boot();

        /** @var ServiceLocator $prime */
        $prime = $kernel->getContainer()->get(ServiceLocator::class);
        $this->assertInstanceOf(ShardingConnection::class, $prime->connection('test'));
        $this->assertInstanceOf(ShardingQuery::class, $prime->connection('test')->builder());
    }
}
