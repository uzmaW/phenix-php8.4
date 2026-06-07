<?php
namespace Phoenix\Middleware;

final class MiddlewareKernel
{
    private array $middleware = [];
    private array $globalMiddleware = [];

    public function push(string $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function prepend(string $middleware): void
    {
        array_unshift($this->middleware, $middleware);
    }

    public function addGlobal(string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function handle(mixed $request, callable $handler): mixed
    {
        $all = array_merge($this->globalMiddleware, $this->middleware);
        $pipeline = function ($request) use ($handler) {
            return $handler($request);
        };
        for ($i = count($all) - 1; $i >= 0; $i--) {
            $middlewareClass = $all[$i];
            $pipeline = function ($request) use ($middlewareClass, $pipeline) {
                $middleware = new $middlewareClass();
                return $middleware->handle($request, $pipeline);
            };
        }
        return $pipeline($request);
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
