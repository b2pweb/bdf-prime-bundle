<?php

namespace Bdf\PrimeBundle\DependencyInjection;

use Bdf\Prime\Configuration as PrimeConfiguration;
use Bdf\Prime\Types\TypesRegistryInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * PrimeExtension
 */
class PrimeExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('prime.yaml');

        foreach ($config['connections'] as $name => $options) {
            $config['connections'][$name] = $this->cleanConnectionOptions($options);
        }

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

        if (isset($config['cache']['query'])) {
            $ref = $this->createCacheReference('prime.cache.query', $config['cache']['query'], $container);

            if ($ref !== null) {
                $configuration->addMethodCall('setResultCache', [$ref]);
            }
        }

        if (isset($config['cache']['metadata'])) {
            $ref = $this->createCacheReference('prime.cache.metadata', $config['cache']['metadata'], $container);

            if ($ref !== null) {
                $configuration->addMethodCall('setMetadataCache', [$ref]);
            }
        }

        $logger = null;
        if ($config['logging']) {
            $logger = new Reference('prime.logger');
        }

        if ($config['profiling']) {
            // TODO Manage DataCollector !
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
            $definition = $container->register($namespace, Psr16Cache::class);
            $definition->addArgument(new Reference($config['pool']));
            $definition->setPrivate(true);

            return new Reference($namespace);
        }

        return null;
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