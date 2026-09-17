<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Http\Request;
use App\Core\Http\Response;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][$path] = $handler;
    }

    public function get(string $path, callable $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, callable $handler): void { $this->add('POST', $path, $handler); }

    public function dispatch(Request $request): never
    {
        $handler = $this->routes[$request->method][$request->path] ?? null;
        if (!$handler) Response::json(['error' => 'not_found'], 404);
        $result = $handler($request);
        Response::json(is_array($result) ? $result : ['data' => $result]);
    }
}
