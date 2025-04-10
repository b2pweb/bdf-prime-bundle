<?php

namespace Bdf\PrimeBundle\Tests\Fixtures\Php81;

enum MyStringEnum: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
