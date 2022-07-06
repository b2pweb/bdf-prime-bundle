<?php

namespace Bdf\PrimeBundle\Configurator;

use Bdf\Prime\ServiceLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PrimeConfigurator
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * PrimeFactory constructor.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Create the repository instance.
     */
    public function configurePrime(ServiceLocator $service)
    {
        $loader = $this->container->getParameter('prime.hydrators');

        if ($loader) {
            $this->configureHydrator($service, $loader);
        }
    }

    /**
     * Create the repository instance.
     */
    private function configureHydrator(ServiceLocator $service, string $loader)
    {
        if (is_file($loader)) {
            $launcher = function () use ($service, $loader) {
                $registry = $service->hydrators();

                include $loader;
            };
            $launcher();
        }
    }
}
