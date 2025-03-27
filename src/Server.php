<?php

namespace Light;

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Route\Router;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Server
{
    private $container;
    private $root;
    private $base;
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    private function getRouter()
    {
        $router = new Router();

        $router->addPatternMatcher("any", ".+");
        $base_path = $this->root . "/pages";

        //get all files in the directory, including subdirectories

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base_path)
        );


        $methods = ["GET", "POST", "PATCH", "PUT", "DELETE"];
        foreach ($files as $file) {

            if ($file->isFile()) {

                /** @var \SplFileInfo $file */
                $path = $file->getPathname();

                //get the relative path to the file
                $relative_path = str_replace($base_path, "", $path);


                $f = str_replace("\\", "/", $relative_path);

                foreach ($methods as $method) {

                    if ($file->getBasename() == "index.php") {
                        $p = str_replace("/index.php", "", $f);


                        $router->map($method, $this->base . $p . "/", function (ServerRequestInterface $request, array $args) use ($file) {
                            return (new Server\RequestHandler($file, $this->container))->handle($request);
                        });
                        continue;
                    }

                    $p = str_replace(".php", "", $f);

                    $router->map($method, $this->base . $p, function (ServerRequestInterface $request, array $args) use ($file) {
                        return (new Server\RequestHandler($file, $this->container))->handle($request);
                    });
                }
            }
        }


        return $router;
    }

    public function run()
    {
        $request = ServerRequestFactory::fromGlobals();
        $this->root = $this->getRootPath($request);
        $this->base = $this->getBasePath($request);

        $router = $this->getRouter();
        $response = $router->dispatch($request);
        (new SapiEmitter())->emit($response);
    }


    private function getRootPath(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();
        if (!$server['SCRIPT_NAME']) {
            return getcwd();
        }
        return dirname($server['SCRIPT_FILENAME']);
    }

    private function getBasePath(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();
        $base = $server['SCRIPT_NAME'];
        return  str_replace("\\", "/", dirname($base));
    }
}
