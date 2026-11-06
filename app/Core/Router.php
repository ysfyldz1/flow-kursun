<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler, array $options = []): void
    {
        $this->add('GET', $path, $handler, $options);
    }

    public function post(string $path, array|callable $handler, array $options = []): void
    {
        $this->add('POST', $path, $handler, $options);
    }

    private function add(string $method, string $path, array|callable $handler, array $options): void
    {
        // {id} gibi parametreleri regex'e çevir
        $pattern = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/') . '$#';
        $this->routes[] = compact('method', 'pattern', 'handler', 'options');
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || !preg_match($route['pattern'], $path, $m)) {
                continue;
            }

            $opts = $route['options'];
            if (($opts['auth'] ?? true) && !Auth::check()) {
                redirect('/login');
            }
            if (!empty($opts['guest']) && Auth::check()) {
                redirect('/');
            }
            if (!empty($opts['can']) && !Auth::can($opts['can'])) {
                abort(403);
            }
            if ($method === 'POST') {
                Csrf::verify();
            }

            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                (new $class())->$action(...$params);
            } else {
                $handler(...$params);
            }
            return;
        }

        abort(404);
    }
}
