<?php

namespace Light\Server;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Stream;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionObject;
use \Psr\Http\Server\MiddlewareInterface;

class RequestHandler implements MiddlewareInterface
{
    private object $stub;
    private MiddlewarePipe $middleware;
    private ?ContainerInterface $container;

    public function __construct(string $file, ?ContainerInterface $container)
    {
        $this->container = $container;
        $stub = require($file);
        if (!is_object($stub)) {
            throw new \RuntimeException("Page file must return an object: " . $file);
        }
        $this->stub = $stub;
        $this->middleware = new MiddlewarePipe();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->middleware->pipe($this);
        return $this->middleware->handle($request);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $method = $request->getMethod();
        $isHead = $method === 'HEAD';
        $ref_obj = new ReflectionObject($this->stub);

        // HEAD has the same semantics as GET unless the page provides
        // an explicit HEAD handler.
        $handlerMethod = $isHead && !$ref_obj->hasMethod('HEAD') ? 'GET' : $method;

        if (!$ref_obj->hasMethod($handlerMethod)) {
            return new EmptyResponse(405);
        }

        $middle = new MiddlewarePipe();
        $ref_method = $ref_obj->getMethod($handlerMethod);

        foreach ($ref_method->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof MiddlewareInterface) {
                $middle->pipe($instance);
            }
        }

        $handler = new MethodMiddleware($this->stub, $ref_method, $this->container);

        $middle->pipe($handler);

        $response = $middle->handle($request);

        if ($isHead) {
            return $response->withBody(new Stream('php://temp', 'r+'));
        }

        return $response;
    }
}
