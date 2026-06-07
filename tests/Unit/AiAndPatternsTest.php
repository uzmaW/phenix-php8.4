<?php

declare(strict_types=1);

namespace Tests\Unit;

use Phoenix\AI\Agent;
use Phoenix\AI\CryptoAgent;
use Phoenix\Blockchain\BitcoinAdapter;
use Phoenix\Blockchain\EthereumAdapter;
use Phoenix\Blockchain\SolanaAdapter;
use Phoenix\Blockchain\CosmosAdapter;
use Phoenix\CQRS\CommandBus;
use Phoenix\CQRS\Command;
use Phoenix\Saga\Saga;
use Phoenix\EventSourcing\Event;
use Phoenix\EventSourcing\AggregateRoot;
use Phoenix\Middleware\MiddlewareKernel;
use Phoenix\WebSocket\PubSub;
use Phoenix\WebSocket\SessionManager;
use PHPUnit\Framework\TestCase;

class OrderCreated extends Event
{
    public function __construct(
        public readonly string $orderId = '',
        public readonly float $amount = 0.0,
    ) {}

    public function getEventName(): string
    {
        return 'OrderCreated';
    }
}

class TestAggregate extends AggregateRoot
{
    public array $orders = [];

    protected function apply(Event $event): void
    {
        if ($event instanceof OrderCreated) {
            $this->orders[] = $event->orderId;
        }
    }

    public function createOrder(string $orderId, float $amount): void
    {
        $this->recordThat(new OrderCreated($orderId, $amount));
    }
}

class TestCommand extends Command
{
    public function __construct(public readonly string $data = '') {}

    public function getCommandName(): string
    {
        return 'TestCommand';
    }
}

final class AiAndPatternsTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/phoenix_ai_test_' . uniqid();
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

    public function test_agent_creation_and_think(): void
    {
        $agent = new Agent('TestBot', 'A test agent', 'Testing');
        $this->assertSame('TestBot', $agent->getName());
        $this->assertSame('A test agent', $agent->getRole());
        $this->assertFalse($agent->isRunning());

        $agent->start();
        $this->assertTrue($agent->isRunning());

        $response = $agent->think('hello');
        $this->assertStringContainsString('hello', $response);
        $this->assertCount(2, $agent->getMemory());

        $agent->stop();
        $this->assertFalse($agent->isRunning());
    }

    public function test_crypto_agent_portfolio(): void
    {
        $agent = new CryptoAgent('Trader', 'Crypto trader', 'Make money');
        $btc = new BitcoinAdapter();
        $btc->fund(1.5);
        $agent->addWallet('btc', $btc);

        $portfolio = $agent->getPortfolio();
        $this->assertArrayHasKey('btc', $portfolio);
        $this->assertStringContainsString('BTC', $portfolio['btc']['balance']);
    }

    public function test_bitcoin_adapter(): void
    {
        $btc = new BitcoinAdapter();
        $this->assertStringStartsWith('bc1q', $btc->getAddress());
        $btc->fund(1.0);
        $this->assertStringContainsString('BTC', $btc->getBalance());

        $tx = $btc->send('bc1qother', 0.5);
        $this->assertStringStartsWith('btc_tx_', $tx);
        $this->assertStringContainsString('BTC', $btc->getBalance());
    }

    public function test_ethereum_adapter(): void
    {
        $eth = new EthereumAdapter();
        $this->assertStringStartsWith('0x', $eth->getAddress());
        $eth->fund(10.0);
        $this->assertStringContainsString('ETH', $eth->getBalance());

        $tx = $eth->send('0xother', 2.0);
        $this->assertStringStartsWith('0x', $tx);
    }

    public function test_solana_adapter(): void
    {
        $sol = new SolanaAdapter();
        $sol->fund(100.0);
        $this->assertStringContainsString('SOL', $sol->getBalance());

        $tx = $sol->send('somewhere', 10.0);
        $this->assertNotEmpty($tx);
    }

    public function test_cosmos_adapter(): void
    {
        $cosmos = new CosmosAdapter();
        $cosmos->fund(500.0);
        $this->assertStringContainsString('ATOM', $cosmos->getBalance());

        $tx = $cosmos->send('cosmos1other', 100.0);
        $this->assertStringStartsWith('cosmos_tx_', $tx);
    }

    public function test_command_bus(): void
    {
        $bus = new CommandBus();
        $handled = false;

        $bus->register(TestCommand::class, function (TestCommand $cmd) use (&$handled) {
            $handled = true;
            return 'handled: ' . $cmd->data;
        });

        $result = $bus->dispatch(new TestCommand('test'));
        $this->assertTrue($handled);
        $this->assertSame('handled: test', $result);
        $this->assertTrue($bus->hasHandler(TestCommand::class));
    }

    public function test_command_bus_throws_on_no_handler(): void
    {
        $this->expectException(\RuntimeException::class);
        $bus = new CommandBus();
        $bus->dispatch(new TestCommand('test'));
    }

    public function test_saga_executes_steps(): void
    {
        $saga = new Saga();
        $steps = [];

        $saga->addStep('step1', function () use (&$steps) { $steps[] = 'step1'; return 'ok'; }, function () {});
        $saga->addStep('step2', function () use (&$steps) { $steps[] = 'step2'; return 'ok'; }, function () {});

        $results = $saga->execute();
        $this->assertCount(2, $results);
        $this->assertSame(['step1', 'step2'], $steps);
        $this->assertTrue($saga->isExecuted());
    }

    public function test_saga_compensates_on_failure(): void
    {
        $saga = new Saga();
        $compensated = false;

        $saga->addStep('step1', fn() => 'ok', function () use (&$compensated) { $compensated = true; });
        $saga->addStep('step2', function () { throw new \Exception('fail'); }, function () {});

        try {
            $saga->execute();
        } catch (\Exception $e) {
            $this->assertSame('fail', $e->getMessage());
        }

        $this->assertTrue($compensated);
    }

    public function test_event_sourcing_aggregate(): void
    {
        $aggregate = new TestAggregate();
        $aggregate->createOrder('order1', 100.0);
        $aggregate->createOrder('order2', 200.0);

        $this->assertSame(['order1', 'order2'], $aggregate->orders);

        $events = $aggregate->releaseEvents();
        $this->assertCount(2, $events);
        $this->assertSame(2, $aggregate->getVersion());
    }

    public function test_middleware_kernel(): void
    {
        $GLOBALS['test_log'] = [];
        $kernel = new MiddlewareKernel();

        $kernel->push(Middleware1::class);
        $kernel->push(Middleware2::class);

        $result = $kernel->handle('request', function ($req) {
            $GLOBALS['test_log'][] = 'handler';
            return 'response';
        });

        $this->assertSame('response', $result);
        $this->assertSame(['middleware1_before', 'middleware2_before', 'handler', 'middleware2_after', 'middleware1_after'], $GLOBALS['test_log']);
    }

    public function test_pubsub_publish_and_read(): void
    {
        $pubsub = new PubSub('test_channel', $this->testDir);
        $pubsub->publish(['type' => 'message', 'text' => 'hello']);
        $pubsub->publish(['type' => 'message', 'text' => 'world']);

        $messages = $pubsub->readMessages();
        $this->assertCount(2, $messages);
        $this->assertSame('hello', $messages[0]['text']);
    }

    public function test_session_manager(): void
    {
        $manager = new SessionManager($this->testDir);
        $token = $manager->create('user1', 'Alice');
        $this->assertNotEmpty($token);

        $session = $manager->validate($token);
        $this->assertNotNull($session);
        $this->assertSame('user1', $session['user_id']);

        $manager->destroy($token);
        $this->assertNull($manager->validate($token));
    }
}

class Middleware1 implements \Phoenix\Middleware\MiddlewareInterface
{
    public function handle(mixed $request, callable $next): mixed
    {
        $GLOBALS['test_log'][] = 'middleware1_before';
        $result = $next($request);
        $GLOBALS['test_log'][] = 'middleware1_after';
        return $result;
    }
}

class Middleware2 implements \Phoenix\Middleware\MiddlewareInterface
{
    public function handle(mixed $request, callable $next): mixed
    {
        $GLOBALS['test_log'][] = 'middleware2_before';
        $result = $next($request);
        $GLOBALS['test_log'][] = 'middleware2_after';
        return $result;
    }
}
