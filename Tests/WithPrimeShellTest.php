<?php

namespace Bdf\PrimeBundle\Tests;

require_once __DIR__.'/TestKernel.php';

use Bdf\Prime\Shell\PrimeShellCommand;
use Bdf\PrimeBundle\PrimeBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class WithPrimeShellTest extends TestCase
{
    private $kernel;

    protected function setUp(): void
    {
        if (!class_exists(PrimeShellCommand::class)) {
            $this->markTestSkipped('PrimeShell not installed');
        }

        $this->kernel = new class('test', true) extends Kernel {
            use \Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

            public function registerBundles(): iterable
            {
                return [
                    new FrameworkBundle(),
                    new PrimeBundle(),
                ];
            }

            protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
            {
                $loader->import(__DIR__.'/Fixtures/config.yaml');
            }

            protected function configureRoutes($routes)
            {
            }
        };
        $this->kernel->boot();
    }

    public function testCommands()
    {
        $app = new Application($this->kernel);

        $command = $app->get('prime:shell');

        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }

        $this->assertInstanceOf(PrimeShellCommand::class, $command);
    }
}
