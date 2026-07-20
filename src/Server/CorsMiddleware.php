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
    ) {
        if ($this->allowCredentials && in_array('*', $this->allowedOrigins, true)) {
            throw new \InvalidArgumentException(
                'allowCredentials requires explicit allowedOrigins; wildcard origins are not allowed'
            );
        }
    }

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
        if ($origin !== '' && !in_array('*', $this->allowedOrigins, true)) {
            $response = $this->addVaryOrigin($response);
        }

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

    private function addVaryOrigin(ResponseInterface $response): ResponseInterface
    {
        $vary = trim($response->getHeaderLine('Vary'));

        if ($vary === '*') {
            return $response;
        }

        $varyValues = $vary === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $vary))));

        $normalizedValues = array_map('strtolower', $varyValues);
        if (!in_array('origin', $normalizedValues, true)) {
            $varyValues[] = 'Origin';
        }

        return $response->withHeader('Vary', implode(', ', $varyValues));
    }

    private function resolveOrigin(string $origin): string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        return in_array($origin, $this->allowedOrigins, true) ? $origin : '';
    }
}
