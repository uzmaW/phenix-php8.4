<?php

namespace Phoenix\Http;

class Router
{
    private array $routes = [];
    private static array $allRoutes = [];

    public function get(string $path, callable|array $handler): self
    {
        $this->routes['GET'][trim($path, '/')] = $handler;
        self::$allRoutes['GET'][trim($path, '/')] = $handler;
        return $this;
    }

    public function post(string $path, callable|array $handler): self
    {
        $this->routes['POST'][trim($path, '/')] = $handler;
        self::$allRoutes['POST'][trim($path, '/')] = $handler;
        return $this;
    }

    public function put(string $path, callable|array $handler): self
    {
        $this->routes['PUT'][trim($path, '/')] = $handler;
        self::$allRoutes['PUT'][trim($path, '/')] = $handler;
        return $this;
    }

    public function delete(string $path, callable|array $handler): self
    {
        $this->routes['DELETE'][trim($path, '/')] = $handler;
        self::$allRoutes['DELETE'][trim($path, '/')] = $handler;
        return $this;
    }

    public function dispatch(string $uri, string $method): mixed
    {
        $uri = trim(parse_url($uri, PHP_URL_PATH), '/');
        $handler = $this->routes[$method][$uri] ?? null;

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

    public static function getAllRoutes(): array
    {
        return self::$allRoutes;
    }

    public function clearRoutes(): void
    {
        $this->routes = [];
        self::$allRoutes = [];
    }
}
