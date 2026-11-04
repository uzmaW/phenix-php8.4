<?php
declare(strict_types=1);
namespace Phoenix\CQRS;

use Phoenix\Core\Result;

// ---------------------------------------------------------------------------
// Query marker interfaces / base class
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Middleware
// ---------------------------------------------------------------------------

/**
 * Middleware wraps each query dispatch.
 * Implementations receive the query and a $next callable that executes the
 * remainder of the pipeline (including the final handler).
 */
interface QueryMiddleware
{
    public function handle(QueryInterface $query, callable $next): mixed;
}

/**
 * In-memory caching middleware.
 *
 * Serialises the query object as a cache key, so identical queries within
 * the TTL window bypass the handler and return the cached value.
 *
 * Call invalidate() to flush the cache (e.g. after a command that mutates
 * data the query would read).
 */
final class CachingQueryMiddleware implements QueryMiddleware
{
    /** @var array<string, array{value: mixed, ts: int}> */
    private array $cache = [];

    public function __construct(private readonly int $ttl = 60) {}

    public function handle(QueryInterface $query, callable $next): mixed
    {
        $key = md5(serialize($query));

        if (
            isset($this->cache[$key])
            && (time() - $this->cache[$key]['ts']) < $this->ttl
        ) {
            return $this->cache[$key]['value'];
        }

        $result = $next($query);

        $this->cache[$key] = ['value' => $result, 'ts' => time()];

        return $result;
    }

    /** Flush all cached query results. */
    public function invalidate(): void
    {
        $this->cache = [];
    }
}

/**
 * Logging middleware – records dispatched query class names and outcomes.
 * Outcomes are stored as 'ok' / 'err' when the result is a Result monad,
 * otherwise as 'mixed'.
 */
final class LoggingQueryMiddleware implements QueryMiddleware
{
    private array $log = [];

    public function handle(QueryInterface $query, callable $next): mixed
    {
        $class = get_class($query);
        $this->log[] = "query:start:{$class}";

        $result = $next($query);

        $outcome = $result instanceof Result
            ? ($result->isOk() ? 'ok' : 'err')
            : 'mixed';
        $this->log[] = "query:end:{$outcome}:{$class}";

        return $result;
    }

    public function getLog(): array
    {
        return $this->log;
    }

    public function clearLog(): void
    {
        $this->log = [];
    }
}

// ---------------------------------------------------------------------------
// QueryBus
// ---------------------------------------------------------------------------

/**
 * Query bus with middleware pipeline support.
 *
 * Usage:
 *   $bus = new QueryBus();
 *   $bus->addMiddleware(new CachingQueryMiddleware(ttl: 30));
 *   $bus->register(GetUserQuery::class, fn(GetUserQuery $q) => $repo->find($q->id));
 *   $user = $bus->handle(new GetUserQuery(id: 42));
 */
final class QueryBus
{
    /** @var array<string, callable(QueryInterface): mixed> */
    private array $handlers = [];

    /** @var list<QueryMiddleware> */
    private array $middleware = [];

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    public function register(string $queryClass, callable $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    public function addMiddleware(QueryMiddleware $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function hasHandler(string $queryClass): bool
    {
        return isset($this->handlers[$queryClass]);
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    /**
     * Dispatch a query through the middleware pipeline and return its result.
     *
     * @throws \RuntimeException when no handler is registered for the query class.
     */
    public function handle(QueryInterface $query): mixed
    {
        $class = get_class($query);

        if (!isset($this->handlers[$class])) {
            throw new \RuntimeException(
                "No handler registered for query: {$class}"
            );
        }

        // Innermost handler
        $core = fn(QueryInterface $q) => ($this->handlers[$class])($q);

        // Wrap middleware from outermost to innermost (LIFO around core)
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn(callable $next, QueryMiddleware $mw): callable
                => fn(QueryInterface $q) => $mw->handle($q, $next),
            $core,
        );

        return $pipeline($query);
    }
}
