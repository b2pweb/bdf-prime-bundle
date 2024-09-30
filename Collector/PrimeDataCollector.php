<?php

namespace Bdf\PrimeBundle\Collector;

use Bdf\Prime\Connection\Middleware\LoggerMiddleware;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adapt the doctrine data collector to prime.
 */
class PrimeDataCollector extends DoctrineDataCollector
{
    public function __construct(ManagerRegistry $registry, bool $shouldValidateSchema = true, ?DebugDataHolder $debugDataHolder = null)
    {
        // Only set a default DebugDataHolder if middleware system is available
        if (
            null === $debugDataHolder
            && \class_exists(DebugDataHolder::class)
            && \interface_exists(MiddlewareInterface::class)
            && \class_exists(LoggerMiddleware::class)
        ) {
            $debugDataHolder = new DebugDataHolder();
        }

        parent::__construct($registry, $shouldValidateSchema, $debugDataHolder);
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
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

    public function addLogger($name, $logger)
    {
        // In symfony 7 the addLogger method is removed
        // So declare it only for BC
        // New version use DebugDataHolder from the constructor
        if (\method_exists(parent::class, 'addLogger')) {
            parent::addLogger($name, $logger);
        }
    }
}
