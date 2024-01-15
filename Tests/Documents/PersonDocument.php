<?php

namespace Bdf\PrimeBundle\Tests\Documents;

use Bdf\Prime\MongoDB\Document\MongoDocument;

if (PHP_VERSION_ID >= 70400) {
    class PersonDocument extends MongoDocument
    {
        public ?string $firstName = null;
        public ?string $lastName = null;

        public function __construct(string $firstName = null, string $lastName = null)
        {
            $this->firstName = $firstName;
            $this->lastName = $lastName;
        }
    }
}
