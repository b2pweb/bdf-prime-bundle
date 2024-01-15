<?php

namespace Bdf\PrimeBundle\Connection;

use Bdf\Prime\Configuration;
use Bdf\Prime\Connection\Configuration\ConfigurationResolverInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Allows declaration of configuration custom by connection. Use a default connection if the configuration is not set.
 */
class ConfigurationResolver implements ConfigurationResolverInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $id;

    /**
     * ConfigurationResolver constructor.
     */
    public function __construct(ContainerInterface $container, string $id = 'prime.%s_connection.configuration')
    {
        $this->container = $container;
        $this->id = $id;
    }

    public function getConfiguration(string $connectionName): ?Configuration
    {
        try {
            return $this->container->get(sprintf($this->id, $connectionName));
        } catch (ContainerExceptionInterface $exception) {
        }

        return null;
    }
}
