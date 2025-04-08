<?php

namespace Bdf\PrimeBundle\Tests\Fixtures;

use Bdf\Prime\Behaviors\Timestampable;
use Bdf\Prime\Mapper\Mapper;

class TimestampEntityMapper extends Mapper
{
    public function schema(): array
    {
        return [
            'table' => 'timestamp_entity',
            'connection' => 'test',
        ];
    }

    public function buildFields($builder): void
    {
        $builder
            ->integer('id')->autoincrement()
            ->datetime('createdAt')->alias('created_at')->phpClass(\DateTimeImmutable::class)
        ;
    }

    public function getDefinedBehaviors(): array
    {
        return [
            new Timestampable(true, false),
        ];
    }
}
