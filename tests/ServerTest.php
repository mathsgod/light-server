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

        $request = $this->createStub(ServerRequestInterface::class);
        $handler = $this->createStub(RequestHandlerInterface::class);
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

    public function testServerRoutesHeadRequests(): void
    {
        $root = sys_get_temp_dir() . '/light-server-' . bin2hex(random_bytes(8));
        $pages = $root . '/pages';
        mkdir($pages, 0777, true);
        file_put_contents($root . '/index.php', '<?php');
        file_put_contents($pages . '/index.php', <<<'PHP'
<?php
return new class {
    public function GET(): \Psr\Http\Message\ResponseInterface
    {
        return new \Laminas\Diactoros\Response\TextResponse('hello');
    }
};
PHP
        );

        $serverParams = $_SERVER;
        $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        try {
            $server = new \Light\Server();
            $request = new \Laminas\Diactoros\ServerRequest([], [], '/', 'HEAD');
            $response = $server->getRouter()->dispatch($request);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('', (string) $response->getBody());

            $optionsResponse = $server->getRouter()->dispatch(
                new \Laminas\Diactoros\ServerRequest([], [], '/', 'OPTIONS')
            );
            $this->assertEquals(204, $optionsResponse->getStatusCode());
        } finally {
            $_SERVER = $serverParams;
            unlink($pages . '/index.php');
            unlink($root . '/index.php');
            rmdir($pages);
            rmdir($root);
        }
    }

    public function testServerFindsPagesAtProjectRootForPublicEntryPoint(): void
    {
        $root = sys_get_temp_dir() . '/light-server-' . bin2hex(random_bytes(8));
        $public = $root . '/public';
        $pages = $root . '/pages';
        mkdir($public, 0777, true);
        mkdir($pages, 0777, true);
        file_put_contents($public . '/index.php', '<?php');
        file_put_contents($pages . '/index.php', <<<'PHP'
<?php
return new class {
    public function GET(): \Psr\Http\Message\ResponseInterface
    {
        return new \Laminas\Diactoros\Response\TextResponse('project pages');
    }
};
PHP
        );

        $serverParams = $_SERVER;
        $_SERVER['SCRIPT_FILENAME'] = $public . '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        try {
            $server = new \Light\Server();
            $request = new \Laminas\Diactoros\ServerRequest([], [], '/', 'GET');
            $response = $server->getRouter()->dispatch($request);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('project pages', (string) $response->getBody());
        } finally {
            $_SERVER = $serverParams;
            unlink($pages . '/index.php');
            unlink($public . '/index.php');
            rmdir($pages);
            rmdir($public);
            rmdir($root);
        }
    }

    public function testServerMatchesPageFileWithAndWithoutTrailingSlash(): void
    {
        $root = sys_get_temp_dir() . '/light-server-' . bin2hex(random_bytes(8));
        $pages = $root . '/pages';
        mkdir($pages, 0777, true);
        file_put_contents($root . '/index.php', '<?php');
        file_put_contents($pages . '/foo.php', <<<'PHP'
<?php
return new class {
    public function GET(): \Psr\Http\Message\ResponseInterface
    {
        return new \Laminas\Diactoros\Response\TextResponse('foo');
    }
};
PHP
        );

        $serverParams = $_SERVER;
        $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        try {
            $server = new \Light\Server();

            foreach (['/foo', '/foo/'] as $path) {
                $response = $server->getRouter()->dispatch(new \Laminas\Diactoros\ServerRequest([], [], $path, 'GET'));
                $this->assertSame(200, $response->getStatusCode());
                $this->assertSame('foo', (string) $response->getBody());
            }
        } finally {
            $_SERVER = $serverParams;
            unlink($pages . '/foo.php');
            unlink($root . '/index.php');
            rmdir($pages);
            rmdir($root);
        }
    }

    public function testServerMatchesDirectoryIndexWithAndWithoutTrailingSlash(): void
    {
        $root = sys_get_temp_dir() . '/light-server-' . bin2hex(random_bytes(8));
        $pages = $root . '/pages';
        mkdir($pages . '/foo', 0777, true);
        file_put_contents($root . '/index.php', '<?php');
        file_put_contents($pages . '/foo/index.php', <<<'PHP'
<?php
return new class {
    public function GET(): \Psr\Http\Message\ResponseInterface
    {
        return new \Laminas\Diactoros\Response\TextResponse('foo index');
    }
};
PHP
        );

        $serverParams = $_SERVER;
        $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        try {
            $server = new \Light\Server();

            foreach (['/foo', '/foo/'] as $path) {
                $response = $server->getRouter()->dispatch(new \Laminas\Diactoros\ServerRequest([], [], $path, 'GET'));
                $this->assertSame(200, $response->getStatusCode());
                $this->assertSame('foo index', (string) $response->getBody());
            }
        } finally {
            $_SERVER = $serverParams;
            unlink($pages . '/foo/index.php');
            unlink($root . '/index.php');
            rmdir($pages . '/foo');
            rmdir($pages);
            rmdir($root);
        }
    }

    public function testServerRejectsFileAndIndexRouteCollision(): void
    {
        $root = sys_get_temp_dir() . '/light-server-' . bin2hex(random_bytes(8));
        $pages = $root . '/pages';
        mkdir($pages . '/foo', 0777, true);
        file_put_contents($root . '/index.php', '<?php');
        file_put_contents($pages . '/foo.php', '<?php return new stdClass();');
        file_put_contents($pages . '/foo/index.php', '<?php return new stdClass();');

        $serverParams = $_SERVER;
        $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        try {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('Route collision for "/foo"');
            new \Light\Server();
        } finally {
            $_SERVER = $serverParams;
            unlink($pages . '/foo/index.php');
            unlink($pages . '/foo.php');
            unlink($root . '/index.php');
            rmdir($pages . '/foo');
            rmdir($pages);
            rmdir($root);
        }
    }
}
