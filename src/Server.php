<?php

namespace Light;

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\HttpHandlerRunner\RequestHandlerRunnerInterface;
use League\Route\Router;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Server implements RequestHandlerRunnerInterface
{
    private const HTTP_METHODS = ["GET", "POST", "PATCH", "PUT", "DELETE"];

    private $container;
    private $router;
    private $request;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;

        $this->request = ServerRequestFactory::fromGlobals();

        //router setup
        $root = $this->getRootPath();
        $base = $this->getBasePath();
        $router = new Router();

        $router->addPatternMatcher("any", ".+");
        $page_path = $root . "/pages";

        //get all files in the directory, including subdirectories

        $files = $this->scanFiles($page_path);

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue; // Skip non-PHP files
            }

            $path = $file->getPathname();
            $relative_path = substr($path, strlen($page_path));
            $relative_path = str_replace(DIRECTORY_SEPARATOR, "/", $relative_path);

            foreach (self::HTTP_METHODS as $method) {
                $routePath = $base . rtrim(str_replace(".php", "", $relative_path), "/");

                if ($file->getBasename() === "index.php") {
                    $routePath = rtrim(str_replace("/index", "", $routePath), "/") . "/";
                }

                $router->map($method, $routePath, function (ServerRequestInterface $request, array $args) use ($file) {
                    return (new Server\RequestHandler($file, $this->container))->handle($request);
                });
            }
        }

        $this->router = $router;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }


    private function scanFiles(string $path): \Generator
    {
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

    public function getRouter()
    {
        return $this->router;
    }

    public $middleware = [];
    public function pipe(MiddlewareInterface $middleware)
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


    public function getRootPath()
    {
        $server = $this->request->getServerParams();
        if (!$server['SCRIPT_NAME']) {
            return getcwd();
        }
        return dirname($server['SCRIPT_FILENAME']);
    }

    public function getBasePath()
    {
        $server = $this->request->getServerParams();
        $base = $server['SCRIPT_NAME'];
        if (!$base) {
            return "/";
        }
        return  str_replace("\\", "/", dirname($base));
    }
}
