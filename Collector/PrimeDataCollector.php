<?php

namespace Bdf\PrimeBundle\Collector;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adapt the doctrine data collector to prime.
 */
class PrimeDataCollector extends DoctrineDataCollector
{
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        \Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector::collect($request, $response, $exception);

        $this->data['entities'] = [];
        $this->data['errors'] = [];
        $this->data['caches'] = [
            'enabled' => false,
            'log_enabled' => false,
            'counts' => [
                'puts' => 0,
                'hits' => 0,
                'misses' => 0,
            ],
            'regions' => [
                'puts' => [],
                'hits' => [],
                'misses' => [],
            ],
        ];
    }
}
