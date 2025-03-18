## Light Server

## Installation

```bash
composer require mathsgod/light-server
```

## Usage

```php
(new Light\Server())->run();
```


### Configuration

create a folder `pages` in the root of your project and create a file `index.php` in it.

```php
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