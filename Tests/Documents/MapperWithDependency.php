<?php

namespace Bdf\PrimeBundle\Tests\Documents;

use Bdf\Prime\MongoDB\Collection\MongoCollectionLocator;
use Bdf\Prime\MongoDB\Document\DocumentMapper;
use Bdf\Prime\MongoDB\Document\Hydrator\DocumentHydratorFactory;

if (PHP_VERSION_ID >= 70400) {
    class MapperWithDependency extends DocumentMapper
    {
        public $locator;

        public function __construct(MongoCollectionLocator $locator, ?DocumentHydratorFactory $hydratorFactory = null)
        {
            parent::__construct(null, $hydratorFactory);
            $this->locator = $locator;
        }

        public function connection(): string
        {
            return 'mongo';
        }

        public function collection(): string
        {
            return 'foo';
        }
    }
}
