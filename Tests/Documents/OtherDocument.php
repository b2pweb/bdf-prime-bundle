<?php

namespace Bdf\PrimeBundle\Tests\Documents;

use Bdf\Prime\MongoDB\Document\MongoDocument;

if (PHP_VERSION_ID >= 70400) {
    class OtherDocument extends MongoDocument
    {
        public ?string $foo = null;
    }
}
