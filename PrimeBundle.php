<?php

namespace Bdf\PrimeBundle;

use Bdf\Prime\Locatorizable;
use Bdf\Prime\ServiceLocator;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * PrimeBundle
 *
 * @author Seb
 */
class PrimeBundle extends Bundle
{
    /**
     * {@inheritDoc}
     */
    public function boot()
    {
        if ($this->container->getParameter('prime.locatorizable')) {
            Locatorizable::configure(function() {
                return $this->container->get('prime');
            });
        }
    }

    /**
     * {@inheritDoc}
     */
    public function shutdown()
    {
        if (!$this->container->initialized('prime')) {
            return;
        }

        /** @var ServiceLocator $prime */
        $prime = $this->container->get('prime');

        // Clear circle references
        $prime->clearRepositories();

        // Close all loaded connections
        foreach ($prime->connections() as $connection) {
            $connection->close();
        }
    }
}
