<?php

namespace Bdf\PrimeBundle\Tests\Fixtures;

class A
{
    public $foo;

    public function __construct(string $foo)
    {
        $this->foo = $foo;
    }
}
