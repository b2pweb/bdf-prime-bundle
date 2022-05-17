<?php

namespace Bdf\PrimeBundle\Tests\Documents;

use Bdf\Prime\MongoDB\Document\DocumentMapper;
use Bdf\Prime\MongoDB\Schema\CollectionDefinitionBuilder;

if (PHP_VERSION_ID >= 70400) {
    class PersonDocumentMapper extends DocumentMapper
    {
        public function connection(): string
        {
            return 'mongo';
        }

        public function collection(): string
        {
            return 'person';
        }

        protected function buildDefinition(CollectionDefinitionBuilder $builder): void
        {
            $builder->collation(['locale' => 'en', 'strength' => 1]);
        }
    }
}
