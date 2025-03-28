<?php

namespace Light\Server;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
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
        $this->stub = require($file);
        $this->middleware = new MiddlewarePipe();
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


        if (!$ref_obj->hasMethod($method)) {
            return new EmptyResponse(405); // 或者返回一個自定義的錯誤響應
        }


        if ($ref_obj->hasMethod($method)) {
            $middle = new MiddlewarePipe();
            $ref_method = $ref_obj->getMethod($method);

            foreach ($ref_method->getAttributes() as $attribute) {
                $middle->pipe($attribute->newInstance());
            }

            $handler = new MethodMiddleware($this->stub, $ref_method, $this->container);

            $middle->pipe($handler);

            return $middle->handle($request);
        }

        return new EmptyResponse(200);
    }
}
