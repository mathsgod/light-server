<?php

namespace Light\Tests\Server;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Light\Server\RequestHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RequestHandlerTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'rh_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    private function writePageFile(string $code): void
    {
        file_put_contents($this->tempFile, "<?php\n" . $code);
    }

    // page file 唔返回 object 應拋 RuntimeException
    public function testThrowsRuntimeExceptionWhenFileDoesNotReturnObject(): void
    {
        $this->writePageFile('return "not an object";');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Page file must return an object/');

        new RequestHandler($this->tempFile, null);
    }

    // 不支援的 HTTP method 應返回 405
    public function testReturns405WhenMethodNotFound(): void
    {
        $this->writePageFile('
return new class {
    public function GET(\Psr\Http\Message\ServerRequestInterface $r): \Psr\Http\Message\ResponseInterface {
        return new \Laminas\Diactoros\Response\JsonResponse([]);
    }
};');

        $handler = new RequestHandler($this->tempFile, null);
        $request = new ServerRequest([], [], '/', 'POST');

        $response = $handler->handle($request);

        $this->assertEquals(405, $response->getStatusCode());
    }

    // GET request 應正常 dispatch 並返回 response
    public function testHandlesGetRequestSuccessfully(): void
    {
        $this->writePageFile('
return new class {
    public function GET(\Psr\Http\Message\ServerRequestInterface $r): \Psr\Http\Message\ResponseInterface {
        return new \Laminas\Diactoros\Response\JsonResponse(["status" => "ok"]);
    }
};');

        $handler = new RequestHandler($this->tempFile, null);
        $request = new ServerRequest([], [], '/', 'GET');

        $response = $handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('"status":"ok"', (string) $response->getBody());
    }

    // echo 輸出應包裝成 TextResponse
    public function testHandlesEchoOutput(): void
    {
        $this->writePageFile('
return new class {
    public function GET(): void {
        echo "hello";
    }
};');

        $handler = new RequestHandler($this->tempFile, null);
        $request = new ServerRequest([], [], '/', 'GET');

        $response = $handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('hello', (string) $response->getBody());
    }

    // 冇返回值應得 EmptyResponse 200
    public function testHandlesVoidMethodReturnsEmpty200(): void
    {
        $this->writePageFile('
return new class {
    public function GET(): void {}
};');

        $handler = new RequestHandler($this->tempFile, null);
        $request = new ServerRequest([], [], '/', 'GET');

        $response = $handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', (string) $response->getBody());
    }
}
