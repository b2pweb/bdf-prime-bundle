<?php

namespace Bdf\PrimeBundle\Tests\Fixtures\Php81;

use Bdf\Prime\Repository\EntityRepository;
use Bdf\PrimeBundle\Attribute\Repository;
use Bdf\PrimeBundle\Tests\Fixtures\TestEntity;

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
