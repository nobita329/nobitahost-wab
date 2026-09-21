<?php
/**
 * Tiny router with `{param}` placeholders, middleware and grouped prefixes.
 * Handler signature: function (Request $req, array $params)
 */
final class Router
{
    /** @var array<string, array<int, array{pattern:string, regex:string, keys:array<int,string>, handler:callable, mw:array}>> */
    private array $routes = [];
    private array $groupMw = [];
    private string $prefix = '';

    /**
     * Routes are declared Express-style; middleware and handler can be passed in any order:
     *   $r->get('/x', fn() => ...);
     *   $r->post('/x', [Auth::class, 'csrf'], fn() => ...);
     *   $r->get('/x', fn() => ..., [[Auth::class, 'requireAdmin']]);
     */
    public function get(string $path, mixed ...$args): void    { $this->add('GET', $path, $args); }
    public function post(string $path, mixed ...$args): void   { $this->add('POST', $path, $args); }
    public function put(string $path, mixed ...$args): void    { $this->add('PUT', $path, $args); }
    public function patch(string $path, mixed ...$args): void  { $this->add('PATCH', $path, $args); }
    public function delete(string $path, mixed ...$args): void { $this->add('DELETE', $path, $args); }
    public function any(string $path, mixed ...$args): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $m) $this->add($m, $path, $args);
    }

    public function group(string $prefix, array $mw, callable $fn): void
    {
        $prevPrefix = $this->prefix;
        $prevMw = $this->groupMw;
        $this->prefix = $prevPrefix . $prefix;
        $this->groupMw = array_merge($prevMw, $mw);
        $fn($this);
        $this->prefix = $prevPrefix;
        $this->groupMw = $prevMw;
    }

    private function add(string $method, string $path, array $args): void
    {
        $handler = null;
        $mw = [];
        foreach ($args as $a) {
            if ($a instanceof Closure) { $handler = $a; continue; }
            if (is_array($a)) {
                if (count($a) === 2 && isset($a[0], $a[1]) && is_string($a[0]) && is_string($a[1])) {
                    $mw[] = $a;                 // single callable: [Class::class, 'method']
                } else {
                    foreach ($a as $m) { if ($m) $mw[] = $m; }   // list of middleware
                }
                continue;
            }
            if (is_string($a) && function_exists($a)) { $mw[] = $a; }
        }
        if (!$handler) {
            throw new RuntimeException("Route {$method} {$path} is missing a Closure handler");
        }

        $full = $this->prefix . $path;
        if ($full !== '/' && str_ends_with($full, '/')) $full = rtrim($full, '/');
        $keys = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '([^/]+)';
        }, $full);
        $this->routes[$method][] = [
            'pattern' => $full,
            'regex' => '#^' . $regex . '$#',
            'keys' => $keys,
            'handler' => $handler,
            'mw' => array_merge($this->groupMw, $mw),
        ];
    }

    public function dispatch(Request $req): bool
    {
        $method = $req->method;
        $path = $req->path;
        $pathExists = false;

        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['regex'], $path, $m)) continue;
            $pathExists = true;
            array_shift($m);
            $params = [];
            foreach ($route['keys'] as $i => $key) {
                $params[$key] = isset($m[$i]) ? rawurldecode($m[$i]) : null;
            }
            foreach ($route['mw'] as $guard) {
                $ok = $guard($req, $params);
                if ($ok === false) return true; // guard already responded (redirect / json)
            }
            ($route['handler'])($req, $params);
            return true;
        }

        if (!$pathExists) {
            foreach ($this->routes as $m2 => $list) {
                if ($m2 === $method) continue;
                foreach ($list as $route) {
                    if (preg_match($route['regex'], $path)) {
                        Response::json(['success' => false, 'error' => 'Method Not Allowed'], 405);
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
