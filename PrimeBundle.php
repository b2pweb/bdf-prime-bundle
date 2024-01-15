<?php

namespace Bdf\PrimeBundle;

use Bdf\Prime\Locatorizable;
use Bdf\Prime\MongoDB\Collection\MongoCollectionLocator;
use Bdf\Prime\MongoDB\Mongo;
use Bdf\Prime\ServiceLocator;
use Bdf\PrimeBundle\DependencyInjection\Compiler\IgnorePrimeAnnotationsPass;
use Bdf\PrimeBundle\DependencyInjection\Compiler\PrimeConnectionFactoryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * PrimeBundle.
 *
 * @author Seb
 */
class PrimeBundle extends Bundle
{
    public function boot()
    {
        if ($this->container->getParameter('prime.locatorizable')) {
            Locatorizable::configure(function () {
                return $this->container->get('prime');
            });

            if (class_exists(Mongo::class)) {
                Mongo::configure(function () {
                    return $this->container->get(MongoCollectionLocator::class);
                });
            }
        }
    }

    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new PrimeConnectionFactoryPass());
        $container->addCompilerPass(new IgnorePrimeAnnotationsPass());
    }

    public function shutdown()
    {
        if ($this->container->initialized('prime')) {
            /** @var ServiceLocator $prime */
            $prime = $this->container->get('prime');

            // Clear circle references
            $prime->clearRepositories();

            // Close all loaded connections
            foreach ($prime->connections() as $connection) {
                $connection->close();
            }
        }

        // Always unconfigure locator, because it's configured even if prime is not initialized
        if ($this->container->getParameter('prime.locatorizable')) {
            Locatorizable::configure(null);

            if (class_exists(Mongo::class)) {
                Mongo::configure(null);
            }
        }
    }
}
