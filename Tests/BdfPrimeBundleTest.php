<?php

namespace Bdf\PrimeBundle\Tests\DependencyInjection;

use Bdf\PrimeBundle\PrimeBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * BdfSerializerBundleTest
 */
class BdfPrimeBundleTest extends TestCase
{
    public function test_default_config()
    {
        $builder = $this->createMock(ContainerBuilder::class);

        $bundle = new PrimeBundle();

        $this->assertNull($bundle->build($builder));
    }
}
