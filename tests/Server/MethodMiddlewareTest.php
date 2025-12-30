<?php

namespace Light\Tests\Server;

use PHPUnit\Framework\TestCase;
use Light\Server\MethodMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\JsonResponse;

class MethodMiddlewareTest extends TestCase
{
    public function testMethodMiddlewareCanBeInstantiated(): void
    {
        $object = new class {
            public function GET(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['message' => 'ok']);
            }
        };

        $reflectionMethod = new \ReflectionMethod($object, 'GET');
        $middleware = new MethodMiddleware($object, $reflectionMethod, null);

        $this->assertInstanceOf(MethodMiddleware::class, $middleware);
    }

    public function testMethodMiddlewareProcessesRequest(): void
    {
        $object = new class {
            public function GET(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['method' => 'GET']);
            }
        };

        $reflectionMethod = new \ReflectionMethod($object, 'GET');
        $middleware = new MethodMiddleware($object, $reflectionMethod, null);

        // Create test stub for request
        $request = $this->createStub(ServerRequestInterface::class);
        
        // Create test stub for handler
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse(['default' => 'response']));

        $response = $middleware->process($request, $handler);
        
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
