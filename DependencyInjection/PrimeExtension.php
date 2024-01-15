<?php

namespace Bdf\PrimeBundle\DependencyInjection;

use Bdf\Prime\Cache\DoctrineCacheAdapter;
use Bdf\Prime\Configuration as PrimeConfiguration;
use Bdf\Prime\Connection\ConnectionRegistry;
use Bdf\Prime\Connection\Factory\ConnectionFactory;
use Bdf\Prime\Connection\Factory\MasterSlaveConnectionFactory;
use Bdf\Prime\Connection\Factory\ShardingConnectionFactory;
use Bdf\Prime\Connection\Middleware\LoggerMiddleware;
use Bdf\Prime\Console\CriteriaCommand;
use Bdf\Prime\Console\UpgraderCommand;
use Bdf\Prime\Mapper\ContainerMapperFactory;
use Bdf\Prime\Mapper\Mapper;
use Bdf\Prime\Mapper\MapperFactory;
use Bdf\Prime\Mapper\MapperFactoryInterface;
use Bdf\Prime\Migration\MigrationManager;
use Bdf\Prime\MongoDB\Collection\MongoCollectionLocator;
use Bdf\Prime\MongoDB\Document\DocumentMapperInterface;
use Bdf\Prime\MongoDB\Driver\MongoConnectionFactory;
use Bdf\Prime\MongoDB\Schema\CollectionStructureUpgraderResolver;
use Bdf\Prime\Schema\RepositoryUpgraderResolver;
use Bdf\Prime\Schema\StructureUpgraderResolverAggregate;
use Bdf\Prime\Schema\StructureUpgraderResolverInterface;
use Bdf\Prime\ServiceLocator;
use Bdf\Prime\Shell\PrimeShellCommand;
use Bdf\Prime\Types\TypesRegistryInterface;
use Bdf\PrimeBundle\DependencyInjection\Compiler\PrimeMiddlewarePass;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * PrimeExtension.
 */
class PrimeExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('prime.yaml');
        $loader->load('collector.yaml');

        if (class_exists(LoggerMiddleware::class)) {
            $loader->load('middlewares.yaml');
        }

        $this->configureConnection($config, $container);
        $this->configureMapperCache($config, $container);
        $this->configureSerializer($config, $container);

        if (interface_exists(StructureUpgraderResolverInterface::class)) {
            $this->configureUpgrader($config, $container);
        }

        if (class_exists(MongoCollectionLocator::class)) {
            $this->configureMongo($loader, $container, $config);
        }

        if (class_exists(PrimeShellCommand::class)) {
            $loader->load('prime_shell.yaml');
        }

        $container->setParameter('prime.default_connection', $config['default_connection']);
        $container->setParameter('prime.migration.connection', $config['migration']['connection']);
        $container->setParameter('prime.migration.table', $config['migration']['table']);
        $container->setParameter('prime.migration.path', $config['migration']['path']);
        $container->setParameter('prime.hydrators', $config['hydrators']);
        $container->setParameter('prime.locatorizable', $config['activerecord']);

        $this->configurePrime21($container);
    }

    public function configureConnection(array $config, ContainerBuilder $container)
    {
        foreach ($config['connections'] as $name => &$options) {
            $options = $this->cleanConnectionOptions($options);

            // Overwrite global config by the connection config parameters and create the configuration reference.
            $this->createConfiguration($name, $this->mergeConfiguration($config, $options), $container);

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

        $registry = $container->getDefinition(ConnectionRegistry::class);
        $registry->replaceArgument(0, $config['connections']);
    }

    private function hasConnectionOption(string $option, array $options): bool
    {
        if (isset($options[$option])) {
            return true;
        }

        if (!isset($options['url'])) {
            return false;
        }

        // The option could be in the url. Adding the factory by default.
        return true;
    }

    public function configureSerializer(array $config, ContainerBuilder $container)
    {
        if (!isset($config['serializer'])) {
            return;
        }

        $prime = $container->findDefinition('prime');
        $prime->addMethodCall('setSerializer', [new Reference($config['serializer'])]);
    }

    public function configureMapperCache(array $config, ContainerBuilder $container)
    {
        $definition = $container->getDefinition(MapperFactory::class);

        if (isset($config['cache']['query'])) {
            $ref = $this->createResultCacheReference('prime.cache.query', $config['cache']['query'], $container);

            if (null !== $ref) {
                $definition->replaceArgument(2, $ref);
            }
        }

        if (isset($config['cache']['metadata'])) {
            $ref = $this->createCacheReference('prime.cache.metadata', $config['cache']['metadata'], $container);

            if (null !== $ref) {
                $definition->replaceArgument(1, $ref);
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function configureMongo(FileLoader $loader, ContainerBuilder $container, array $config): void
    {
        $loader->load('prime_mongo.yaml');

        $container->registerForAutoconfiguration(DocumentMapperInterface::class)
            ->setPublic(true)
            ->setShared(false)
            ->setAutowired(true);

        if (isset($config['cache']['metadata'])) {
            $definition = $container->findDefinition('prime_mongodb_serializer');
            $ref = $this->createCacheReference('prime.cache.metadata', $config['cache']['metadata'], $container);

            if (null !== $ref) {
                $definition->replaceArgument(0, $ref);
            }
        }

        $container->findDefinition(StructureUpgraderResolverAggregate::class)
            ->addMethodCall('register', [new Reference(CollectionStructureUpgraderResolver::class)])
        ;

        if (\class_exists(MongoConnectionFactory::class)) {
            $container->register(MongoConnectionFactory::class)
                ->addTag('bdf_prime.connection_factory', ['priority' => 1])
            ;
        }
    }

    private function configureUpgrader(array $config, ContainerBuilder $container)
    {
        $container->register(RepositoryUpgraderResolver::class)
            ->addArgument(new Reference(ServiceLocator::class))
        ;

        $container->register(StructureUpgraderResolverAggregate::class)
            ->setPublic(true)
            ->addMethodCall('register', [new Reference(RepositoryUpgraderResolver::class)])
        ;

        $container->setAlias(StructureUpgraderResolverInterface::class, StructureUpgraderResolverAggregate::class)
            ->setPublic(true)
        ;

        $container->findDefinition('prime.upgrade_command')
            ->replaceArgument(0, new Reference(StructureUpgraderResolverInterface::class))
        ;
    }

    /**
     * Configure new classes and features of Prime 2.1.
     */
    private function configurePrime21(ContainerBuilder $container): void
    {
        if (class_exists(CriteriaCommand::class)) {
            $container->register(CriteriaCommand::class, CriteriaCommand::class)
                ->addArgument(new Reference(ServiceLocator::class))
                ->addTag('console.command')
            ;
        }

        // Configure UpgraderCommand with migration manager to allows generation of migration files
        $upgraderCommandConstructor = new \ReflectionMethod(UpgraderCommand::class, '__construct');
        if ($upgraderCommandConstructor->getNumberOfParameters() >= 2 && MigrationManager::class === $upgraderCommandConstructor->getParameters()[1]->getType()->getName()) {
            $container->findDefinition('prime.upgrade_command')
                ->addArgument(new Reference(MigrationManager::class))
            ;
        }

        // Use the container mapper factory instead of the legacy one
        if (class_exists(ContainerMapperFactory::class)) {
            $baseArguments = $container->findDefinition(MapperFactory::class)->getArguments();

            $container->register(ContainerMapperFactory::class)
                ->addArgument(new Reference('service_container'))
                ->addArgument($baseArguments[0])
                ->addArgument($baseArguments[1])
                ->addArgument($baseArguments[2])
            ;

            $container->setAlias(MapperFactoryInterface::class, ContainerMapperFactory::class);
            $container->findDefinition('prime')
                ->replaceArgument(1, new Reference(MapperFactoryInterface::class))
            ;

            // Mappers must be public to be accessible by the factory
            $container->registerForAutoconfiguration(Mapper::class)
                ->setShared(false)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('prime.mapper')
            ;
        }
    }

    public function mergeConfiguration(array $globalConfig, array $config): array
    {
        return [
            'types' => array_merge($globalConfig['types'], $config['types']),
            'auto_commit' => $config['auto_commit'] ?? $globalConfig['auto_commit'],
            'logging' => $config['logging'] ?? $globalConfig['logging'],
            'profiling' => $config['profiling'] ?? $globalConfig['profiling'],
            'platformTypes' => $config['platformTypes'] ?? [],
        ];
    }

    /**
     * Create and declare the configuration definition of the connection.
     */
    public function createConfiguration(string $name, array $config, ContainerBuilder $container): void
    {
        $namespace = "prime.{$name}_connection";

        $configuration = $container->setDefinition("$namespace.configuration", new ChildDefinition(PrimeConfiguration::class));
        $configuration->setPublic(true);
        $configuration->addMethodCall('setTypes', [new Reference("$namespace.types")]);
        $configuration->addTag('bdf_prime.configuration', ['connection' => $name]);

        $typeRegistry = $container->setDefinition("$namespace.types", new ChildDefinition(TypesRegistryInterface::class));
        foreach ($config['types'] as $type => $info) {
            $typeRegistry->addMethodCall('register', [$info['class'], is_int($type) ? null : $type]);
        }

        if (isset($config['auto_commit'])) {
            $configuration->addMethodCall('setAutoCommit', [$config['auto_commit']]);
        }

        $logger = null;
        if ($config['logging']) {
            $supportsMiddleware = $container->hasDefinition('prime.middleware.logger');

            // Mongo driver for Prime does not support middleware prior to introduction of MongoConnectionFactory
            // So we must use the legacy SQLLogger
            if (\class_exists(MongoCollectionLocator::class) && !$container->hasDefinition(MongoConnectionFactory::class)) {
                $supportsMiddleware = false;
            }

            // Prime 2.2: Use the logger middleware instead of legacy SQLLogger
            if ($supportsMiddleware) {
                $container->findDefinition('prime.middleware.logger')
                    ->addTag(PrimeMiddlewarePass::TAG, ['connection' => $name])
                ;
            } else {
                $logger = new Reference('prime.logger');
            }
        }

        if ($config['profiling']) {
            $profilingLogger = new Reference('prime.logger.profiling');

            if (null !== $logger) {
                $chainLogger = $container->findDefinition('prime.logger.chain');
                $chainLogger->replaceArgument(0, [$logger, $profilingLogger]);

                $logger = new Reference('prime.logger.chain');
            } else {
                $logger = $profilingLogger;
            }
        }

        if ($logger) {
            $configuration->addMethodCall('setSQLLogger', [$logger]);
        }

        foreach ($config['platformTypes'] as $type => $info) {
            $configuration->addMethodCall('addPlatformType', [$info['class'], is_int($type) ? null : $type]);
        }
    }

    /**
     * @return Reference|null
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
            }

            return new Reference($namespace);
        }

        return null;
    }

    /**
     * Create the cache result service.
     *
     * @return Reference|null
     */
    private function createResultCacheReference(string $namespace, array $config, ContainerBuilder $container)
    {
        if (isset($config['service'])) {
            return new Reference($config['service']);
        }

        if (isset($config['pool'])) {
            if (!$container->has($namespace)) {
                $definition = $container->register($namespace.'.doctrine-provider', DoctrineProvider::class);
                $definition->setFactory([DoctrineProvider::class, 'wrap']);
                $definition->addArgument(new Reference($config['pool']));

                $definition = $container->register($namespace, DoctrineCacheAdapter::class);
                $definition->addArgument(new Reference($namespace.'.doctrine-provider'));
            }

            return new Reference($namespace);
        }

        return null;
    }

    /**
     * Rearrange the key name of the configuration.
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

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }
}
