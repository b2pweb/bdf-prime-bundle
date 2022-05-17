<?php

namespace Bdf\PrimeBundle\Tests;

require_once __DIR__.'/TestKernel.php';

use Bdf\Prime\MongoDB\Collection\MongoCollectionLocator;
use Bdf\Prime\MongoDB\Document\DocumentMapper;
use Bdf\Prime\MongoDB\Document\MongoDocument;
use Bdf\Prime\MongoDB\Schema\CollectionDefinitionBuilder;
use Bdf\Prime\MongoDB\Schema\CollectionSchemaResolver;
use Bdf\Prime\Query\Expression\Like;
use Bdf\PrimeBundle\Tests\Documents\MapperWithDependency;
use Bdf\PrimeBundle\Tests\Documents\OtherDocumentMapper;
use Bdf\PrimeBundle\Tests\Documents\PersonDocument;
use Bdf\PrimeBundle\Tests\Documents\PersonDocumentMapper;
use PHPUnit\Framework\TestCase;

/**
 * BdfSerializerBundleTest
 */
class WithMongoTest extends TestCase
{
    /**
     *
     */
    public function test_kernel()
    {
        $kernel = new \TestKernel('dev', true);
        $kernel->boot();

        $this->assertInstanceOf(MongoCollectionLocator::class, $kernel->getContainer()->get(MongoCollectionLocator::class));
        $this->assertInstanceOf(CollectionSchemaResolver::class, $kernel->getContainer()->get(CollectionSchemaResolver::class));
    }

    /**
     *
     */
    public function test_functional()
    {
        $kernel = new \TestKernel('dev', true);
        $kernel->boot();

        $kernel->getContainer()->get(CollectionSchemaResolver::class)->resolveByDocumentClass(PersonDocument::class)->migrate();

        $person1 = new PersonDocument('John', 'Doe');
        $person2 = new PersonDocument('Jean', 'Dupont');

        $person1->save();
        $person2->save();

        $this->assertEquals([$person1], PersonDocument::where('firstName', 'john')->all());
        $this->assertEquals([$person1, $person2], PersonDocument::where('lastName', (new Like('o'))->contains())->all());

        $kernel->getContainer()->get(CollectionSchemaResolver::class)->resolveByDocumentClass(PersonDocument::class)->drop();
    }

    public function test_mapper_with_dependency_injection()
    {
        $kernel = new \TestKernel('dev', true);
        $kernel->boot();

        /** @var MongoCollectionLocator $locator */
        $locator = $kernel->getContainer()->get(MongoCollectionLocator::class);

        $collection = $locator->collectionByMapper(MapperWithDependency::class);

        $this->assertInstanceOf(MapperWithDependency::class, $collection->mapper());
        $this->assertSame($locator, $collection->mapper()->locator);
    }

    public function test_same_hydrator_instance_should_be_used()
    {
        $kernel = new \TestKernel('dev', true);
        $kernel->boot();

        /** @var MongoCollectionLocator $locator */
        $locator = $kernel->getContainer()->get(MongoCollectionLocator::class);

        $c1 = $locator->collectionByMapper(OtherDocumentMapper::class);
        $c2 = $locator->collectionByMapper(PersonDocumentMapper::class);

        $c1->mapper()->fromDatabase([], $c1->connection()->platform()->types());
        $c2->mapper()->fromDatabase([], $c1->connection()->platform()->types());

        $r = new \ReflectionProperty(DocumentMapper::class, 'hydrator');
        $r->setAccessible(true);

        $this->assertSame($r->getValue($c1->mapper()), $r->getValue($c2->mapper()));
    }
}
