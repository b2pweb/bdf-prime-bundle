<?php

use Bdf\Prime\Entity\InitializableInterface;
use Bdf\Prime\Entity\Model;
use Bdf\Prime\Mapper\Mapper;
use Bdf\Prime\Query\Custom\KeyValue\KeyValueQuery;
use Bdf\Prime\Repository\EntityRepository;
use Bdf\Prime\TestEmbeddedEntity;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\RouteCollectionBuilder;

class TestKernel extends \Symfony\Component\HttpKernel\Kernel
{
    use \Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Symfony\Bundle\WebProfilerBundle\WebProfilerBundle(),
            new \Symfony\Bundle\TwigBundle\TwigBundle(),
            new \Bdf\PrimeBundle\PrimeBundle(),
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
        ];
    }

    protected function configureRoutes($routes)
    {
        //$routes->add('index', '/')->controller([$this, 'indexAction']);
        if ($routes instanceof RouteCollectionBuilder) {
            $routes->add('/', 'kernel::indexAction');
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml', '/_wdt');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml', '/_profiler');
        } else {
            $routes->add('index', '/')->controller([$this, 'indexAction']);
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml');
        }
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/conf.yaml');
        //$c->import(__DIR__.'/conf.yaml');
    }

    public function indexAction()
    {
        TestEntity::repository()->schema()->migrate();
        TestEntity::findById(5);

        return new \Symfony\Component\HttpFoundation\Response(<<<HTML
<!DOCTYPE html>
<html>
    <body>Hello World !</body>
</html>
HTML
);
    }
}


class TestEntity extends Model
{
    public $id;
    public $name;
    public $dateInsert;
    public $parentId;
    public $parent;

    public function __construct(array $attributes = [])
    {
        $this->import($attributes);
    }
}

class TestEntityMapper extends Mapper
{
    /**
     * {@inheritdoc}
     */
    public function schema()
    {
        return [
            'connection' => 'test',
            'database' => 'test',
            'table' => 'test_',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildFields($builder)
    {
        $builder
            ->integer('id')->autoincrement()
            ->string('name')
            ->datetime('dateInsert')->alias('date_insert')->nillable()
        ;
    }
}
