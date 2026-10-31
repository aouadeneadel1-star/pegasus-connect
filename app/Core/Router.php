<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Routes GET et POST avec paramètres nommés ({id}) et groupes partageant un préfixe
 * et des middlewares. Les middlewares sont désignés par leur nom (« auth »,
 * « perm:agents.gerer ») et résolus par l'application.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: array{0: class-string, 1: string}, middleware: list<string>}> */
    private array $routes = [];

    /** @var list<array{prefix: string, middleware: list<string>}> */
    private array $groups = [];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param array{prefix?: string, middleware?: list<string>} $options
     * @param callable(self): void $routes
     */
    public function group(array $options, callable $routes): void
    {
        $this->groups[] = [
            'prefix' => rtrim($options['prefix'] ?? '', '/'),
            'middleware' => $options['middleware'] ?? [],
        ];
        $routes($this);
        array_pop($this->groups);
    }

    /**
     * @return array{handler: array{0: class-string, 1: string}, params: array<string, string>, middleware: list<string>}
     */
    public function match(string $method, string $path): array
    {
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $allowed = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed = true;
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return ['handler' => $route['handler'], 'params' => $params, 'middleware' => $route['middleware']];
        }

        throw $allowed ? HttpException::methodNotAllowed() : HttpException::notFound();
    }

    /**
     * @return list<array{method: string, pattern: string, middleware: list<string>}>
     */
    public function routes(): array
    {
        return array_map(
            static fn (array $route): array => [
                'method' => $route['method'],
                'pattern' => $route['pattern'],
                'middleware' => $route['middleware'],
            ],
            $this->routes,
        );
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $prefix = '';
        $groupMiddleware = [];
        foreach ($this->groups as $group) {
            $prefix .= $group['prefix'];
            array_push($groupMiddleware, ...$group['middleware']);
        }

        $pattern = $prefix . ($path === '/' && $prefix !== '' ? '' : $path);
        $pattern = $pattern === '' ? '/' : $pattern;

        // preg_quote() échappe les accolades : les paramètres apparaissent sous la forme \{id\}.
        $regex = preg_replace_callback(
            '/\\\\\{([a-z][a-zA-Z0-9_]*)\\\\\}/',
            static fn (array $match): string => '(?P<' . $match[1] . '>[^/]+)',
            preg_quote($pattern, '#'),
        );

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => [...$groupMiddleware, ...$middleware],
        ];
    }
}
