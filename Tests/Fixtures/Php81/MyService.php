<?php

namespace Bdf\PrimeBundle\Tests\Fixtures\Php81;

use Bdf\Prime\Repository\EntityRepository;
use Bdf\PrimeBundle\Attribute\Repository;
use Bdf\PrimeBundle\Tests\Fixtures\TestEntity;
use Symfony\Component\DependencyInjection\Attribute\AutowireInline;

if (PHP_VERSION_ID >= 80100 && class_exists(AutowireInline::class)) {
    class MyService
    {
        /**
         * @var EntityRepository<TestEntity>
         */
        #[Repository(TestEntity::class)]
        public $repository;

        public function __construct(
            #[Repository(TestEntity::class)]
            EntityRepository $repository
        ) {
            $this->repository = $repository;
        }
    }
} else {
    // Add class to avoid error in PHP 7.X
    class MyService {}
}
