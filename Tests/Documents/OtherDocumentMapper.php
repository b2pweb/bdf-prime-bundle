<?php

namespace Bdf\PrimeBundle\Tests\Documents;

use Bdf\Prime\MongoDB\Document\DocumentMapper;

if (PHP_VERSION_ID >= 70400) {
    class OtherDocumentMapper extends DocumentMapper
    {
        public function connection(): string
        {
            return 'mongo';
        }

        public function collection(): string
        {
            return 'other';
        }
    }
}
