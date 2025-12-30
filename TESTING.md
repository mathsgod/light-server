# PHPUnit 测试指南

## 快速开始

### 运行所有测试
```bash
./vendor/bin/phpunit
```

### 运行特定测试类
```bash
./vendor/bin/phpunit tests/ServerTest.php
```

### 运行特定测试方法
```bash
./vendor/bin/phpunit --filter testMethodMiddlewareInstantiation
```

### 生成代码覆盖报告
```bash
./vendor/bin/phpunit --coverage-html coverage
```

## 项目结构

```
tests/
├── ServerTest.php              # Server 组件测试
└── Server/
    └── MethodMiddlewareTest.php # 中间件测试
```

## 写测试的最佳实践

### 1. 测试命名约定
- 测试类：`{ComponentName}Test`
- 测试方法：`test{MethodName}{Scenario}`

### 2. 使用模拟对象
```php
$mockRequest = $this->createMock(ServerRequestInterface::class);
$mockRequest->method('getServerParams')->willReturn([...]);
```

### 3. 断言示例
```php
$this->assertInstanceOf(ClassName::class, $object);
$this->assertEquals($expected, $actual);
$this->assertTrue($condition);
$this->assertCount(5, $array);
```

## 配置文件

- `phpunit.xml` - PHPUnit 主配置文件，定义了测试套件和输出格式

## composer.json 依赖

已添加以下开发依赖：
- `phpunit/phpunit: ^12` - PHPUnit 测试框架

运行 `composer install` 来安装所有依赖。

## 常见问题

**Q: 如何排除某些目录？**
编辑 `phpunit.xml` 的 `<exclude>` 标签。

**Q: 如何提高测试速度？**
使用 `--cache-result` 选项或并行运行测试。

**Q: 如何测试私有方法？**
使用反射或重构代码使其可测试。
