<?php

namespace Bdf\PrimeBundle\Attribute;

use Symfony\Component\DependencyInjection\Attribute\AutowireInline;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Attribute use to inject a prime repository on a method parameter.
 *
 * Usage:
 * ```php
 * class MyUserService
 * {
 *     public function __construct(
 *         #[Repository(User::class)
 *         private readonly EntityRepository $repository,
 *     ) {}
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Repository extends AutowireInline
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(string $entityClass)
    {
        parent::__construct([new Reference('prime'), 'repository'], [$entityClass]);
    }
}
