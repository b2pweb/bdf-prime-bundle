<?php

namespace Bdf\PrimeBundle\DependencyInjection\Compiler;

use Bdf\Prime\Connection\Factory\ChainFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Registers all service tag as loader into the serializer.
 */
class PrimeConnectionFactoryPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $service;
    private $tag;

    public function __construct(string $service = ChainFactory::class, string $loaderTag = 'bdf_prime.connection_factory')
    {
        $this->service = $service;
        $this->tag = $loaderTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->service)) {
            return;
        }

        if (!$factories = $this->findAndSortTaggedServices($this->tag, $container)) {
            throw new RuntimeException(sprintf('You must tag at least one service as "%s" to use the "%s" service.', $this->tag, $this->service));
        }

        $connectionFactory = $container->getDefinition($this->service);
        $connectionFactory->replaceArgument(0, $factories);
    }
}
