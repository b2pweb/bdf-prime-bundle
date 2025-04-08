<?php

namespace Bdf\PrimeBundle\DependencyInjection\Compiler;

use Bdf\Prime\Clock\ClockAwareInterface;
use Bdf\Prime\Clock\NativeClock;
use Bdf\Prime\Mapper\ContainerMapperFactory;
use Bdf\Prime\Mapper\MapperFactory;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Register the clock service into mappers, if available.
 */
final class RegisterClockPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!\interface_exists(ClockInterface::class) || !\interface_exists(ClockAwareInterface::class)) {
            return;
        }

        if ($container->hasDefinition(ClockInterface::class) || $container->hasAlias(ClockInterface::class)) {
            $clockRef = new Reference(ClockInterface::class);
        } else {
            $container->register('prime.clock', NativeClock::class);
            $clockRef = new Reference('prime.clock');
        }

        $container
            ->findDefinition(MapperFactory::class)
            ->setArgument('$clock', $clockRef)
        ;

        $container
            ->findDefinition(ContainerMapperFactory::class)
            ->setArgument('$clock', $clockRef)
        ;
    }
}
