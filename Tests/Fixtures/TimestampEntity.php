<?php

namespace Bdf\PrimeBundle\Tests\Fixtures;

use Bdf\Prime\Entity\Model;

class TimestampEntity extends Model
{
    /**
     * @var int|null
     */
    public $id;

    /**
     * @var \DateTimeImmutable|null
     */
    public $createdAt;

    public function __construct(array $data = [])
    {
        $this->import($data);
    }
}
