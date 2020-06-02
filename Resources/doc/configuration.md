Configuration Reference
=======================

```yaml
prime:
    # Enable active record mode to easy access to repositories
    activerecord:                 false
  
    # When true, queries are logged to a "doctrine" monolog channel
    logging:                      "%kernel.debug%"
    profiling:                    "%kernel.debug%"

    # Path to the generated file containing hydrators
    hydrators:                    '%kernel.cache_dir%/prime/hydrators/loader.php'

    # The bdf_serializer service ID
    serializer:                   ~

    auto_commit:                  true
    default_connection:           default

    # A collection of custom types
    types:
        # example
        some_custom_type: Acme\HelloBundle\MyCustomType

    cache:
        query:
            service:              'Bdf\Prime\Cache\ArrayCache'              
            pool:                 ~  
        metadata:
            service:              ~              
            pool:                 ~  
    
    migration:
        connection:               ~
        table:                    'migrations'
        path:                     '%kernel.project_dir%/src/Migration'

    connections:
        # A collection of different named connections (e.g. default, conn2, etc)
        default:
            dbname:               ~
            host:                 ~
            port:                 ~
            user:                 ~
            password:             ~
            charset:              "UTF8"

            # SQLite specific
            path:                 ~

            # SQLite specific
            memory:               ~

            # MySQL specific. The unix socket to use for MySQL
            unix_socket:          ~

            # IBM DB2 specific. True to use as persistent connection for the ibm_db2 driver
            persistent:           ~

            # IBM DB2 specific. The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
            protocol:             ~

            # Oracle specific. True to use SERVICE_NAME as connection parameter instead of SID for Oracle
            service:              ~

            # Oracle specific. Overrules dbname parameter if given and used as SERVICE_NAME or SID connection
            # parameter for Oracle depending on the service parameter.
            servicename:          ~

            # oci8 driver specific. The session mode to use for the oci8 driver.
            sessionMode:          ~

            # SQL Anywhere specific (ServerName). The name of a running database server to connect to for SQL Anywhere.
            server:               ~

            # PostgreSQL specific (default_dbname).
            # Override the default database (postgres) to connect to.
            default_dbname:       ~

            # PostgreSQL specific (LIBPQ-CONNECT-SSLMODE).
            # Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
            sslmode:              ~

            # PostgreSQL specific (LIBPQ-CONNECT-SSLROOTCERT).
            # The name of a file containing SSL certificate authority (CA) certificate(s).
            # If the file exists, the server's certificate will be verified to be signed by one of these authorities.
            sslrootcert:          ~

            # PostgreSQL specific (LIBPQ-CONNECT-SSLCERT).
            # The name of a file containing the client SSL certificate.
            sslcert:              ~

            # PostgreSQL specific (LIBPQ-CONNECT-SSLKEY).
            # The name of a file containing the private key for the client SSL certificate.
            sslkey:               ~

            # PostgreSQL specific (LIBPQ-CONNECT-SSLCRL).
            # The name of a file containing the SSL certificate revocation list (CRL).
            sslcrl:               ~

            # Oracle specific (SERVER=POOLED). True to use a pooled server with the oci8/pdo_oracle driver
            pooled:               ~

            # pdo_sqlsrv driver specific. Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
            MultipleActiveResultSets:  ~

            # Enable savepoints for nested transactions
#            use_savepoints: true

            driver:               ~
            platform_service:     ~

            server_version:       ~
            driver_class:         ~
            # Allows to specify a custom wrapper implementation to use.
            # Must be a subclass of Doctrine\DBAL\Connection
            wrapper_class:        ~

            # An array of options
            options:
                # example
                # key:                  value

            default_table_options:
                # Affects schema-tool. If absent, DBAL chooses defaults
                # based on the platform. Examples here are for MySQL.
                # charset:      utf8
                # collate:      utf8_unicode_ci
                # engine:       InnoDB

            # The read connection of master / slave connection
            read:
                # A collection of named slave connections (e.g. slave1, slave2)
                dbname:               ~
                host:                 ~
                port:                 ~
                user:                 ~
                password:             ~
                charset:              ~
                path:                 ~
                memory:               ~

                # MySQL specific. The unix socket to use for MySQL
                unix_socket:          ~

                # IBM DB2 specific. True to use as persistent connection for the ibm_db2 driver
                persistent:           ~

                # IBM DB2 specific. The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
                protocol:             ~

                # Oracle specific. True to use SERVICE_NAME as connection parameter instead of SID for Oracle
                service:              ~

                # Oracle specific. Overrules dbname parameter if given and used as SERVICE_NAME or SID connection
                # parameter for Oracle depending on the service parameter.
                servicename:          ~

                # oci8 driver specific. The session mode to use for the oci8 driver.
                sessionMode:          ~

                # SQL Anywhere specific (ServerName). The name of a running database server to connect to for SQL Anywhere.
                server:               ~

                # PostgreSQL specific (default_dbname).
                # Override the default database (postgres) to connect to.
                default_dbname:       ~

                # PostgreSQL specific (LIBPQ-CONNECT-SSLMODE).
                # Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
                sslmode:              ~

                # PostgreSQL specific (LIBPQ-CONNECT-SSLROOTCERT).
                # The name of a file containing SSL certificate authority (CA) certificate(s).
                # If the file exists, the server's certificate will be verified to be signed by one of these authorities.
                sslrootcert:          ~

                # PostgreSQL specific (LIBPQ-CONNECT-SSLCERT).
                # The name of a file containing the client SSL certificate.
                sslcert:              ~

                # PostgreSQL specific (LIBPQ-CONNECT-SSLKEY).
                # The name of a file containing the private key for the client SSL certificate.
                sslkey:               ~

                # PostgreSQL specific (LIBPQ-CONNECT-SSLCRL).
                # The name of a file containing the SSL certificate revocation list (CRL).
                sslcrl:               ~

                # Oracle specific (SERVER=POOLED). True to use a pooled server with the oci8/pdo_oracle driver
                pooled:               ~

                # pdo_sqlsrv driver specific. Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
                MultipleActiveResultSets:  ~

            shard_choser:             ~
            distribution_key:         ~
            shards:
                shard1:
                    dbname:               ~
                    host:                 ~
                    port:                 ~
                    user:                 ~
                    password:             ~
                    charset:              ~
                    path:                 ~
                    memory:               ~
    
                    # MySQL specific. The unix socket to use for MySQL
                    unix_socket:          ~
    
                    # IBM DB2 specific. True to use as persistent connection for the ibm_db2 driver
                    persistent:           ~
    
                    # IBM DB2 specific. The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
                    protocol:             ~
    
                    # Oracle specific. True to use SERVICE_NAME as connection parameter instead of SID for Oracle
                    service:              ~
    
                    # Oracle specific. Overrules dbname parameter if given and used as SERVICE_NAME or SID connection
                    # parameter for Oracle depending on the service parameter.
                    servicename:          ~
    
                    # oci8 driver specific. The session mode to use for the oci8 driver.
                    sessionMode:          ~
    
                    # SQL Anywhere specific (ServerName). The name of a running database server to connect to for SQL Anywhere.
                    server:               ~
    
                    # PostgreSQL specific (default_dbname).
                    # Override the default database (postgres) to connect to.
                    default_dbname:       ~
    
                    # PostgreSQL specific (LIBPQ-CONNECT-SSLMODE).
                    # Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
                    sslmode:              ~
    
                    # PostgreSQL specific (LIBPQ-CONNECT-SSLROOTCERT).
                    # The name of a file containing SSL certificate authority (CA) certificate(s).
                    # If the file exists, the server's certificate will be verified to be signed by one of these authorities.
                    sslrootcert:          ~
    
                    # PostgreSQL specific (LIBPQ-CONNECT-SSLCERT).
                    # The name of a file containing the client SSL certificate.
                    sslcert:              ~
    
                    # PostgreSQL specific (LIBPQ-CONNECT-SSLKEY).
                    # The name of a file containing the private key for the client SSL certificate.
                    sslkey:               ~
    
                    # PostgreSQL specific (LIBPQ-CONNECT-SSLCRL).
                    # The name of a file containing the SSL certificate revocation list (CRL).
                    sslcrl:               ~
    
                    # Oracle specific (SERVER=POOLED). True to use a pooled server with the oci8/pdo_oracle driver
                    pooled:               ~
    
                    # pdo_sqlsrv driver specific. Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
                    MultipleActiveResultSets:  ~
```

Configuration
-------------

Most of parameters are from doctrine configuration. See the doctrine bundle for more informations.
