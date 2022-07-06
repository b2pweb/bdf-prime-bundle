<?php

namespace Bdf\PrimeBundle\Migration;

use Bdf\Prime\Migration\Version\DbVersionRepository;
use Bdf\Prime\ServiceLocator;

/**
 * Create an instance of DbVersionRepository.
 */
class DbVersionRepositoryFactory
{
    /**
     * @var ServiceLocator
     */
    private $prime;

    /**
     * PrimeFactory constructor.
     */
    public function __construct(ServiceLocator $prime)
    {
        $this->prime = $prime;
    }

    /**
     * Create the repository instance.
     */
    public function create(string $tableName, string $connection = null): DbVersionRepository
    {
        return new DbVersionRepository($this->prime->connection($connection), $tableName);
    }
}
