<?php

namespace Bdf\PrimeBundle\Tests\Fixtures\Php81;

use Bdf\Prime\Mapper\Builder\FieldBuilder;
use Bdf\Prime\Mapper\Mapper;

class WithEnumEntityMapper extends Mapper
{
    public function schema(): array
    {
        return [
            'table' => 'with_enum',
            'connection' => 'test',
        ];
    }

    public function buildFields($builder): void
    {
        $builder
            ->integer('id')->autoincrement()
            ->intEnum('intEnum', MyIntEnum::class)
            ->stringEnum('stringEnum', MyStringEnum::class)
            ->unitEnum('unitEnum', MyUnitEnum::class)
        ;
    }
}
