<?php

namespace Bdf\PrimeBundle\Tests\Fixtures\Php81;

use Bdf\Prime\Entity\Model;

class WithEnumEntity extends Model
{
    public $id;
    public $intEnum;
    public $stringEnum;
    public $unitEnum;

    public function __construct(array $data = [])
    {
        $this->import($data);
    }
}
