<?php

namespace Bdf\PrimeBundle\DependencyInjection;

use Bdf\Prime\Configuration as PrimeConfiguration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @var bool
     */
    private $debug;

    /**
     * @param bool $debug Whether to use the debug mode
     */
    public function __construct($debug = false)
    {
        $this->debug = (bool) $debug;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('prime');
        $node = $treeBuilder->getRootNode();
        $node
            ->children()
                ->booleanNode('activerecord')->defaultFalse()->end()
                ->scalarNode('hydrators')->defaultValue('%kernel.cache_dir%/prime/hydrators/loader.php')->end()
                ->scalarNode('serializer')->info('The bdf serializer service id.')->end()
                ->scalarNode('default_connection')->defaultNull()->end()
                ->append($this->getConnectionsNode())
                ->append($this->getMigrationNode())
                ->append($this->getCacheNode())
            ->end()
        ;

        $this->configureConfigurationNode($node, true);

        return $treeBuilder;
    }

    /**
     * Adds the configuration node of the connection
     * Could be the global config or a connection config.
     */
    private function configureConfigurationNode(ArrayNodeDefinition $node, bool $addDefault): void
    {
        $parametersNode = $node->children();

        $loggingNode = $parametersNode->booleanNode('logging');
        $profilingNode = $parametersNode->booleanNode('profiling');
        $autoCommitNode = $parametersNode->booleanNode('auto_commit');

        if (true === $addDefault) {
            $loggingNode->defaultValue($this->debug);
            $profilingNode->defaultValue($this->debug);
            $autoCommitNode->defaultNull();
        }

        $parametersNode->append($this->getTypesNode());
    }

    /**
     * @return NodeDefinition
     */
    private function getConnectionsNode()
    {
        $root = (new TreeBuilder('connections'))->getRootNode();

        $connectionNode = $root
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->isRequired()
            ->beforeNormalization()
                ->ifString()
                ->then(static function ($v) {
                    return ['url' => $v];
                })
            ->end()
        ;

        $this->configureDbalDriverNode($connectionNode);
        $this->configureConfigurationNode($connectionNode, false);

        $connectionNode
            ->children()
                ->scalarNode('driver')->end()
                ->scalarNode('platform_service')->end()
                ->scalarNode('server_version')->end()
                ->scalarNode('driver_class')->end()
                ->scalarNode('wrapper_class')->end()
                ->arrayNode('options')
                    ->useAttributeAsKey('key')
                    ->variablePrototype()->end()
                ->end()
                ->arrayNode('default_table_options')
                    ->info("This option is used by the schema-tool and affects generated SQL. Possible keys include 'charset','collate', and 'engine'.")
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                ->end()
                ->append($this->getPlatformTypesNode())
            ->end();

        $slaveNode = $connectionNode->children()->arrayNode('read');

        $this->configureDbalDriverNode($slaveNode);

        $shardNode = $connectionNode
            ->children()
                ->scalarNode('shard_choser')->end()
                ->scalarNode('distribution_key')->end()
                ->arrayNode('shards')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype();

        $this->configureDbalDriverNode($shardNode);

        return $root;
    }

    /**
     * Adds config keys related to params processed by the DBAL drivers.
     *
     * These keys are available for slave configurations too.
     */
    private function configureDbalDriverNode(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->scalarNode('url')->info('A URL with connection information; any parameter value parsed from this string will override explicitly set parameters')->end()
                ->scalarNode('dbname')->end()
                ->scalarNode('host')->end()
                ->scalarNode('port')->end()
                ->scalarNode('user')->end()
                ->scalarNode('password')->end()
                ->scalarNode('application_name')->end()
                ->scalarNode('charset')->end()
                ->scalarNode('path')->end()
                ->booleanNode('memory')->end()
                ->scalarNode('unix_socket')->info('The unix socket to use for MySQL')->end()
                ->booleanNode('persistent')->info('True to use as persistent connection for the ibm_db2 driver')->end()
                ->scalarNode('protocol')->info('The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)')->end()
                ->booleanNode('service')
                    ->info('True to use SERVICE_NAME as connection parameter instead of SID for Oracle')
                ->end()
                ->scalarNode('servicename')
                    ->info(
                        'Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter '.
                        'for Oracle depending on the service parameter.'
                    )
                ->end()
                ->scalarNode('sessionMode')
                    ->info('The session mode to use for the oci8 driver')
                ->end()
                ->scalarNode('server')
                    ->info('The name of a running database server to connect to for SQL Anywhere.')
                ->end()
                ->scalarNode('default_dbname')
                    ->info(
                        'Override the default database (postgres) to connect to for PostgreSQL connexion.'
                    )
                ->end()
                ->scalarNode('sslmode')
                    ->info(
                        'Determines whether or with what priority a SSL TCP/IP connection will be negotiated with '.
                        'the server for PostgreSQL.'
                    )
                ->end()
                ->scalarNode('sslrootcert')
                    ->info(
                        'The name of a file containing SSL certificate authority (CA) certificate(s). '.
                        'If the file exists, the server\'s certificate will be verified to be signed by one of these authorities.'
                    )
                ->end()
                ->scalarNode('sslcert')
                    ->info(
                        'The path to the SSL client certificate file for PostgreSQL.'
                    )
                ->end()
                ->scalarNode('sslkey')
                    ->info(
                        'The path to the SSL client key file for PostgreSQL.'
                    )
                ->end()
                ->scalarNode('sslcrl')
                    ->info(
                        'The file name of the SSL certificate revocation list for PostgreSQL.'
                    )
                ->end()
                ->booleanNode('pooled')->info('True to use a pooled server with the oci8/pdo_oracle driver')->end()
                ->booleanNode('MultipleActiveResultSets')->info('Configuring MultipleActiveResultSets for the pdo_sqlsrv driver')->end()
//                ->booleanNode('use_savepoints')->info('Use savepoints for nested transactions')->end()
                ->scalarNode('instancename')
                    ->info(
                        'Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection.'.
                        ' It is generally used to connect to an Oracle RAC server to select the name'.
                        ' of a particular instance.'
                    )
                ->end()
                ->scalarNode('connectstring')
                    ->info(
                        'Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.'.
                        'When using this option, you will still need to provide the user and password parameters, but the other '.
                        'parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods'.
                        ' from Doctrine\DBAL\Connection will no longer function as expected.'
                    )
                ->end()
            ->end()
            ->beforeNormalization()
                ->ifTrue(static function ($v) {
                    return !isset($v['sessionMode']) && isset($v['session_mode']);
                })
                ->then(static function ($v) {
                    $v['sessionMode'] = $v['session_mode'];
                    unset($v['session_mode']);

                    return $v;
                })
            ->end()
            ->beforeNormalization()
                ->ifTrue(static function ($v) {
                    return !isset($v['MultipleActiveResultSets']) && isset($v['multiple_active_result_sets']);
                })
                ->then(static function ($v) {
                    $v['MultipleActiveResultSets'] = $v['multiple_active_result_sets'];
                    unset($v['multiple_active_result_sets']);

                    return $v;
                })
            ->end();
    }

    /**
     * @return NodeDefinition
     */
    private function getTypesNode()
    {
        $root = (new TreeBuilder('types'))->getRootNode();
        $root
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->beforeNormalization()
            ->ifString()
            ->then(static function ($v) {
                return ['class' => $v];
            })
            ->end()
            ->children()
                ->scalarNode('class')->isRequired()->end()
            ->end()
        ;

        return $root;
    }

    /**
     * @return NodeDefinition
     */
    private function getPlatformTypesNode()
    {
        $root = (new TreeBuilder('platformTypes'))->getRootNode();
        $root
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->beforeNormalization()
            ->ifString()
            ->then(static function ($v) {
                return ['class' => $v];
            })
            ->end()
            ->children()
                ->scalarNode('class')->isRequired()->end()
            ->end()
            ->beforeNormalization()
            ->ifTrue(function ($value) { return !empty($value) && !method_exists(PrimeConfiguration::class, 'addPlatformType'); })
            ->thenInvalid('Define platform types is only supported by bdf-prime version >= 2.1')
        ;

        return $root;
    }

    /**
     * @return NodeDefinition
     */
    private function getMigrationNode()
    {
        $root = (new TreeBuilder('migration'))->getRootNode();
        $root->children()
            ->scalarNode('path')
                ->info('The directory path where the migration file will be stored.')
                ->isRequired()
            ->end()
            ->scalarNode('connection')
                ->info('The prime connection name to use to access to the version of migrations.')
                ->defaultNull()
            ->end()
            ->scalarNode('table')
                ->info('The table name to store the version of migrations. The default name is "migrations".')
                ->defaultValue('migrations')
            ->end()
        ;

        return $root;
    }

    /**
     * @return NodeDefinition
     */
    private function getCacheNode()
    {
        $root = (new TreeBuilder('cache'))->getRootNode();
        $root->children()
            ->arrayNode('query')
                ->info('The result cache service. Should implement Bdf\Prime\Cache\CacheInterface.')
                ->children()
                    ->scalarNode('pool')->end()
                    ->scalarNode('service')->end()
                ->end()
            ->end()
            ->arrayNode('metadata')
                ->info('The metadata cache service. Should implement Psr\SimpleCache\CacheInterface.')
                ->children()
                    ->scalarNode('pool')->end()
                    ->scalarNode('service')->end()
                ->end()
            ->end()
        ;

        return $root;
    }
}
