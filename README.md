Installation
============

1 Download the Bundle
---------------------

Download the latest stable version of this bundle with composer:

```bash
    $ composer require b2pweb/bdf-prime-bundle
```

2 Enable the Bundle
-------------------

Adding the following line in the ``config/bundles.php`` file of your project::

```php
<?php
// config/bundles.php

return [
    // ...
    Bdf\PrimeBundle\PrimeBundle::class => ['all' => true],
    Bdf\PrimeBundle\TestingPrimeBundle::class => ['test' => true],
    // ...
];
```

3 Set environment
-----------------

Add your dsn on the`.env` file

```
DATABASE_URL=mysql://root@127.0.0.1/dbname?serverVersion=5.7
```

Add your dsn on the`.env.test` file

```
DATABASE_URL=sqlite::memory:
```

4 Add configuration
-------------------

Add a default config file to `./config/packages/prime.yaml`

```yaml
prime:
    activerecord: true
    hydrators: '%kernel.cache_dir%/prime/hydrators/loader.php'
    default_connection: 'default'
    connections:
        default: '%env(resolve:DATABASE_URL)%'
    
    migration:
        connection: 'default'
        path: '%kernel.project_dir%/src/Migration'
```

Enable caching for production

```yaml
prime:
  cache:
    query:
      service: 'Bdf\Prime\Cache\ArrayCache'
    metadata:
      pool: 'cache.app'
```

Add a test file to `./config/packages/test/prime.yaml`

```yaml
prime:
  logging: false
  cache:
    query:
      pool: null
      service: null

    metadata:
      pool: null
      service: null
```
