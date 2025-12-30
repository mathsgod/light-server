# Light Server

A lightweight PHP web framework with a simple file-based routing convention.

## Features

- 🚀 Lightweight design and fast startup
- 📄 File-system based routing
- 🛠️ PSR-7 standard support
- 💪 Built-in middleware system
- 🔄 Simple HTTP method handling (GET, POST, etc.)

## Installation

Install via Composer:

```bash
composer require mathsgod/light-server
```

## Quick Start

### Basic Setup

1. Create a `pages` folder in your project root
2. Create a `pages/index.php` file

### Starting the Server

```php
<?php

require 'vendor/autoload.php';

(new Light\Server())->run();
```

## Usage

### Simple Example

In `pages/index.php`:

```php
<?php

use Laminas\Diactoros\Response\TextResponse;

return new class() {
    
    public function get()
    {
        return new TextResponse("Hello World");
    }

    public function post()
    {
        return new TextResponse("POST request received");
    }
};
`

### Routing Structure

The page system automatically generates routes based on the file structure:

- `pages/index.php` → `/`
- `pages/about.php` → `/about`
- `pages/blog/index.php` → `/blog`
- `pages/blog/{id}/index.php` → `/blog/{id}` (dynamic routes)

### Handling HTTP Methods

Define corresponding methods in your page class:

```php
public function get() { }      // GET request
public function post() { }     // POST request
public function put() { }      // PUT request
public function delete() { }   // DELETE request
public function patch() { }    // PATCH request
```

## License

See the LICENSE file for details.