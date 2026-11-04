<?php

declare(strict_types=1);

namespace Tests\Unit;

use Phoenix\Auth\JwtService;
use Phoenix\Core\Container;
use Phoenix\Core\Result;
use Phoenix\CQRS\CommandBus;
use Phoenix\CQRS\CommandInterface;
use Phoenix\CQRS\LoggingMiddleware;
use Phoenix\CQRS\ValidationMiddleware;
use Phoenix\CQRS\QueryBus;
use Phoenix\EventSourcing\AggregateRoot;
use Phoenix\EventSourcing\Event;
use Phoenix\EventSourcing\EventStore;
use Phoenix\EventSourcing\Projection;
use Phoenix\Saga\Saga;
use Phoenix\Support\Pipeline;
use Phoenix\Validation\Validator;
use PHPUnit\Framework\TestCase;

// ============================================================================
// Domain fixtures
// ============================================================================

final class CreateProductCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly float  $price,
    ) {}
}

final class GetProductQuery extends \Phoenix\CQRS\Query
{
    public function __construct(public readonly int $id) {}
}

final class ProductCreatedEvent extends Event
{
    public function __construct(
        private readonly string $productId,
        private readonly string $productName,
    ) {
        parent::__construct();
    }

    public function getEventName(): string
    {
        return 'ProductCreated';
    }

    public function toArray(): array
    {
        return [
            'type'        => 'ProductCreated',
            'payload'     => ['id' => $this->productId, 'name' => $this->productName],
            'occurred_at' => time(),
        ];
    }
}

final class ProductAggregate extends AggregateRoot
{
    public string $name      = '';
    public string $productId = '';

    public static function create(string $id, string $name): self
    {
        $a     = new self();
        $a->id = $id;
        $a->recordThat(new ProductCreatedEvent($id, $name));

        return $a;
    }

    protected function apply(Event $event): void
    {
        $data = $event->toArray();
        if (($data['type'] ?? '') === 'ProductCreated') {
            $this->productId = $data['payload']['id']   ?? '';
            $this->name      = $data['payload']['name'] ?? '';
        }
    }
}

final class ProductCountProjection implements Projection
{
    private int $count = 0;

    public function getName(): string  { return 'product_count'; }
    public function getState(): mixed  { return $this->count; }
    public function reset(): void      { $this->count = 0; }

    public function apply(array $eventData): void
    {
        if (($eventData['type'] ?? '') === 'ProductCreated') {
            $this->count++;
        }
    }
}

// ============================================================================
// Tests
// ============================================================================

final class BusinessEdgeCasesTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Result monad
    // -------------------------------------------------------------------------

    public function test_result_mapErr_transforms_error(): void
    {
        $r = Result::err('not found')->mapErr(fn ($e) => strtoupper($e));
        $this->assertSame('NOT FOUND', $r->unwrapErr());
    }

    public function test_result_mapErr_passes_ok_through(): void
    {
        $r = Result::ok(42)->mapErr(fn ($e) => 'x');
        $this->assertSame(42, $r->unwrap());
    }

    public function test_result_orElse_returns_fallback_on_err(): void
    {
        $r = Result::err('fail')->orElse(fn ($e) => Result::ok('recovered'));
        $this->assertSame('recovered', $r->unwrap());
    }

    public function test_result_orElse_passes_ok_through(): void
    {
        $r = Result::ok('value')->orElse(fn ($e) => Result::ok('other'));
        $this->assertSame('value', $r->unwrap());
    }

    public function test_result_match_branches_correctly(): void
    {
        $ok  = Result::ok(10)->match(fn ($v) => $v * 2, fn ($e) => -1);
        $err = Result::err('oops')->match(fn ($v) => $v * 2, fn ($e) => -1);
        $this->assertSame(20, $ok);
        $this->assertSame(-1, $err);
    }

    public function test_result_pipe_chains_transformations(): void
    {
        $r = Result::ok(5)->pipe(
            fn ($v) => Result::ok($v * 2),
            fn ($v) => Result::ok($v + 1),
        );
        $this->assertSame(11, $r->unwrap());
    }

    public function test_result_pipe_short_circuits_on_err(): void
    {
        $called = false;
        $r = Result::ok(5)->pipe(
            fn ($v) => Result::err('stop'),
            function ($v) use (&$called) {
                $called = true;

                return Result::ok($v);
            },
        );
        $this->assertTrue($r->isErr());
        $this->assertFalse($called);
    }

    public function test_result_try_catches_exceptions(): void
    {
        $ok  = Result::try(fn () => 42);
        $err = Result::try(fn () => throw new \RuntimeException('boom'));
        $this->assertSame(42, $ok->unwrap());
        $this->assertInstanceOf(\RuntimeException::class, $err->unwrapErr());
    }

    public function test_result_collect_aggregates_ok_values(): void
    {
        $r = Result::collect([Result::ok(1), Result::ok(2), Result::ok(3)]);
        $this->assertSame([1, 2, 3], $r->unwrap());
    }

    public function test_result_collect_short_circuits_on_first_err(): void
    {
        $r = Result::collect([Result::ok(1), Result::err('bad'), Result::ok(3)]);
        $this->assertTrue($r->isErr());
        $this->assertSame('bad', $r->unwrapErr());
    }

    public function test_result_fromNullable_wraps_value(): void
    {
        $this->assertSame('hello', Result::fromNullable('hello', 'missing')->unwrap());
        $this->assertSame('missing', Result::fromNullable(null, 'missing')->unwrapErr());
    }

    public function test_result_toOption_returns_null_on_err(): void
    {
        $this->assertSame(42, Result::ok(42)->toOption());
        $this->assertNull(Result::err('x')->toOption());
    }

    public function test_result_andThen_chains_fallible_ops(): void
    {
        $r = Result::ok(5)->andThen(
            fn ($v) => $v > 3 ? Result::ok($v * 10) : Result::err('too small')
        );
        $this->assertSame(50, $r->unwrap());
    }

    public function test_result_unwrapOrElse_calls_fn_on_err(): void
    {
        $r = Result::err('missing')->unwrapOrElse(fn ($e) => 'default_' . $e);
        $this->assertSame('default_missing', $r);
    }

    // -------------------------------------------------------------------------
    // CQRS — CommandBus
    // -------------------------------------------------------------------------

    public function test_command_bus_dispatches_and_wraps_result(): void
    {
        $bus = new CommandBus();
        $bus->register(
            CreateProductCommand::class,
            fn ($cmd) => ['name' => $cmd->name, 'price' => $cmd->price],
        );
        $result = $bus->dispatch(new CreateProductCommand('Widget', 9.99));
        $this->assertTrue($result->isOk());
        $this->assertSame('Widget', $result->unwrap()['name']);
    }

    public function test_command_bus_returns_err_for_no_handler(): void
    {
        $bus    = new CommandBus();
        $result = $bus->dispatch(new CreateProductCommand('X', 1));
        $this->assertTrue($result->isErr());
    }

    public function test_command_bus_logging_middleware_records_dispatch(): void
    {
        $bus    = new CommandBus();
        $logger = new LoggingMiddleware();
        $bus->addMiddleware($logger);
        $bus->register(CreateProductCommand::class, fn ($cmd) => $cmd->name);
        $bus->dispatch(new CreateProductCommand('Test', 1.0));
        $log = $logger->getLog();
        $this->assertStringContainsString('start', $log[0]);
        $this->assertStringContainsString('end:ok', $log[1]);
    }

    public function test_command_bus_validation_middleware_blocks_invalid(): void
    {
        $bus        = new CommandBus();
        $validation = new ValidationMiddleware();
        $validation->addValidator(
            CreateProductCommand::class,
            fn ($cmd) => $cmd->price <= 0 ? 'Price must be positive' : null,
        );
        $bus->addMiddleware($validation);
        $bus->register(CreateProductCommand::class, fn ($cmd) => $cmd->name);
        $result = $bus->dispatch(new CreateProductCommand('Bad', -1.0));
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('Price must be positive', $result->unwrapErr());
    }

    public function test_command_bus_validation_middleware_passes_valid(): void
    {
        $bus        = new CommandBus();
        $validation = new ValidationMiddleware();
        $validation->addValidator(
            CreateProductCommand::class,
            fn ($cmd) => $cmd->price <= 0 ? 'Price must be positive' : null,
        );
        $bus->addMiddleware($validation);
        $bus->register(CreateProductCommand::class, fn ($cmd) => $cmd->name);
        $result = $bus->dispatch(new CreateProductCommand('Good', 10.0));
        $this->assertTrue($result->isOk());
    }

    // -------------------------------------------------------------------------
    // CQRS — QueryBus
    // -------------------------------------------------------------------------

    public function test_query_bus_dispatches_correctly(): void
    {
        $bus = new QueryBus();
        $bus->register(GetProductQuery::class, fn ($q) => ['id' => $q->id, 'name' => 'Product']);
        $result = $bus->handle(new GetProductQuery(42));
        $this->assertSame(42, $result['id']);
    }

    public function test_query_bus_throws_for_no_handler(): void
    {
        $bus = new QueryBus();
        $this->expectException(\RuntimeException::class);
        $bus->handle(new GetProductQuery(1));
    }

    // -------------------------------------------------------------------------
    // Saga
    // -------------------------------------------------------------------------

    public function test_saga_executes_all_steps_successfully(): void
    {
        $log  = [];
        $saga = new Saga();
        $saga->addStep(
            'reserve',
            function () use (&$log) { $log[] = 'reserved'; return Result::ok('reserved'); },
            function () use (&$log) { $log[] = 'released'; },
        );
        $saga->addStep(
            'charge',
            function () use (&$log) { $log[] = 'charged'; return Result::ok('charged'); },
            function () use (&$log) { $log[] = 'refunded'; },
        );

        $result = $saga->execute();
        $this->assertTrue($result->isOk());
        $this->assertSame(['reserved', 'charged'], $log);
        $this->assertTrue($saga->isExecuted());
    }

    public function test_saga_compensates_on_step_failure(): void
    {
        $compensated = [];
        $saga        = new Saga();
        $saga->addStep(
            'step1',
            fn () => Result::ok('ok'),
            function () use (&$compensated) { $compensated[] = 'step1_comp'; },
        );
        $saga->addStep(
            'step2',
            fn () => Result::err('step2 failed'),
            function () use (&$compensated) { $compensated[] = 'step2_comp'; },
        );

        $result = $saga->execute();
        $this->assertTrue($result->isErr());
        $this->assertContains('step1_comp', $compensated);
        $this->assertGreaterThanOrEqual(2, count($saga->getAuditLog()));
    }

    public function test_saga_dry_run_does_not_execute_actions(): void
    {
        $executed = false;
        $saga     = new Saga();
        $saga->addStep(
            'step1',
            function () use (&$executed) { $executed = true; return Result::ok('x'); },
            fn () => null,
        );
        $saga->dryRun(true)->execute();
        $this->assertFalse($executed);
    }

    public function test_saga_audit_log_tracks_events(): void
    {
        $saga = new Saga();
        $saga->addStep('a', fn () => Result::ok(1), fn () => null);
        $saga->addStep('b', fn () => Result::err('fail'), fn () => null);
        $saga->execute();
        $this->assertGreaterThan(0, count($saga->getAuditLog()));
    }

    public function test_saga_compensation_errors_are_captured(): void
    {
        $saga = new Saga();
        $saga->addStep(
            'step1',
            fn () => Result::ok('ok'),
            fn () => throw new \RuntimeException('comp error'),
        );
        $saga->addStep('step2', fn () => Result::err('main error'), fn () => null);

        $result = $saga->execute();
        $this->assertTrue($result->isErr());
        $data = $result->unwrapErr();
        $this->assertArrayHasKey('compensation_errors', $data);
        $this->assertNotEmpty($data['compensation_errors']);
    }

    // -------------------------------------------------------------------------
    // EventSourcing
    // -------------------------------------------------------------------------

    public function test_event_store_saves_and_loads_events(): void
    {
        $store   = new EventStore(sys_get_temp_dir() . '/phoenix_test_' . uniqid());
        $product = ProductAggregate::create('p1', 'Keyboard');
        $result  = $store->save($product);
        $this->assertTrue($result->isOk());
        $events = $store->loadEvents('p1');
        $this->assertCount(1, $events);
        $this->assertSame('ProductCreated', $events[0]['type']);
    }

    public function test_event_store_detects_version_conflict(): void
    {
        $store = new EventStore(sys_get_temp_dir() . '/phoenix_test_' . uniqid());

        $product = ProductAggregate::create('p2', 'Mouse');
        $store->save($product);   // persists 1 event

        $product2 = ProductAggregate::create('p2', 'Mouse 2');
        $result   = $store->save($product2, expectedVersion: 0); // current is 1 → conflict
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('Version conflict', $result->unwrapErr());
    }

    public function test_event_store_accepts_correct_expected_version(): void
    {
        $store   = new EventStore(sys_get_temp_dir() . '/phoenix_test_' . uniqid());
        $product = ProductAggregate::create('p3', 'Monitor');
        $store->save($product);   // version is now 1

        $product2 = ProductAggregate::create('p3', 'Monitor v2');
        $result   = $store->save($product2, expectedVersion: 1);
        $this->assertTrue($result->isOk());
    }

    public function test_event_store_projection_counts_events(): void
    {
        $store      = new EventStore(sys_get_temp_dir() . '/phoenix_test_' . uniqid());
        $projection = new ProductCountProjection();
        $store->addProjection($projection);

        $store->save(ProductAggregate::create('prod1', 'A'));
        $store->save(ProductAggregate::create('prod2', 'B'));

        $this->assertSame(2, $projection->getState());
    }

    public function test_event_store_rebuild_replays_all_projections(): void
    {
        $dir   = sys_get_temp_dir() . '/phoenix_test_' . uniqid();
        $store = new EventStore($dir);

        // Save without projection registered
        $store->save(ProductAggregate::create('x1', 'X'));
        $store->save(ProductAggregate::create('x2', 'Y'));

        // Register projection and rebuild
        $projection = new ProductCountProjection();
        $store->addProjection($projection);
        $total = $store->rebuildAllProjections();

        $this->assertSame(2, $total);
        $this->assertSame(2, $projection->getState());
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_validator_passes_valid_data(): void
    {
        $v = new Validator();
        $v->field('name')->required()->min(3);
        $v->field('email')->required()->email();
        $result = $v->validate(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->assertTrue($result->isOk());
    }

    public function test_validator_fails_invalid_email(): void
    {
        $v = new Validator();
        $v->field('email')->required()->email();
        $result = $v->validate(['email' => 'not-an-email']);
        $this->assertTrue($result->isErr());
        $this->assertArrayHasKey('email', $result->unwrapErr());
    }

    public function test_validator_required_fails_on_missing_field(): void
    {
        $v = new Validator();
        $v->field('name')->required();
        $result = $v->validate([]);
        $this->assertTrue($result->isErr());
    }

    public function test_validator_static_make_passes(): void
    {
        $result = Validator::make(
            ['email' => 'test@test.com', 'name' => 'Bob'],
            ['email' => 'required|email', 'name' => 'required|min:2'],
        );
        $this->assertTrue($result->isOk());
    }

    public function test_validator_static_make_fails_short_name(): void
    {
        $result = Validator::make(
            ['email' => 'x@x.com', 'name' => 'A'],
            ['email' => 'required|email', 'name' => 'required|min:2'],
        );
        $this->assertTrue($result->isErr());
        $this->assertArrayHasKey('name', $result->unwrapErr());
    }

    public function test_validator_custom_rule(): void
    {
        $v = new Validator();
        $v->field('age')->custom(fn ($v) => (int) $v >= 18, 'Must be 18 or older');
        $this->assertTrue($v->validate(['age' => 20])->isOk());
        $this->assertTrue($v->validate(['age' => 16])->isErr());
    }

    public function test_validator_in_rule(): void
    {
        $v = new Validator();
        $v->field('status')->in(['active', 'inactive', 'pending']);
        $this->assertTrue($v->validate(['status' => 'active'])->isOk());
        $this->assertTrue($v->validate(['status' => 'deleted'])->isErr());
    }

    public function test_validator_numeric_min_max_value(): void
    {
        $v = new Validator();
        $v->field('qty')->required()->numeric()->minValue(1)->maxValue(100);
        $this->assertTrue($v->validate(['qty' => '50'])->isOk());
        $this->assertTrue($v->validate(['qty' => '0'])->isErr());
        $this->assertTrue($v->validate(['qty' => '200'])->isErr());
        $this->assertTrue($v->validate(['qty' => 'abc'])->isErr());
    }

    public function test_validator_collects_multiple_errors_per_field(): void
    {
        $v = new Validator();
        $v->field('pass')->required()->min(8)->regex('/[0-9]/');
        $result = $v->validate(['pass' => 'abc']); // fails min AND regex
        $this->assertTrue($result->isErr());
        $errors = $result->unwrapErr();
        $this->assertCount(2, $errors['pass']);
    }

    // -------------------------------------------------------------------------
    // JWT
    // -------------------------------------------------------------------------

    public function test_jwt_creates_and_validates_token(): void
    {
        $jwt    = new JwtService('super-secret-key', ttl: 3600);
        $token  = $jwt->create(['user_id' => 42, 'role' => 'admin']);
        $result = $jwt->validate($token);
        $this->assertTrue($result->isOk());
        $claims = $result->unwrap();
        $this->assertSame(42, $claims['user_id']);
        $this->assertSame('admin', $claims['role']);
    }

    public function test_jwt_rejects_tampered_signature(): void
    {
        $jwt     = new JwtService('secret');
        $token   = $jwt->create(['user_id' => 1]);
        $tampered = $token . 'x';
        $this->assertTrue($jwt->validate($tampered)->isErr());
    }

    public function test_jwt_rejects_expired_token(): void
    {
        $jwt    = new JwtService('secret', ttl: -1);
        $token  = $jwt->create(['user_id' => 1]);
        $result = $jwt->validate($token);
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('expired', $result->unwrapErr());
    }

    public function test_jwt_refresh_flow_produces_new_access_token(): void
    {
        $jwt          = new JwtService('refresh-secret', ttl: 3600, refreshTtl: 86400);
        $refreshToken = $jwt->createRefreshToken(['user_id' => 5]);
        $result       = $jwt->refresh($refreshToken);
        $this->assertTrue($result->isOk());
        $this->assertTrue($jwt->validate($result->unwrap())->isOk());
    }

    public function test_jwt_refresh_rejects_access_token(): void
    {
        $jwt    = new JwtService('secret');
        $token  = $jwt->create(['user_id' => 1]);
        $result = $jwt->refresh($token);
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('refresh', $result->unwrapErr());
    }

    public function test_jwt_revocation_blacklists_token(): void
    {
        $jwt   = new JwtService('revoke-secret');
        $token = $jwt->create(['user_id' => 9]);
        $this->assertTrue($jwt->validate($token)->isOk());
        $jwt->revoke($token);
        $result = $jwt->validate($token);
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('revoked', $result->unwrapErr());
    }

    public function test_jwt_get_claims_without_validation(): void
    {
        $jwt    = new JwtService('secret');
        $token  = $jwt->create(['user_id' => 7, 'role' => 'editor']);
        $claims = $jwt->getClaims($token);
        $this->assertSame(7, $claims['user_id']);
    }

    // -------------------------------------------------------------------------
    // Container
    // -------------------------------------------------------------------------

    public function test_container_tagged_bindings(): void
    {
        $c = new Container();
        $c->set('logger.file', fn () => (object) ['name' => 'file']);
        $c->set('logger.db',   fn () => (object) ['name' => 'db']);
        $c->tag('loggers', ['logger.file', 'logger.db']);

        $loggers = $c->tagged('loggers');
        $this->assertCount(2, $loggers);
        $names = array_map(fn ($l) => $l->name, $loggers);
        $this->assertContains('file', $names);
        $this->assertContains('db',   $names);
    }

    public function test_container_bind_interface_to_concrete(): void
    {
        $c = new Container();
        $c->set('ConcreteService', fn () => (object) ['value' => 42]);
        $c->bind('ServiceInterface', 'ConcreteService');
        $resolved = $c->get('ServiceInterface');
        $this->assertSame(42, $resolved->value);
    }

    public function test_container_detects_circular_dependency(): void
    {
        $c = new Container();
        $c->set('a', fn ($c) => $c->get('b'));
        $c->set('b', fn ($c) => $c->get('a'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/[Cc]ircular/');
        $c->get('a');
    }

    public function test_container_singleton_returns_same_instance(): void
    {
        $c = new Container();
        $c->singleton('counter', fn () => new \stdClass());
        $a = $c->get('counter');
        $b = $c->get('counter');
        $this->assertSame($a, $b);
    }

    public function test_container_forget_clears_instance(): void
    {
        $c = new Container();
        $c->set('svc', fn () => new \stdClass());
        $c->get('svc');
        $c->forget('svc');
        $this->assertFalse($c->has('svc'));
    }

    // -------------------------------------------------------------------------
    // Pipeline
    // -------------------------------------------------------------------------

    public function test_pipeline_transforms_payload(): void
    {
        $result = Pipeline::send(5)
            ->through(
                fn ($v, $next) => $next($v * 2),
                fn ($v, $next) => $next($v + 1),
            )
            ->thenReturn();
        $this->assertSame(11, $result);
    }

    public function test_pipeline_thenResult_wraps_exceptions(): void
    {
        $result = Pipeline::send('input')
            ->through(fn ($v, $next) => throw new \RuntimeException('pipeline error'))
            ->thenResult();
        $this->assertTrue($result->isErr());
        $this->assertInstanceOf(\RuntimeException::class, $result->unwrapErr());
    }

    public function test_pipeline_then_applies_destination(): void
    {
        $result = Pipeline::send(10)
            ->through(fn ($v, $next) => $next($v + 5))
            ->then(fn ($v) => "Value: {$v}");
        $this->assertSame('Value: 15', $result);
    }

    public function test_pipeline_pipe_alias_works(): void
    {
        $result = Pipeline::send('hello')
            ->pipe(fn ($v, $next) => $next(strtoupper($v)))
            ->thenReturn();
        $this->assertSame('HELLO', $result);
    }

    public function test_pipeline_empty_stages_passes_payload_through(): void
    {
        $result = Pipeline::send(99)->thenReturn();
        $this->assertSame(99, $result);
    }

    public function test_pipeline_build_creates_onion_middleware(): void
    {
        $log = [];
        $handle = Pipeline::send('req')
            ->through(
                function ($v, $next) use (&$log) {
                    $log[] = 'before-1';
                    $r     = $next($v);
                    $log[] = 'after-1';
                    return $r;
                },
                function ($v, $next) use (&$log) {
                    $log[] = 'before-2';
                    $r     = $next($v);
                    $log[] = 'after-2';
                    return $r;
                },
            )
            ->build(fn ($v) => 'response');

        $response = $handle('req');
        $this->assertSame('response', $response);
        $this->assertSame(['before-1', 'before-2', 'after-2', 'after-1'], $log);
    }
}
