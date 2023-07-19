<?php

namespace Bdf\PrimeBundle\Tests\Fixtures;

use Bdf\Prime\Mapper\Mapper;
use Bdf\Prime\ServiceLocator;

class WithInjectionMapper extends Mapper
{
    /**
     * @var A
     */
    public $a;

    public function __construct(ServiceLocator $serviceLocator, A $a)
    {
        parent::__construct($serviceLocator);

        $this->a = $a;
    }

    public function schema(): array
    {
        return [
            'connection' => 'test',
            'table' => 'with_injection',
        ];
    }

    public function buildFields($builder): void
    {
        $builder
            ->integer('id')->autoincrement()
        ;
    }
}
