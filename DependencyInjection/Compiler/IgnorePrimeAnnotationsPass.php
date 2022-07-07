<?php

namespace Bdf\PrimeBundle\DependencyInjection\Compiler;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Ignore all annotations from the bdf serializer and used by prime.
 */
class IgnorePrimeAnnotationsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!class_exists(AnnotationReader::class)) {
            return;
        }

        AnnotationReader::addGlobalIgnoredName('SerializeIgnore');
    }
}
