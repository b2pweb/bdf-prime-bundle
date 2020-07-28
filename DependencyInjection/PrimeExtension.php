<?php

namespace Bdf\PrimeBundle\DependencyInjection;

use Bdf\Dsn\Dsn;
use Bdf\Prime\Cache\DoctrineCacheAdapter;
use Bdf\Prime\Configuration as PrimeConfiguration;
use Bdf\Prime\Connection\ConnectionRegistry;
use Bdf\Prime\Connection\Factory\ChainFactory;
use Bdf\Prime\Connection\Factory\ConnectionFactory;
use Bdf\Prime\Connection\Factory\ConnectionFactoryInterface;
use Bdf\Prime\Connection\Factory\MasterSlaveConnectionFactory;
use Bdf\Prime\Connection\Factory\ShardingConnectionFactory;
use Bdf\Prime\Mapper\MapperFactory;
use Bdf\Prime\Types\TypesRegistryInterface;
use Doctrine\Common\Cache\FilesystemCache;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * PrimeExtension
 */
class PrimeExtension extends Extension
{
    use PriorityTaggedServiceTrait;

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('prime.yaml');
        $loader->load('collector.yaml');

        $this->configureConnection($config, $container);
        $this->configureMapperCache($config, $container);
        $this->configureConfiguration($config, $container);
        $this->configureSerializer($config, $container);

        $container->setParameter('prime.default_connection', $config['default_connection']);
        $container->setParameter('prime.migration.connection', $config['migration']['connection']);
        $container->setParameter('prime.migration.table', $config['migration']['table']);
        $container->setParameter('prime.migration.path', $config['migration']['path']);
        $container->setParameter('prime.hydrators', $config['hydrators']);
        $container->setParameter('prime.locatorizable', $config['activerecord']);
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     */
    public function configureConnection(array $config, ContainerBuilder $container)
    {
        foreach ($config['connections'] as $name => &$options) {
            $options = $this->cleanConnectionOptions($options);

            if (!$container->hasDefinition(MasterSlaveConnectionFactory::class) && $this->hasConnectionOption('read', $options)) {
                $container->register(MasterSlaveConnectionFactory::class, MasterSlaveConnectionFactory::class)
                    ->addTag('bdf_prime.connection_factory', ['priority' => -255])
                    ->addArgument(new Reference(ConnectionFactory::class));
            }

            if (!$container->hasDefinition(ShardingConnectionFactory::class) && $this->hasConnectionOption('shards', $options)) {
                $container->register(ShardingConnectionFactory::class, ShardingConnectionFactory::class)
                    ->addTag('bdf_prime.connection_factory', ['priority' => -256])
                    ->addArgument(new Reference(ConnectionFactory::class));
            }
        }

        $factories = $this->findAndSortTaggedServices('bdf_prime.connection_factory', $container);

        $connectionFactory = $container->getDefinition(ChainFactory::class);
        $connectionFactory->replaceArgument(0, $factories);

        $registry = $container->getDefinition(ConnectionRegistry::class);
        $registry->replaceArgument(0, $config['connections']);
    }

    /**
     * @param string $option
     * @param array $options
     * @return bool
     */
    private function hasConnectionOption(string $option, array $options): bool
    {
        if (isset($options[$option])) {
            return true;
        }

        if (!isset($options['url'])) {
            return false;
        }

        try {
            return Dsn::parse($options['url'])->query($option) !== null;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     */
    public function configureSerializer(array $config, ContainerBuilder $container)
    {
        if (!isset($config['serializer'])) {
            return;
        }

        $prime = $container->findDefinition('prime');
        $prime->addMethodCall('setSerializer', [new Reference($config['serializer'])]);
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     */
    public function configureMapperCache(array $config, ContainerBuilder $container)
    {
        $definition = $container->getDefinition(MapperFactory::class);

        if (isset($config['cache']['query'])) {
            $ref = $this->createResultCacheReference('prime.cache.query', $config['cache']['query'], $container);

            if ($ref !== null) {
                $definition->replaceArgument(2, $ref);
            }
        }

        if (isset($config['cache']['metadata'])) {
            $ref = $this->createCacheReference('prime.cache.metadata', $config['cache']['metadata'], $container);

            if ($ref !== null) {
                $definition->replaceArgument(1, $ref);
            }
        }
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     */
    public function configureConfiguration(array $config, ContainerBuilder $container)
    {
        $configuration = $container->register(PrimeConfiguration::class, '%prime.configuration.class%');
        $configuration->addMethodCall('setDbConfig', [$config['connections']]);
        $configuration->addMethodCall('setTypes', [new Reference(TypesRegistryInterface::class)]);

        $typeRegistry = $container->findDefinition(TypesRegistryInterface::class);
        foreach ($config['types'] as $type => $info) {
            $typeRegistry->addMethodCall('register', [$info['class'], is_int($type) ? null : $type]);
        }

        if (isset($config['auto_commit'])) {
            $configuration->addMethodCall('setAutoCommit', [$config['auto_commit']]);
        }

        $logger = null;
        if ($config['logging']) {
            $logger = new Reference('prime.logger');
        }

        if ($config['profiling']) {
            $profilingLogger = new Reference('prime.logger.profiling');

            if ($logger !== null) {
                $chainLogger = $container->findDefinition('prime.logger.chain');
                $chainLogger->addMethodCall('addLogger', [$profilingLogger]);

                $logger = new Reference('prime.logger.chain');
            } else {
                $logger = $profilingLogger;
            }
        }

        if ($logger) {
            $configuration->addMethodCall('setSQLLogger', [$logger]);
        }
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     *
     * @return null|Reference
     */
    private function createCacheReference(string $namespace, array $config, ContainerBuilder $container)
    {
        if (isset($config['service'])) {
            return new Reference($config['service']);
        }

        if (isset($config['pool'])) {
            if (!$container->has($namespace)) {
                $definition = $container->register($namespace, Psr16Cache::class);
                $definition->addArgument(new Reference($config['pool']));
                $definition->setPrivate(true);
            }

            return new Reference($namespace);
        }

        return null;
    }

    /**
     * Create the cache result service
     *
     * @param array $config
     * @param ContainerBuilder $container
     *
     * @return null|Reference
     */
    private function createResultCacheReference(string $namespace, array $config, ContainerBuilder $container)
    {
        if (isset($config['service'])) {
            return new Reference($config['service']);
        }

        if (isset($config['provider'])) {
            if (!$container->has($namespace)) {
                $definition = $container->register($namespace, DoctrineCacheAdapter::class);
                $definition->addArgument($this->createDoctrineCacheProvider($namespace, $config['provider'], $container));
                $definition->setPrivate(true);
            }

            return new Reference($namespace);
        }

        return null;
    }

    /**
     * Create the doctrine cache provider service
     *
     * @param string $namespace
     * @param array $config
     * @param ContainerBuilder $container
     *
     * @return Reference
     */
    private function createDoctrineCacheProvider(string $namespace, array $config, ContainerBuilder $container): Reference
    {
        if (isset($config['service'])) {
            return new Reference($config['service']);
        }

        if (isset($config['filesystem'])) {
            $container
                ->register($namespace.'.doctrine-provider', FilesystemCache::class)
                ->addArgument($config['filesystem'])
            ;

            return new Reference($namespace.'.doctrine-provider');
        }

        throw new InvalidArgumentException('Invalid result cache configuration');
    }

    /**
     * Rearrange the key name of the configuration
     *
     * @param array $options
     *
     * @return array
     */
    private function cleanConnectionOptions(array $options): array
    {
        if (isset($options['platform_service'])) {
            $options['platform'] = new Reference($options['platform_service']);
            unset($options['platform_service']);
        }

//        unset($options['mapping_types']);

        if (isset($options['shard_choser'])) {
            $options['shard_choser'] = new Reference($options['shard_choser']);
        }

        $parameters = [
            'options' => 'driverOptions',
            'driver_class' => 'driverClass',
            'wrapper_class' => 'wrapperClass',
            'shard_choser' => 'shardChoser',
            'distribution_key' => 'distributionKey',
            'server_version' => 'serverVersion',
            'default_table_options' => 'defaultTableOptions',
        ];

        foreach ($parameters as $old => $new) {
            if (isset($options[$old])) {
                $options[$new] = $options[$old];
                unset($options[$old]);
            }
        }

        if (!empty($options['read']) && !empty($options['shards'])) {
            throw new InvalidArgumentException('Sharding and master-slave connection cannot be used together');
        }


        $parameters = ['read', 'shards', 'driverOptions', 'defaultTableOptions'];

        foreach ($parameters as $key) {
            if (empty($options[$key])) {
                unset($options[$key]);
            }
        }

        return $options;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }
}
