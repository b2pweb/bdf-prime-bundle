<?php

namespace Bdf\PrimeBundle\DependencyInjection\Compiler;

use Doctrine\DBAL\Driver\Middleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers all service tag as middleware into connection configuration.
 */
class PrimeMiddlewarePass implements CompilerPassInterface
{
    public const TAG = 'bdf_prime.middleware';

    private $tag;

    public function __construct(string $loaderTag = self::TAG)
    {
        $this->tag = $loaderTag;
    }

    public function process(ContainerBuilder $container)
    {
        // Skip if middleware are not available on the installed version of doctrine/dbal
        if (!\interface_exists(Middleware::class)) {
            return;
        }

        /**
         * @var list<array{
         *     middleware: Reference,
         *     priority: int,
         *     connections: array<string, string>,
         * }> $middlewares
         */
        $middlewares = [];

        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $middleware = [
                'middleware' => new Reference($id),
                'priority' => 0,
                'connections' => [],
            ];

            foreach ($tags as $tag) {
                if (isset($tag['priority'])) {
                    $middleware['priority'] = $tag['priority'];
                }

                if (isset($tag['connection'])) {
                    $middleware['connections'][$tag['connection']] = $tag['connection'];
                }

                if (isset($tag['connections'])) {
                    foreach ($tag['connections'] as $connection) {
                        $middleware['connections'][$connection] = $connection;
                    }
                }
            }

            $middlewares[] = $middleware;
        }

        \usort($middlewares, function ($a, $b) { return $b['priority'] <=> $a['priority']; });

        foreach ($container->findTaggedServiceIds('bdf_prime.configuration') as $id => $tags) {
            $connectionName = $tags[0]['connection'] ?? null;

            // Should not happen
            if (!$connectionName) {
                continue;
            }

            $middlewaresForConnection = [];

            foreach ($middlewares as $middleware) {
                if (empty($middleware['connections']) || isset($middleware['connections'][$connectionName])) {
                    $middlewaresForConnection[] = $middleware['middleware'];
                }
            }

            $definition = $container->getDefinition($id);
            $definition->addMethodCall('setMiddlewares', [$middlewaresForConnection]);
        }
    }
}
