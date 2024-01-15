<?php

namespace Bdf\PrimeBundle\Tests\Fixtures;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

class DummyMiddleware implements Middleware
{
    public $foo;

    public function __construct(string $foo = '')
    {
        $this->foo = $foo;
    }

    public function wrap(Driver $driver): Driver
    {
        return $driver;
    }
}
