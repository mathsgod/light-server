<?php

namespace Light\Tests\Server;

use Laminas\Diactoros\Response\JsonResponse;
use Light\Server\MethodMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DummyService
{
    public string $value = 'injected';
}

class MethodMiddlewareTest extends TestCase
{
    private ServerRequestInterface $request;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        $this->request = $this->createStub(ServerRequestInterface::class);
        $this->handler = $this->createStub(RequestHandlerInterface::class);
    }

    public function testMethodMiddlewareCanBeInstantiated(): void
    {
        $object = new class {
            public function GET(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['message' => 'ok']);
            }
        };

        $middleware = new MethodMiddleware($object, new \ReflectionMethod($object, 'GET'), null);
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

        $middleware = new MethodMiddleware($object, new \ReflectionMethod($object, 'GET'), null);
        $response = $middleware->process($this->request, $this->handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // Bug 3 fix: union type 參數唔應 crash，應收到 null
    public function testUnionTypeParameterReceivesNull(): void
    {
        $object = new class {
            public string|int|null $received = 'untouched';

            public function GET(string|int|null $id = null): ResponseInterface
            {
                $this->received = $id;
                return new JsonResponse([]);
            }
        };

        $middleware = new MethodMiddleware($object, new \ReflectionMethod($object, 'GET'), null);
        $response = $middleware->process($this->request, $this->handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($object->received);
    }

    // ServerRequestInterface 參數應自動注入 $request
    public function testInjectsServerRequestInterfaceParameter(): void
    {
        $object = new class {
            public ?ServerRequestInterface $received = null;

            public function GET(ServerRequestInterface $request): ResponseInterface
            {
                $this->received = $request;
                return new JsonResponse([]);
            }
        };

        $middleware = new MethodMiddleware($object, new \ReflectionMethod($object, 'GET'), null);
        $middleware->process($this->request, $this->handler);

        $this->assertSame($this->request, $object->received);
    }

    // Container 應根據 type hint 注入依賴
    public function testContainerInjectsTypedDependency(): void
    {
        $service = new DummyService();

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn($id) => $id === DummyService::class);
        $container->method('get')->willReturn($service);

        $object = new class {
            public ?DummyService $received = null;

            public function GET(DummyService $svc): ResponseInterface
            {
                $this->received = $svc;
                return new JsonResponse([]);
            }
        };

        $middleware = new MethodMiddleware($object, new \ReflectionMethod($object, 'GET'), $container);
        $middleware->process($this->request, $this->handler);

        $this->assertSame($service, $object->received);
    }

    // echo 輸出應包裝成 TextResponse
    public function testEchoOutputReturnsTextResponse(): void
    {
        $object = new class {
            public function GET(): void
            {
                echo 'hello from echo';
            }
        };

        $middleware = new MethodMiddleware($object, new \ReflectionMethod($object, 'GET'), null);
        $response = $middleware->process($this->request, $this->handler);

        $this->assertInstanceOf(\Laminas\Diactoros\Response\TextResponse::class, $response);
        $this->assertEquals('hello from echo', (string) $response->getBody());
    }

    // 冇返回值應得 EmptyResponse 200
    public function testNoReturnValueReturnsEmptyResponse200(): void
    {
        $object = new class {
            public function GET(): void {}
        };

        $middleware = new MethodMiddleware($object, new \ReflectionMethod($object, 'GET'), null);
        $response = $middleware->process($this->request, $this->handler);

        $this->assertInstanceOf(\Laminas\Diactoros\Response\EmptyResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // Bug 5 fix: PHP warning 應拋 ErrorException，唔係靜音
    public function testPhpWarningThrowsErrorException(): void
    {
        $object = new class {
            public function GET(): ResponseInterface
            {
                trigger_error('test warning', E_USER_WARNING);
                return new JsonResponse([]);
            }
        };

        $middleware = new MethodMiddleware($object, new \ReflectionMethod($object, 'GET'), null);

        $this->expectException(\ErrorException::class);
        $middleware->process($this->request, $this->handler);
    }
}
