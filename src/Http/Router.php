<?php

namespace Phoenix\Http;

class Router
{
    private array $routes = [];
    private array $compiledRoutes = [];

    public function get(string $path, callable|array $handler): self
    {
        $path = trim($path, '/');
        $this->routes['GET'][$path] = $handler;
        $this->compiledRoutes = [];
        return $this;
    }

    public function post(string $path, callable|array $handler): self
    {
        $path = trim($path, '/');
        $this->routes['POST'][$path] = $handler;
        $this->compiledRoutes = [];
        return $this;
    }

    public function put(string $path, callable|array $handler): self
    {
        $path = trim($path, '/');
        $this->routes['PUT'][$path] = $handler;
        $this->compiledRoutes = [];
        return $this;
    }

    public function delete(string $path, callable|array $handler): self
    {
        $path = trim($path, '/');
        $this->routes['DELETE'][$path] = $handler;
        $this->compiledRoutes = [];
        return $this;
    }

    public function dispatch(string $uri, string $method): mixed
    {
        $uri = trim(parse_url($uri, PHP_URL_PATH), '/');

        if (empty($this->compiledRoutes)) {
            $this->compiledRoutes = $this->routes;
        }

        $handler = $this->compiledRoutes[$method][$uri] ?? null;

        if (!$handler) {
            http_response_code(404);
            return "404 Not Found";
        }

        if (is_callable($handler)) {
            return $handler();
        }

        [$controller, $action] = $handler;
        return (new $controller)->$action();
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function clearRoutes(): void
    {
        $this->routes = [];
        $this->compiledRoutes = [];
    }
}
