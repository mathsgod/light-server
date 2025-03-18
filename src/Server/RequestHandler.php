<?php

namespace Light\Server;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use ReflectionObject;
use \Psr\Http\Server\MiddlewareInterface;
use ReflectionNamedType;

class RequestHandler implements MiddlewareInterface
{
    private $stub;
    public $middleware;
    private $container;

    function __construct(string $file, ?ContainerInterface $container)
    {
        $this->container = $container;

        ob_start();
        $this->stub = require($file);
        ob_end_clean();

        $this->middleware = new MiddlewarePipe();

        foreach ($this->stub->middleware ?? [] as $middleware) {
            $file = getcwd() . DIRECTORY_SEPARATOR . "middleware" . DIRECTORY_SEPARATOR . $middleware . ".php";
            if (file_exists($file)) {
                $middleware = require($file);
                if ($middleware instanceof MiddlewareInterface) {
                    $this->middleware->pipe($middleware);
                }
            }
        }
    }

    function handle(ServerRequestInterface $request): ResponseInterface
    {


        $this->middleware->pipe($this);
        return $this->middleware->handle($request);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $method = $request->getMethod();
        $ref_obj = new ReflectionObject($this->stub);

        if ($ref_obj->hasMethod($method)) {
            $fallback_handler = new class($this, $this->stub, $method, null) implements RequestHandlerInterface
            {
                private $php;
                private $object;
                private $ref_method;
                private $container;

                public function __construct(RequestHandler $php, $object, string $method, ?ContainerInterface $container)
                {
                    $this->php = $php;
                    $this->object = $object;
                    $this->ref_method = new ReflectionMethod($this->object, $method);
                    $this->container = $container;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {


                    $args = [];

                    foreach ($this->ref_method->getParameters() as $param) {

                        /*       foreach ($param->getAttributes() as $attribute) {
                            if ($handler = $this->app->getParameterHandler($attribute->getName())) {
                                $args[] = $handler->handle($request, $attribute, $param);
                            } else {
                                $args[] = null;
                            }

                            continue 2;
                        } */

                        if ($type = $param->getType()) {

                            if ($type->getName() == ServerRequestInterface::class) {
                                $args[] = $request;
                                continue;
                            }

                            if (assert($type instanceof ReflectionNamedType) && $this->container->has($type->getName())) {
                                $args[] = $this->container->get($type->getName());
                            } else {
                                $args[] = null;
                            }
                        } else {
                            $args[] = null;
                        }
                    }

                    ob_start();
                    $ret = $this->ref_method->invoke($this->object, ...$args);
                    ob_get_contents();
                    ob_end_clean();

                    if ($ret instanceof ResponseInterface) {
                        return $ret;
                    }

                    return new EmptyResponse(200);
                }
            };



            return $fallback_handler->handle($request);
        }

        return new EmptyResponse(200);
    }
}
