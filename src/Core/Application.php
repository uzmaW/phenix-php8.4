<?php

namespace Phoenix\Core;

final class Application
{
    private Container $container;
    private bool $running = false;

    public function __construct()
    {
        $this->container = new Container();
        ServiceLocator::set($this->container);
    }

    public function bootstrap(): void
    {
        $env = getenv('APP_ENV') ?: 'development';
        $this->container->set('app.env', fn() => $env);
        $this->container->set('app.debug', fn() => $env !== 'production');
        $this->container->set('app.name', fn() => 'Phoenix Framework');
        $this->container->set('app.version', fn() => '2.0.0');
    }

    public function run(): void
    {
        $this->running = true;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function isProduction(): bool
    {
        return $this->container->get('app.env') === 'production';
    }

    public function isDebug(): bool
    {
        return $this->container->get('app.debug');
    }

    public function container(): Container
    {
        return $this->container;
    }
}
