<?php

namespace Phoenix\Middleware;

interface MiddlewareInterface
{
    public function handle(mixed $request, callable $next): mixed;
}
