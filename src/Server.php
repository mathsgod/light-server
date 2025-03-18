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

        $base_path = getcwd() . "/pages";


        //get all files in the directory, including subdirectories

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base_path)
        );

        //$methods = ["GET", "POST", "PATCH", "PUT", "DELETE"];
        $methods = ["GET"];
        foreach ($files as $key => $file) {
            if ($file->isFile()) {

                /** @var \SplFileInfo $file */
                $path = $file->getPathname();

                //get the relative path to the file
                $relative_path = str_replace($base_path, "", $path);


                $f = str_replace("\\", "/", $relative_path);

                foreach ($methods as $method) {


                    if ($file->getBasename() == "index.php") {
                        $p = str_replace("/index.php", "", $f);
                        $router->map($method, $p, function (ServerRequestInterface $request, array $args) use ($file) {
                            return (new Server\RequestHandler($file, $this->container))->handle($request);
                        });
                        continue;
                    }

                    $p = str_replace(".php", "", $f);

                    $router->map($method, $p, function (ServerRequestInterface $request, array $args) use ($file) {
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
        $router = $this->getRouter();
        $response = $router->dispatch($request);
        (new SapiEmitter())->emit($response);
    }
}
