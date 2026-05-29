<?php

namespace Light\Tests\Server;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Light\Server\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddlewareTest extends TestCase
{
    private function makeHandler(): RequestHandlerInterface
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse(['ok' => true]));
        return $handler;
    }

    private function makeRequest(string $method, string $origin = 'https://example.com'): ServerRequestInterface
    {
        return (new ServerRequest([], [], '/', $method))
            ->withHeader('Origin', $origin);
    }

    // OPTIONS preflight 應返 204，唔 call handler
    public function testOptionsPreflightReturns204(): void
    {
        $middleware = new CorsMiddleware();
        $response = $middleware->process($this->makeRequest('OPTIONS'), $this->makeHandler());

        $this->assertEquals(204, $response->getStatusCode());
    }

    // OPTIONS preflight 應帶 CORS headers
    public function testOptionsPreflightHasCorsHeaders(): void
    {
        $middleware = new CorsMiddleware();
        $response = $middleware->process($this->makeRequest('OPTIONS'), $this->makeHandler());

        $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Max-Age'));
    }

    // 非 OPTIONS request 應正常 call handler 並加 CORS headers
    public function testNonOptionsRequestPassesThroughWithCorsHeaders(): void
    {
        $middleware = new CorsMiddleware();
        $response = $middleware->process($this->makeRequest('GET'), $this->makeHandler());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    // 指定 origin 列表：符合的 origin 應回傳對應 origin
    public function testAllowedSpecificOriginReturnsOrigin(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://example.com']);
        $response = $middleware->process($this->makeRequest('GET', 'https://example.com'), $this->makeHandler());

        $this->assertEquals('https://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    // 不在允許列表的 origin 唔應加 CORS headers
    public function testDisallowedOriginHasNoCorsHeaders(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://example.com']);
        $response = $middleware->process($this->makeRequest('GET', 'https://evil.com'), $this->makeHandler());

        $this->assertEmpty($response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    // allowCredentials = true 應加 Allow-Credentials header
    public function testAllowCredentialsHeader(): void
    {
        $middleware = new CorsMiddleware(allowCredentials: true);
        $response = $middleware->process($this->makeRequest('GET'), $this->makeHandler());

        $this->assertEquals('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    // allowCredentials = false（預設）唔應有 Allow-Credentials header
    public function testNoCredentialsHeaderByDefault(): void
    {
        $middleware = new CorsMiddleware();
        $response = $middleware->process($this->makeRequest('GET'), $this->makeHandler());

        $this->assertEmpty($response->getHeaderLine('Access-Control-Allow-Credentials'));
    }
}
