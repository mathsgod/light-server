<?php

namespace Light\Tests;

use PHPUnit\Framework\TestCase;
use Light\Server\RequestHandler;
use Light\Server\MethodMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Unit tests for Light Server components
 */
class ServerTest extends TestCase
{
    /**
     * Test that MethodMiddleware can be instantiated with valid parameters
     */
    public function testMethodMiddlewareInstantiation(): void
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

    /**
     * Test that MethodMiddleware can process a request
     */
    public function testMethodMiddlewareProcessRequest(): void
    {
        $object = new class {
            public function GET(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['method' => 'GET', 'status' => 'success']);
            }
        };

        $reflectionMethod = new \ReflectionMethod($object, 'GET');
        $middleware = new MethodMiddleware($object, $reflectionMethod, null);

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse(['default' => 'response']));

        $response = $middleware->process($request, $handler);
        
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test MethodMiddleware with multiple HTTP methods
     */
    public function testMethodMiddlewareWithMultipleMethods(): void
    {
        $object = new class {
            public function GET(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['method' => 'GET']);
            }

            public function POST(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['method' => 'POST']);
            }
        };

        $getMethod = new \ReflectionMethod($object, 'GET');
        $getMiddleware = new MethodMiddleware($object, $getMethod, null);

        $postMethod = new \ReflectionMethod($object, 'POST');
        $postMiddleware = new MethodMiddleware($object, $postMethod, null);

        $this->assertInstanceOf(MethodMiddleware::class, $getMiddleware);
        $this->assertInstanceOf(MethodMiddleware::class, $postMiddleware);
    }

    /**
     * Test request handler without container
     */
    public function testRequestHandlerWithoutContainer(): void
    {
        // Create a temporary PHP file that returns an object
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php
return new class {
    public function GET(\Psr\Http\Message\ServerRequestInterface $request) {
        return new \Laminas\Diactoros\Response\JsonResponse(["message" => "ok"]);
    }
};');

        try {
            $handler = new RequestHandler($tempFile, null);
            $this->assertInstanceOf(RequestHandler::class, $handler);
        } finally {
            unlink($tempFile);
        }
    }
}


