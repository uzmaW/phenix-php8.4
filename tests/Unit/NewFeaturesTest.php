<?php

declare(strict_types=1);

namespace Tests\Unit;

use Phoenix\Concurrency\AsyncResult;
use Phoenix\Core\Result;
use Phoenix\Macros\BaseMacro;
use Phoenix\Macros\Helpers;
use Phoenix\Macros\MacroSystem;
use Phoenix\Macros\MacroUtils;
use Phoenix\Collections\OptimizedHashMap;
use Phoenix\Collections\OptimizedHashSet;
use Phoenix\Collections\OptimizedVector;
use Phoenix\Error\BusinessLogicError;
use Phoenix\Error\NotFoundError;
use Phoenix\Pattern\BuilderFactory;
use Phoenix\Pattern\ComplexBuilder;
use Phoenix\Pattern\GenericState;
use Phoenix\Pattern\ObservableSubject;
use Phoenix\Pattern\Observer;
use Phoenix\Pattern\State;
use Phoenix\Pattern\StateMachineBuilder;
use Phoenix\Pattern\StateMachineUtils;
use Phoenix\Pattern\Subject;
use PHPUnit\Framework\TestCase;

final class NewFeaturesTest extends TestCase
{
    public function test_vector_push_get_and_bounds(): void
    {
        $vector = new OptimizedVector();
        $vector->push('a')->push('b')->push_batch(['c', 'd']);

        $this->assertSame(4, $vector->count());
        $this->assertSame('a', $vector->get(0));
        $this->assertSame('d', $vector->last());
        $this->assertFalse($vector->is_empty());
    }

    public function test_vector_bounds_check_throws(): void
    {
        $vector = new OptimizedVector(['only']);

        $this->expectException(\OutOfBoundsException::class);
        $vector->get(5);
    }

    public function test_vector_remove_and_pop(): void
    {
        $vector = new OptimizedVector([1, 2, 3, 2]);
        $vector->remove_first(2);

        $this->assertSame([1, 3, 2], $vector->to_array());
        $this->assertSame(2, $vector->pop());
        $this->assertSame([1, 3], $vector->to_array());
        $this->assertTrue($vector->contains(3));
        $this->assertFalse($vector->contains(99));
    }

    public function test_vector_supports_foreach_iteration(): void
    {
        $vector = new OptimizedVector([1, 2, 3]);
        $sum = 0;
        foreach ($vector as $value) {
            $sum += $value;
        }

        $this->assertSame(6, $sum);
    }

    public function test_vector_map_filter(): void
    {
        $vector = new OptimizedVector([1, 2, 3, 4]);

        $doubled = $vector->map(fn (int $n): int => $n * 2);
        $evens = $vector->filter(fn (int $n): bool => $n % 2 === 0);

        $this->assertSame([2, 4, 6, 8], $doubled->to_array());
        $this->assertSame([2, 4], $evens->to_array());
        $this->assertSame([1, 2, 3, 4], $vector->to_array());
    }

    public function test_vector_metrics_are_reported(): void
    {
        $vector = new OptimizedVector();
        $vector->push('x');

        $metrics = $vector->get_performance_metrics();

        $this->assertArrayHasKey('length', $metrics);
        $this->assertArrayHasKey('push_count', $metrics);
        $this->assertSame(1, $metrics['length']);
    }

    public function test_hashmap_set_get_overwrite_keeps_count(): void
    {
        $map = new OptimizedHashMap();
        $map->set('a', 1)->set('b', 2)->set('a', 3);

        $this->assertSame(3, $map->get('a'));
        $this->assertSame(2, $map->count());
        $this->assertTrue($map->has('b'));
        $this->assertFalse($map->has('missing'));
    }

    public function test_hashmap_null_values_are_still_present(): void
    {
        $map = new OptimizedHashMap();
        $map->set('k', null);

        $this->assertTrue($map->has('k'));
        $this->assertNull($map->get('k'));
    }

    public function test_hashmap_set_many_and_remove(): void
    {
        $map = new OptimizedHashMap();
        $map->set_many(['x' => 1, 'y' => 2, 'z' => 3]);

        $this->assertSame(['x', 'y', 'z'], $map->keys());
        $this->assertSame([1, 2, 3], $map->values());
        $this->assertTrue($map->remove('y'));
        $this->assertFalse($map->remove('y'));
        $this->assertSame(2, $map->count());
    }

    public function test_hashmap_grows_beyond_initial_capacity(): void
    {
        $map = new OptimizedHashMap(4);
        for ($i = 0; $i < 100; $i++) {
            $map->set("k$i", $i);
        }

        $this->assertSame(100, $map->count());
        $this->assertSame(42, $map->get('k42'));
        $this->assertGreaterThan(0, $map->get_performance_metrics()['rehash_count']);
    }

    public function test_hashmap_supports_foreach_iteration(): void
    {
        $map = new OptimizedHashMap();
        $map->set('a', 1)->set('b', 2);

        $sum = 0;
        foreach ($map as $key => $value) {
            $sum += $value;
        }

        $this->assertSame(3, $sum);
    }

    public function test_hashset_deduplicates_and_supports_set_algebra(): void
    {
        $a = new OptimizedHashSet(['x', 'y', 'z', 'x']);
        $b = new OptimizedHashSet(['y', 'z', 'w']);

        $this->assertSame(3, $a->count());
        $this->assertTrue($a->contains('x'));
        $this->assertFalse($a->contains('w'));

        $this->assertEqualsCanonicalizing(['x', 'y', 'z', 'w'], $a->union($b)->to_array());
        $this->assertEqualsCanonicalizing(['y', 'z'], $a->intersection($b)->to_array());
        $this->assertSame(['x'], $a->difference($b)->to_array());
    }

    public function test_hashset_supports_foreach_iteration(): void
    {
        $set = new OptimizedHashSet(['a', 'b']);
        $seen = [];
        foreach ($set as $item) {
            $seen[] = $item;
        }

        $this->assertEqualsCanonicalizing(['a', 'b'], $seen);
    }

    public function test_async_result_ok_and_then_chain(): void
    {
        $future = AsyncResult::defer(fn (): int => 21)
            ->then(fn (int $n): int => $n * 2);

        $this->assertTrue($future->wait());
        $this->assertSame(42, $future->get());
        $this->assertTrue($future->is_ok());
    }

    public function test_async_result_err_catch_and_default(): void
    {
        $failed = AsyncResult::defer(function (): void {
            throw new \RuntimeException('gateway down');
        });

        $recovered = $failed->catch_(fn (\Throwable $e): string => 'recovered: ' . $e->getMessage());

        $this->assertTrue($failed->is_err());
        $this->assertSame('gateway down', $failed->get_error()?->getMessage());
        $this->assertSame('recovered: gateway down', $recovered->get());
        $this->assertSame('fallback', AsyncResult::err(new \RuntimeException('x'))->get_value_or('fallback'));
    }

    public function test_async_result_all_collects_values(): void
    {
        $combined = AsyncResult::all([AsyncResult::ok(1), AsyncResult::defer(fn (): int => 2)]);

        $this->assertSame([1, 2], $combined->get());
    }

    public function test_async_result_interops_with_core_result(): void
    {
        $fromOk = AsyncResult::from_result(Result::ok('value'));
        $fromErr = AsyncResult::from_result(Result::err('broken'));

        $this->assertSame('value', $fromOk->get());
        $this->assertTrue($fromErr->is_err());
        $this->assertSame('broken', $fromErr->to_result()->unwrapErr()->getMessage());
    }

    public function test_state_machine_happy_path_and_metrics(): void
    {
        $machine = $this->orderMachine($context);

        $this->assertSame('created', $machine->get_current_state());

        // Guard requires $context->paid === true — simulate payment capture
        $context->paid = true;
        $this->assertTrue($machine->transition_to('paid')->isOk());
        $this->assertTrue($machine->transition_to('shipped')->isOk());

        $metrics = $machine->get_metrics();
        $this->assertSame(2, $metrics['transitions_successful']);
    }

    public function test_state_machine_guard_blocks_transition(): void
    {
        $machine = $this->orderMachine($context);

        $result = $machine->transition_to('paid');

        $this->assertTrue($result->isErr());
        $this->assertInstanceOf(BusinessLogicError::class, $result->unwrapErr());
        $this->assertSame('created', $machine->get_current_state());

        // Flip the guard, retry succeeds
        $context->paid = true;
        $this->assertTrue($machine->transition_to('paid')->isOk());
    }

    public function test_state_machine_rejects_unknown_and_backward_transitions(): void
    {
        $machine = $this->orderMachine($context);
        $context->paid = true;
        $machine->transition_to('paid');
        $machine->transition_to('shipped');

        $this->assertTrue($machine->transition_to('created')->isErr());
        $this->assertTrue($machine->transition_to('does_not_exist')->isErr());
        $this->assertSame('shipped', $machine->get_current_state());
    }

    public function test_state_machine_entry_hooks_fire(): void
    {
        $machine = $this->orderMachine($context);
        $context->paid = true;
        $machine->transition_to('paid');

        $this->assertContains('paid', $context->log);
    }

    public function test_state_machine_utils_create_simple(): void
    {
        $machine = StateMachineUtils::create_simple('a', ['a', 'b', 'c']);

        $this->assertSame('a', $machine->get_current_state());
        $this->assertTrue($machine->transition_to('c')->isOk());
        $this->assertTrue($machine->transition_to('b')->isOk());
    }

    public function test_generic_state_allow_list(): void
    {
        $state = new GenericState('draft');
        $state->allow_transition_to('published');

        $this->assertTrue($state->can_transition_to('published'));
        $this->assertFalse($state->can_transition_to('archived'));
    }

    public function test_observable_subject_notifies_observers(): void
    {
        $subject = new ObservableSubject('orders');
        $seen = [];
        $observer = new class($seen) implements Observer {
            public function __construct(public array &$seen) {}

            public function update(Subject $subject): void
            {
                $this->seen[] = $subject->get_state();
            }
        };

        $subject->attach($observer);
        $subject->set_state('updated');
        $subject->notify();

        $this->assertSame(['updated'], $seen);
        $this->assertSame(1, ObservableSubject::get_metrics()['notifications_sent']);

        $subject->detach($observer);
        $subject->set_state('ignored');
        $subject->notify();
        $this->assertSame(['updated'], $seen);
    }

    public function test_observable_subject_batch_mode_queues_and_flushes(): void
    {
        $subject = new ObservableSubject('batch');
        $seen = [];
        $observer = new class($seen) implements Observer {
            public function __construct(public array &$seen) {}

            public function update(Subject $subject): void
            {
                $this->seen[] = $subject->get_state();
            }
        };

        $subject->attach($observer);
        $subject->set_batch_mode(true);
        $subject->set_state('one');
        $subject->notify();
        $subject->set_state('two');
        $subject->notify();

        $this->assertSame([], $seen);

        $flushed = $subject->flush();
        $this->assertSame(2, $flushed);
        $this->assertSame(['one', 'two'], $seen[0]);
    }

    public function test_fluent_builder_builds_configured_class(): void
    {
        $point = BuilderFactory::create(Line::class)
            ->set('x', 3)
            ->set('y', 4)
            ->build();

        $this->assertSame(3, $point->x);
        $this->assertSame(4, $point->y);
    }

    public function test_fluent_builder_validation_failure_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new \Phoenix\Pattern\FluentBuilder(Line::class))
            ->validate(fn (array $props): bool => false)
            ->build();
    }

    public function test_complex_builder_runs_steps_and_hooks(): void
    {
        $hookRan = false;
        $builder = new ComplexBuilder(Line::class);
        $builder->add_step('coords', fn (): array => ['x' => 10, 'y' => 20]);
        $builder->add_post_build_hook(function () use (&$hookRan): void {
            $hookRan = true;
        });

        $line = $builder->build();

        $this->assertSame(10, $line->x);
        $this->assertSame(20, $line->y);
        $this->assertTrue($hookRan);
    }

    public function test_macro_system_caches_expansions(): void
    {
        $system = MacroSystem::get_instance();
        $system->register_macro('test_greeting', new class ('test_greeting') extends BaseMacro {
            public function expand(array $arguments): array
            {
                return ["echo 'hello {$arguments[0]}';"];
            }
        });

        $first = $system->expand_macro('test_greeting', ['world']);
        $second = $system->expand_macro('test_greeting', ['world']);

        $this->assertFalse($first->has_errors());
        $this->assertSame($first->code, $second->code);
        $this->assertGreaterThan(0, MacroSystem::get_metrics()['cache_hits']);
    }

    public function test_macro_system_reports_missing_macros(): void
    {
        $expansion = MacroSystem::get_instance()->expand_macro('definitely_missing', []);

        $this->assertTrue($expansion->has_errors());
    }

    public function test_macro_utils_generate_repository_scaffold(): void
    {
        MacroUtils::register_builtin_macros();
        $expansion = MacroUtils::generate('repository', ['Invoice', 'App\\Repositories\\InvoiceRepositoryInterface']);

        $this->assertFalse($expansion->has_errors());
        $this->assertStringContainsString('class InvoiceRepository implements', implode("\n", $expansion->code));
    }

    public function test_macro_utils_helpers(): void
    {
        $this->assertSame(3, Helpers::vec([1, 2, 3])->count());
        $this->assertSame(1, Helpers::map(['a' => 1])->get('a'));
        $this->assertSame(2, Helpers::set(['x', 'y', 'x'])->count());
        $this->assertSame(7, Helpers::cast('int', '7'));
        $this->assertStringStartsWith('id_', Helpers::generate_id());
    }

    public function test_domain_error_types(): void
    {
        $notFound = new NotFoundError('Order', 42);
        $business = new BusinessLogicError('Out of stock', 'out_of_stock');

        $this->assertStringContainsString('Order', $notFound->get_message());
        $this->assertStringContainsString('42', $notFound->get_message());
        $this->assertSame('out_of_stock', $business->business_code);
    }

    private function orderMachine(?object &$context = null): \Phoenix\Pattern\StateMachine
    {
        $context = (object) ['paid' => false, 'log' => []];

        return StateMachineBuilder::create('test-order')
            ->with_context($context)
            ->initial_state('created')
            ->state('created', fn (State $state) => $state
                ->allow_transition_to('paid')
                ->allow_transition_to('cancelled')
            )
            ->state('paid', fn (State $state) => $state
                ->allow_transition_to('shipped')
                ->on_entry(fn (object $ctx) => $ctx->log[] = 'paid')
            )
            ->state('shipped')
            ->state('cancelled')
            ->transition('created', 'paid', fn (object $ctx): bool => $ctx->paid === true)
            ->transition('created', 'cancelled')
            ->transition('paid', 'shipped')
            ->build();
    }
}

final class Line
{
    public function __construct(public int $x = 0, public int $y = 0) {}
}
