<?php

declare(strict_types=1);

namespace Light\Server;

use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization'],
        private bool $allowCredentials = false,
        private int $maxAge = 86400,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        $isPreflight = $request->getMethod() === 'OPTIONS'
            && $request->getHeaderLine('Origin') !== ''
            && $request->getHeaderLine('Access-Control-Request-Method') !== '';

        if ($isPreflight) {
            return $this->addCorsHeaders(new EmptyResponse(204), $origin);
        }

        return $this->addCorsHeaders($handler->handle($request), $origin);
    }

    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        $allowedOrigin = $this->resolveOrigin($origin);

        if ($allowedOrigin === '') {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function resolveOrigin(string $origin): string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        return in_array($origin, $this->allowedOrigins, true) ? $origin : '';
    }
}
