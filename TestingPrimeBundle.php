<?php

namespace Bdf\PrimeBundle;

use Bdf\Prime\Prime;
use Bdf\Prime\Test\TestPack;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * TestingPrimeBundle.
 *
 * @author Seb
 */
class TestingPrimeBundle extends Bundle
{
    /**
     * {@inheritDoc}
     */
    public function boot()
    {
        Prime::configure($this->container);
        TestPack::pack()->initialize();
    }

    /**
     * {@inheritDoc}
     */
    public function shutdown()
    {
        TestPack::pack()->clear();
        TestPack::pack()->destroy();
        Prime::configure(null);
    }
}
