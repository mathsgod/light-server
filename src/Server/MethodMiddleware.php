<?php

namespace Light\Server;

use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use ReflectionNamedType;

class MethodMiddleware implements MiddlewareInterface
{
    private object $object;
    private ReflectionMethod $ref_method;
    private ?ContainerInterface $container;

    public function __construct(object $object, ReflectionMethod $ref_method, ?ContainerInterface $container)
    {
        $this->object = $object;
        $this->ref_method = $ref_method;
        $this->container = $container;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $args = [];
        foreach ($this->ref_method->getParameters() as $param) {
            $type = $param->getType();

            if (!$type instanceof ReflectionNamedType) {
                $args[] = null;
                continue;
            }

            if ($type->getName() === ServerRequestInterface::class) {
                $args[] = $request;
                continue;
            }

            $args[] = $this->container?->has($type->getName())
                ? $this->container->get($type->getName())
                : null;
        }

        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        ob_start();
        try {
            $ret = $this->ref_method->invoke($this->object, ...$args);
        } finally {
            $output = ob_get_clean();
            restore_error_handler();
        }

        if ($ret instanceof ResponseInterface) {
            return $ret;
        }

        if (!empty($output)) {
            // Only return captured output if no errors were suppressed
            return new \Laminas\Diactoros\Response\TextResponse($output);
        }

        return new EmptyResponse(200);
    }
}
