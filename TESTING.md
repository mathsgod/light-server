# PHPUnit Testing Guide

## Quick Start

### Run all tests
```bash
./vendor/bin/phpunit
```

### Run specific test class
```bash
./vendor/bin/phpunit tests/ServerTest.php
```

### Run specific test method
```bash
./vendor/bin/phpunit --filter testMethodMiddlewareInstantiation
```

### Generate code coverage report
```bash
./vendor/bin/phpunit --coverage-html coverage
```

## Project Structure

```
tests/
├── ServerTest.php              # Server component tests
└── Server/
    └── MethodMiddlewareTest.php # Middleware tests
```

## Best Practices for Writing Tests

### 1. Test naming conventions
- Test class: `{ComponentName}Test`
- Test method: `test{MethodName}{Scenario}`

### 2. Using mock objects
```php
$mockRequest = $this->createMock(ServerRequestInterface::class);
$mockRequest->method('getServerParams')->willReturn([...]);
```

### 3. Assertion examples
```php
$this->assertInstanceOf(ClassName::class, $object);
$this->assertEquals($expected, $actual);
$this->assertTrue($condition);
$this->assertCount(5, $array);
```

## Configuration Files

- `phpunit.xml` - PHPUnit main configuration file, defines test suites and output format

## composer.json Dependencies

The following development dependencies have been added:
- `phpunit/phpunit: ^12` - PHPUnit testing framework

Run `composer install` to install all dependencies.

## Frequently Asked Questions

**Q: How do I exclude certain directories?**
Edit the `<exclude>` tag in `phpunit.xml`.

**Q: How do I improve test execution speed?**
Use the `--cache-result` option or run tests in parallel.

**Q: How do I test private methods?**
Use reflection or refactor the code to make it testable.
