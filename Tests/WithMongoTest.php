<?php

namespace Bdf\PrimeBundle\Tests;

require_once __DIR__.'/TestKernel.php';

use Bdf\Prime\MongoDB\Collection\MongoCollectionLocator;
use Bdf\Prime\MongoDB\Document\DocumentMapper;
use Bdf\Prime\MongoDB\Document\MongoDocument;
use Bdf\Prime\MongoDB\Schema\CollectionDefinitionBuilder;
use Bdf\Prime\MongoDB\Schema\CollectionSchemaResolver;
use Bdf\Prime\MongoDB\Schema\CollectionStructureUpgrader;
use Bdf\Prime\MongoDB\Schema\CollectionStructureUpgraderResolver;
use Bdf\Prime\Query\Expression\Like;
use Bdf\Prime\Schema\StructureUpgraderResolverAggregate;
use Bdf\PrimeBundle\PrimeBundle;
use Bdf\PrimeBundle\Tests\Documents\MapperWithDependency;
use Bdf\PrimeBundle\Tests\Documents\OtherDocumentMapper;
use Bdf\PrimeBundle\Tests\Documents\PersonDocument;
use Bdf\PrimeBundle\Tests\Documents\PersonDocumentMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * BdfSerializerBundleTest
 */
class WithMongoTest extends TestCase
{
    private $kernel;

    /**
     *
     */
    protected function setUp(): void
    {
        if (!class_exists(MongoCollectionLocator::class)) {
            $this->markTestSkipped('MongoDB driver not installed');
        }


        $this->kernel = new class('test', true) extends Kernel {
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
                $loader->import(__DIR__.'/Fixtures/conf_mongo.yaml');
            }

            protected function configureRoutes($routes) { }
        };
        $this->kernel->boot();
    }

    /**
     *
     */
    public function test_kernel()
    {
        $this->assertInstanceOf(MongoCollectionLocator::class, $this->kernel->getContainer()->get(MongoCollectionLocator::class));
        $this->assertInstanceOf(CollectionStructureUpgraderResolver::class, $this->kernel->getContainer()->get(CollectionStructureUpgraderResolver::class));
    }

    /**
     *
     */
    public function test_functional()
    {
        $this->kernel->getContainer()->get(CollectionStructureUpgraderResolver::class)->resolveByDomainClass(PersonDocument::class)->migrate();

        $person1 = new PersonDocument('John', 'Doe');
        $person2 = new PersonDocument('Jean', 'Dupont');

        $person1->save();
        $person2->save();

        $this->assertEquals([$person1], PersonDocument::where('firstName', 'john')->all());
        $this->assertEquals([$person1, $person2], PersonDocument::where('lastName', (new Like('o'))->contains())->all());

        $this->kernel->getContainer()->get(CollectionStructureUpgraderResolver::class)->resolveByDomainClass(PersonDocument::class)->drop();
    }

    public function test_mapper_with_dependency_injection()
    {
        /** @var MongoCollectionLocator $locator */
        $locator = $this->kernel->getContainer()->get(MongoCollectionLocator::class);

        $collection = $locator->collectionByMapper(MapperWithDependency::class);

        $this->assertInstanceOf(MapperWithDependency::class, $collection->mapper());
        $this->assertSame($locator, $collection->mapper()->locator);
    }

    public function test_same_hydrator_instance_should_be_used()
    {
        /** @var MongoCollectionLocator $locator */
        $locator = $this->kernel->getContainer()->get(MongoCollectionLocator::class);

        $c1 = $locator->collectionByMapper(OtherDocumentMapper::class);
        $c2 = $locator->collectionByMapper(PersonDocumentMapper::class);

        $c1->mapper()->fromDatabase([], $c1->connection()->platform()->types());
        $c2->mapper()->fromDatabase([], $c1->connection()->platform()->types());

        $r = new \ReflectionProperty(DocumentMapper::class, 'hydrator');
        $r->setAccessible(true);

        $this->assertSame($r->getValue($c1->mapper()), $r->getValue($c2->mapper()));
    }

    public function test_upgrader()
    {
        if (!class_exists(StructureUpgraderResolverAggregate::class)) {
            $this->markTestSkipped('StructureUpgraderResolverAggregate is not found');
        }

        $this->assertInstanceOf(CollectionStructureUpgrader::class, $this->kernel->getContainer()->get(StructureUpgraderResolverAggregate::class)->resolveByDomainClass(PersonDocument::class));
        $this->assertInstanceOf(CollectionStructureUpgrader::class, $this->kernel->getContainer()->get(StructureUpgraderResolverAggregate::class)->resolveByMapperClass(PersonDocumentMapper::class));
    }
}
