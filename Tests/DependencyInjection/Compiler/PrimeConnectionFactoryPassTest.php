<?php

namespace Bdf\PrimeBundle\Tests\DependencyInjection\Compiler;

use Bdf\Prime\Connection\Factory\ChainFactory;
use Bdf\PrimeBundle\DependencyInjection\Compiler\PrimeConnectionFactoryPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class PrimeConnectionFactoryPassTest extends TestCase
{
    public function testThrowExceptionWhenNoLoaders()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must tag at least one service as "bdf_prime.connection_factory" to use the "'.ChainFactory::class.'" service');
        $container = new ContainerBuilder();
        $container->register(ChainFactory::class);

        $serializerPass = new PrimeConnectionFactoryPass();
        $serializerPass->process($container);
    }

    public function testServicesAreOrderedAccordingToPriority()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(ChainFactory::class)->setArguments([null]);
        $container->register('n2')->addTag('bdf_prime.connection_factory', ['priority' => 100]);
        $container->register('n1')->addTag('bdf_prime.connection_factory', ['priority' => 200]);
        $container->register('n3')->addTag('bdf_prime.connection_factory');

        $serializerPass = new PrimeConnectionFactoryPass();
        $serializerPass->process($container);

        $expected = [
            new Reference('n1'),
            new Reference('n2'),
            new Reference('n3'),
        ];

        $this->assertEquals($expected, $definition->getArgument(0));
    }
}
