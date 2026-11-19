<?php
namespace App\Core;

/**
 * Roteador: liga uma URL a um método de um Controller.
 * Os parâmetros {id} (número) e {file} (nome de arquivo) viram argumentos.
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, string $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, string $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, string $handler, array $middleware): void
    {
        $regex = preg_replace(
            ['#\{id\}#', '#\{file\}#'],
            ['(\d+)', '([A-Za-z0-9._-]+)'],
            $pattern
        );
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler, $middleware];
    }

    public function dispatch(string $method, string $path): void
    {
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        foreach ($this->routes as [$m, $regex, $handler, $middleware]) {
            if ($m !== $method || !preg_match($regex, $path, $matches)) {
                continue;
            }
            array_shift($matches);

            if ($method === 'POST' && !csrf_verify()) {
                flash('err', 'Sua sessão expirou. Tente de novo.');
                redirect_back('/login');
            }
            foreach ($middleware as $name) {
                Middleware::run($name);
            }

            [$class, $action] = explode('@', $handler);
            $fqcn = 'App\\Controllers\\' . $class;
            (new $fqcn())->$action(...$matches);
            return;
        }
        $this->notFound();
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', ['title' => 'Página não encontrada'], Auth::check() ? (Auth::isAdmin() ? 'admin' : 'app') : 'guest');
    }
}
