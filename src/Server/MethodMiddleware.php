<?php

namespace Light\Server;

use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionNamedType;

class MethodMiddleware implements MiddlewareInterface
{
    private $object;
    private $ref_method;
    private $container;

    public function __construct($object, $ref_method, ?ContainerInterface $container)
    {
        $this->object = $object;
        $this->ref_method = $ref_method;
        $this->container = $container;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $args = [];
        foreach ($this->ref_method->getParameters() as $param) {
            if ($type = $param->getType()) {
                if ($type->getName() == ServerRequestInterface::class) {
                    $args[] = $request;
                    continue;
                }

                if ($type instanceof ReflectionNamedType && $this->container && $this->container->has($type->getName())) {
                    $args[] = $this->container->get($type->getName());
                } else {
                    $args[] = null;
                }
            } else {
                $args[] = null;
            }
        }

        $previousLevel = error_reporting(0);
        ob_start();
        try {
            $ret = $this->ref_method->invoke($this->object, ...$args);
        } finally {
            $output = ob_get_clean();
            error_reporting($previousLevel);
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
