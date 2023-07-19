<?php

namespace Bdf\PrimeBundle\Tests\Fixtures;

use Bdf\Prime\Mapper\Mapper;

class TestEntityMapper extends Mapper
{
    /**
     * {@inheritdoc}
     */
    public function schema(): array
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
    public function buildFields($builder): void
    {
        $builder
            ->integer('id')->autoincrement()
            ->string('name')
            ->datetime('dateInsert')->alias('date_insert')->nillable();
    }
}
