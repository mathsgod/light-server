# Light Server

A lightweight PHP 8.1+ web framework with file-based routing, PSR-7 support, and automatic dependency injection.

## Requirements

- PHP 8.1+

## Features

- 📄 **File-system based routing** — drop a file in `pages/`, get a route automatically
- 🔀 **Dynamic routes** — `pages/blog/{id}/index.php` → `/blog/{id}`
- 🛠️ **PSR-7 / PSR-15 standard** — standard HTTP message and middleware interfaces
- 💉 **Automatic dependency injection** — method parameters resolved from a PSR-11 container
- 🎯 **Attribute-based middleware** — attach PSR-15 middleware directly to handler methods via PHP attributes
- 💪 **Global middleware** — pipe middleware at the server level
- 🔒 **Built-in security headers** — optional `SecurityHeadersMiddleware`

## Installation

```bash
composer require mathsgod/light-server
```

## Quick Start

### 1. Create an entry point

```php
<?php
// public/index.php

require 'vendor/autoload.php';

(new Light\Server())->run();
```

### 2. Create a page handler

```php
<?php
// pages/index.php

use Laminas\Diactoros\Response\TextResponse;

return new class {
    public function get(): TextResponse
    {
        return new TextResponse("Hello, World!");
    }

    public function post(): TextResponse
    {
        return new TextResponse("POST request received");
    }
};
```

## Routing

Routes are generated automatically from the `pages/` directory structure:

| File | Route |
|------|-------|
| `pages/index.php` | `/` |
| `pages/about.php` | `/about` |
| `pages/blog/index.php` | `/blog/` |
| `pages/blog/{id}/index.php` | `/blog/{id}` |

> If the `pages/` directory does not exist, the server starts normally with no routes.

### HTTP Methods

Define public methods matching the HTTP verb (case-insensitive):

```php
return new class {
    public function get(): ResponseInterface { }
    public function post(): ResponseInterface { }
    public function put(): ResponseInterface { }
    public function delete(): ResponseInterface { }
    public function patch(): ResponseInterface { }
};
```

### Dynamic Routes

Route parameters are available via `$request->getAttribute()`:

```php
<?php
// pages/blog/{id}/index.php

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;

return new class {
    public function get(ServerRequestInterface $request): JsonResponse
    {
        $id = $request->getAttribute('id');
        return new JsonResponse(['id' => $id]);
    }
};
```

### Route Constraints

Since routing is powered by [League\Route](https://route.thephpleague.com/), you can constrain route parameters directly in the folder/file name using `{param:type}` syntax:

| Constraint | Pattern | Example match |
|------------|---------|---------------|
| `{id:number}` | `[0-9]+` | `123` |
| `{name:word}` | `[a-zA-Z]+` | `raymond` |
| `{slug:slug}` | `[a-z0-9-]+` | `my-post` |
| `{token:alphanum_dash}` | `[a-zA-Z0-9-_]+` | `abc-123_x` |
| `{id:uuid}` | UUID format | `550e8400-e29b-41d4-a716-446655440000` |
| `{path:any}` | `.+` | `foo/bar/baz` |

**Example:** Only match when `id` is numeric and `name` is alphabetic:

```
pages/
└── user/
    └── {id:number}/
        └── {name:word}/
            └── index.php   →   /user/{id:number}/{name:word}/
```

```
/user/1/raymond/   ✅  matches  (id=1, name=raymond)
/user/test/raymond/ ❌  no match (id is not numeric)
```

```php
<?php
// pages/user/{id:number}/{name:word}/index.php

use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ServerRequestInterface;

return new class {
    public function get(ServerRequestInterface $request): TextResponse
    {
        $id   = $request->getAttribute('id');
        $name = $request->getAttribute('name');
        return new TextResponse("User ID: $id, Name: $name");
    }
};
```

## Dependency Injection

Method parameters are resolved automatically by type hint:

- `ServerRequestInterface` — injects the current HTTP request
- Any other type hint — resolved from the PSR-11 container (if provided)
- Unresolvable parameters — receive `null`

```php
<?php
// pages/users.php

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;

return new class {
    public function get(ServerRequestInterface $request, UserRepository $repo): JsonResponse
    {
        return new JsonResponse($repo->findAll());
    }
};
```

Pass a PSR-11 container when creating the server:

```php
$container = /* your PSR-11 container */;
(new Light\Server($container))->run();
```

## Middleware

### Global Middleware

Use `pipe()` to apply middleware to all routes:

```php
$server = new Light\Server();
$server->pipe(new Light\Server\SecurityHeadersMiddleware());
$server->run();
```

### Attribute-based Middleware (per method)

Attach PSR-15 middleware to a specific handler method using PHP attributes:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[\Attribute]
class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // auth logic ...
        return $handler->handle($request);
    }
}

return new class {
    #[AuthMiddleware]
    public function get(): ResponseInterface { /* ... */ }
};
```

## Built-in Middleware

### CorsMiddleware

Handles CORS preflight (`OPTIONS`) requests and adds CORS headers to all responses.

- `OPTIONS` requests are intercepted **before routing** and return `204 No Content` with CORS headers
- All other requests pass through normally with CORS headers appended

```php
$server = new Light\Server();
$server->pipe(new Light\Server\CorsMiddleware(
    allowedOrigins:   ['https://example.com'],
    allowedMethods:   ['GET', 'POST', 'PUT', 'DELETE'],
    allowedHeaders:   ['Content-Type', 'Authorization'],
    allowCredentials: true,
    maxAge:           3600,
));
$server->run();
```

All constructor parameters are optional:

| Parameter | Default | Description |
|-----------|---------|-------------|
| `allowedOrigins` | `['*']` | Allowed origins. Use `['*']` for wildcard |
| `allowedMethods` | `['GET','POST','PUT','PATCH','DELETE','OPTIONS']` | Allowed HTTP methods |
| `allowedHeaders` | `['Content-Type','Authorization']` | Allowed request headers |
| `allowCredentials` | `false` | Set `true` to send `Access-Control-Allow-Credentials: true` |
| `maxAge` | `86400` | Preflight cache duration in seconds |

> **Note:** When using `allowCredentials: true`, `allowedOrigins` must list specific origins — wildcard `*` does not work with credentials.

### SecurityHeadersMiddleware

Adds common security response headers:

| Header | Value |
|--------|-------|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Content-Security-Policy` | `default-src 'self'` |

```php
$server = new Light\Server();
$server->pipe(new Light\Server\SecurityHeadersMiddleware());
$server->run();
```

## License

MIT — see the LICENSE file for details.