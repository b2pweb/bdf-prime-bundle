<?php

namespace Bdf\PrimeBundle\Tests\Fixtures;

use Bdf\Prime\Entity\Model;

class TestEntity extends Model
{
    public $id;
    public $name;
    public $dateInsert;
    public $parentId;
    public $parent;

    public function __construct(array $attributes = [])
    {
        $this->import($attributes);
    }
}
