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
use ReflectionParameter;

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
        $args = array_map(
            fn (ReflectionParameter $param): mixed => $this->resolveParameter($param, $request),
            $this->ref_method->getParameters()
        );

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

    private function resolveParameter(
        ReflectionParameter $param,
        ServerRequestInterface $request
    ): mixed {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType) {
            if ($type->getName() === ServerRequestInterface::class) {
                return $request;
            }

            // Only class/interface dependencies belong in the container.
            if (!$type->isBuiltin()) {
                $typeName = $type->getName();

                if ($this->container?->has($typeName)) {
                    return $this->container->get($typeName);
                }
            }
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
            return null;
        }

        $class = $this->ref_method->getDeclaringClass()?->getName() ?? $this->object::class;

        throw new \RuntimeException(sprintf(
            'Unable to resolve parameter $%s for %s::%s()',
            $param->getName(),
            $class,
            $this->ref_method->getName()
        ));
    }
}
