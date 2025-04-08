<?php

namespace Bdf\PrimeBundle\Tests;

use Bdf\PrimeBundle\Tests\Fixtures\TimestampEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

class WithMockClockTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(MockClock::class)) {
            $this->markTestSkipped('Symfony Clock not installed');
        }

        Clock::set(new MockClock('2025-03-05T15:26:32', new \DateTimeZone('UTC')));
    }

    public function testTimestampable()
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        TimestampEntity::repository()->schema()->migrate();

        $entity = new TimestampEntity();
        $entity->insert();

        $this->assertSame(1, $entity->id);
        $this->assertEquals(new \DateTimeImmutable('2025-03-05T15:26:32', new \DateTimeZone('UTC')), $entity->createdAt);
    }
}
