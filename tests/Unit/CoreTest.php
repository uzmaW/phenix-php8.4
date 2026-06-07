<?php

declare(strict_types=1);

namespace Tests\Unit;

use Phoenix\Core\Container;
use Phoenix\Core\ServiceLocator;
use Phoenix\Core\Result;
use Phoenix\Core\Collection;
use PHPUnit\Framework\TestCase;

final class CoreTest extends TestCase
{
    protected function setUp(): void
    {
        $container = new Container();
        ServiceLocator::set($container);
    }

    public function test_container_set_and_get(): void
    {
        $container = new Container();
        $container->set('value', fn() => 42);

        $this->assertSame(42, $container->get('value'));
    }

    public function test_container_has(): void
    {
        $container = new Container();
        $container->set('key', fn() => 'hello');

        $this->assertTrue($container->has('key'));
        $this->assertFalse($container->has('nonexistent'));
    }

    public function test_container_throws_on_missing(): void
    {
        $this->expectException(\Exception::class);
        $container = new Container();
        $container->get('missing');
    }

    public function test_result_ok(): void
    {
        $result = Result::ok(42);
        $this->assertTrue($result->isOk());
        $this->assertFalse($result->isErr());
        $this->assertSame(42, $result->unwrap());
    }

    public function test_result_err(): void
    {
        $result = Result::err('fail');
        $this->assertTrue($result->isErr());
        $this->assertSame('default', $result->unwrapOr('default'));
    }

    public function test_result_map(): void
    {
        $result = Result::ok(10);
        $mapped = $result->map(fn($v) => $v * 2);
        $this->assertSame(20, $mapped->unwrap());
    }

    public function test_result_flatmap(): void
    {
        $result = Result::ok(5);
        $flat = $result->flatMap(fn($v) => Result::ok($v + 1));
        $this->assertSame(6, $flat->unwrap());
    }

    public function test_collection_create_and_count(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $this->assertSame(5, $collection->count());
    }

    public function test_collection_filter(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $filtered = $collection->filter(fn($v) => $v > 3);
        $this->assertSame([4, 5], array_values($filtered->all()));
    }

    public function test_collection_map(): void
    {
        $collection = new Collection([1, 2, 3]);
        $mapped = $collection->map(fn($v) => $v * 10);
        $this->assertSame([10, 20, 30], $mapped->all());
    }

    public function test_service_locator(): void
    {
        $container = new Container();
        $container->set('test_service', fn() => 'hello');
        ServiceLocator::set($container);

        $this->assertSame('hello', ServiceLocator::get('test_service'));
    }
}
