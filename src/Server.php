<?php

namespace Light;

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\HttpHandlerRunner\RequestHandlerRunnerInterface;
use League\Route\Router;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
class Server implements RequestHandlerRunnerInterface
{
    private const HTTP_METHODS = ["GET", "HEAD", "POST", "PATCH", "PUT", "DELETE", "OPTIONS"];

    private ?ContainerInterface $container;
    private Router $router;
    private ServerRequestInterface $request;
    /** @var array<string, string> canonical route path => source page file */
    private array $routeFiles = [];

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;

        $this->request = ServerRequestFactory::fromGlobals();

        //router setup
        $root = $this->getRootPath();
        $base = $this->getBasePath();
        $router = new Router();

        $router->addPatternMatcher("any", ".+");
        $page_path = $this->getPagesPath($root);

        //get all files in the directory, including subdirectories

        foreach ($this->scanFiles($page_path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $routePath = $this->resolveRoutePath($base, $page_path, $file);
            $this->assertRouteIsUnique($routePath, $file);

            foreach ($this->routeAliases($routePath) as $routeAlias) {
                foreach (self::HTTP_METHODS as $method) {
                    $router->map($method, $routeAlias, function (ServerRequestInterface $request, array $args) use ($file) {
                        return (new Server\RequestHandler($file->getPathname(), $this->container))->handle($request);
                    });
                }
            }
        }

        $this->router = $router;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }


    private function resolveRoutePath(string $base, string $pagePath, \SplFileInfo $file): string
    {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($pagePath)));

        if ($file->getBasename() === 'index.php') {
            $relative = dirname($relative);
        } else {
            $relative = preg_replace('/\.php$/', '', $relative) ?? $relative;
        }

        $relative = trim($relative, '/');
        $base = rtrim($base, '/');

        if ($relative === '') {
            return $base === '' ? '/' : $base;
        }

        return $base . '/' . $relative;
    }

    /** @return string[] */
    private function routeAliases(string $routePath): array
    {
        if ($routePath === '/') {
            return ['/'];
        }

        return [$routePath, $routePath . '/'];
    }

    private function assertRouteIsUnique(string $routePath, \SplFileInfo $file): void
    {
        if (isset($this->routeFiles[$routePath])) {
            throw new \LogicException(sprintf(
                'Route collision for "%s": "%s" and "%s" both resolve to the same route.',
                $routePath,
                $this->routeFiles[$routePath],
                $file->getPathname(),
            ));
        }

        $this->routeFiles[$routePath] = $file->getPathname();
    }

    private function scanFiles(string $path): \Generator
    {
        if (!is_dir($path)) {
            return;
        }

        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path)
            );
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to read directory: " . $e->getMessage());
        }

        foreach ($files as $file) {
            if ($file->isFile()) {
                yield $file;
            }
        }
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public array $middleware = [];
    public function pipe(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function run(): void
    {
        foreach ($this->middleware as $middleware) {
            $this->router->middleware($middleware);
        }

        $response = $this->router->dispatch($this->request);

        (new SapiEmitter())->emit($response);
    }


    public function getRootPath(): string
    {
        $server = $this->request->getServerParams();
        $filename = $server['SCRIPT_FILENAME'] ?? null;
        if (!$filename) {
            return getcwd();
        }
        return dirname($filename);
    }

    private function getPagesPath(string $root): string
    {
        $pagesPath = $root . '/pages';

        if (is_dir($pagesPath)) {
            return $pagesPath;
        }

        // Support the conventional project layout where the entry point is
        // public/index.php and pages/ lives at the project root.
        if (basename($root) === 'public') {
            $projectPagesPath = dirname($root) . '/pages';

            if (is_dir($projectPagesPath)) {
                return $projectPagesPath;
            }
        }

        return $pagesPath;
    }

    public function getBasePath(): string
    {
        $server = $this->request->getServerParams();
        $base = $server['SCRIPT_NAME'] ?? null;
        if (!$base) {
            return "";
        }
        $dir = str_replace("\\", "/", dirname($base));
        return $dir === "/" ? "" : $dir;
    }
}
