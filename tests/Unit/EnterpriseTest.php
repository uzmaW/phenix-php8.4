<?php

declare(strict_types=1);

namespace Tests\Unit;

use Phoenix\RateLimit\RateLimiter;
use Phoenix\Core\Idempotency;
use Phoenix\Lock\DistributedLock;
use PHPUnit\Framework\TestCase;

final class EnterpriseTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/phoenix_test_' . uniqid();
        RateLimiter::flushMemory();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->testDir);
        }
    }

    public function test_rate_limiter_allows_within_limit(): void
    {
        $limiter = new RateLimiter($this->testDir);
        $this->assertTrue($limiter->attempt('test_key', 5, 60));
        $this->assertTrue($limiter->attempt('test_key', 5, 60));
        $this->assertTrue($limiter->attempt('test_key', 5, 60));
    }

    public function test_rate_limiter_blocks_over_limit(): void
    {
        $limiter = new RateLimiter($this->testDir);
        for ($i = 0; $i < 3; $i++) {
            $limiter->attempt('test_key', 3, 60);
        }
        $this->assertFalse($limiter->attempt('test_key', 3, 60));
    }

    public function test_rate_limiter_remaining(): void
    {
        $limiter = new RateLimiter($this->testDir);
        $this->assertSame(5, $limiter->remaining('test_key', 5, 60));
        $limiter->attempt('test_key', 5, 60);
        $this->assertSame(4, $limiter->remaining('test_key', 5, 60));
    }

    public function test_rate_limiter_reset(): void
    {
        $limiter = new RateLimiter($this->testDir);
        for ($i = 0; $i < 3; $i++) {
            $limiter->attempt('test_key', 3, 60);
        }
        $limiter->reset('test_key');
        $this->assertSame(3, $limiter->remaining('test_key', 3, 60));
    }

    public function test_idempotency_executes_once(): void
    {
        $idempotency = new Idempotency($this->testDir);
        $counter = 0;

        $result1 = $idempotency->execute('unique_key', function () use (&$counter) {
            $counter++;
            return 'result_' . $counter;
        });

        $result2 = $idempotency->execute('unique_key', function () use (&$counter) {
            $counter++;
            return 'result_' . $counter;
        });

        $this->assertSame('result_1', $result1);
        $this->assertSame('result_1', $result2);
        $this->assertSame(1, $counter);
    }

    public function test_idempotency_reset_allows_reexecution(): void
    {
        $idempotency = new Idempotency($this->testDir);
        $counter = 0;

        $idempotency->execute('key2', function () use (&$counter) {
            $counter++;
            return $counter;
        });

        $idempotency->reset('key2');

        $idempotency->execute('key2', function () use (&$counter) {
            $counter++;
            return $counter;
        });

        $this->assertSame(2, $counter);
    }

    public function test_distributed_lock_acquire_and_release(): void
    {
        $lock = new DistributedLock($this->testDir);
        $this->assertTrue($lock->acquire('lock1', 30));
        $this->assertTrue($lock->isLocked('lock1'));
        $lock->release('lock1');
        $this->assertFalse($lock->isLocked('lock1'));
    }

    public function test_distributed_lock_blocks_second_acquisition(): void
    {
        $lock = new DistributedLock($this->testDir);
        $this->assertTrue($lock->acquire('lock2', 30));
        $this->assertFalse($lock->acquire('lock2', 30));
        $lock->release('lock2');
    }

    public function test_distributed_lock_with_lock_executes_callback(): void
    {
        $lock = new DistributedLock($this->testDir);
        $executed = false;

        $lock->withLock('lock3', function () use (&$executed) {
            $executed = true;
            return 'done';
        });

        $this->assertTrue($executed);
    }

    public function test_distributed_lock_with_lock_throws_on_contention(): void
    {
        $this->expectException(\RuntimeException::class);
        $lock = new DistributedLock($this->testDir);
        $lock->acquire('lock4', 30);
        $lock->withLock('lock4', fn() => 'never');
    }
}
