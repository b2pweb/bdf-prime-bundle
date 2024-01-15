<?php

namespace Bdf\PrimeBundle\Tests\Fixtures;

use Bdf\Prime\Mapper\Mapper;

class TestEntityMapper extends Mapper
{
    public function schema(): array
    {
        return [
            'connection' => 'test',
            'database' => 'test',
            'table' => 'test_',
        ];
    }

    public function buildFields($builder): void
    {
        $builder
            ->integer('id')->autoincrement()
            ->string('name')
            ->datetime('dateInsert')->alias('date_insert')->nillable();
    }
}
