<?php

namespace Bdf\PrimeBundle\Tests\Connection;

use Bdf\Prime\Configuration;
use Bdf\PrimeBundle\Connection\ConfigurationResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 *
 */
class ConfigurationResolverTest extends TestCase
{
    /**
     *
     */
    public function test_unknwon_key()
    {
        $container = $this->createMock(ContainerInterface::class);

        $resolver = new ConfigurationResolver($container);

        $this->assertNull($resolver->getConfiguration('unknown'));
    }

    /**
     *
     */
    public function test_get_config()
    {
        $configuration = new Configuration();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->with('test')->willReturn($configuration);

        $resolver = new ConfigurationResolver($container, '%s');

        $this->assertSame($configuration, $resolver->getConfiguration('test'));
    }
}