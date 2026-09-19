# symfony-todo-app

![CI](https://github.com/NikitaSomik/symfony-todo-app/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.0-000000?logo=symfony&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen)

Modular Symfony 8 API with Docker, PostgreSQL 17, JWT authentication, integration tests, and OpenAPI documentation.

## Stack

- PHP 8.5
- Symfony 8
- PostgreSQL 17
- Redis
- PHPUnit
- PHPStan
- Docker Compose

## Quick Start

```sh
make build
make up
make migrate
```

## Daily Commands

```sh
make cache-clear
make migrate
make run-cs
make run-phpstan
make run-tests
make check-code
```

## Database Reset

```sh
make db-reset
```

Drops all tables and recreates the schema from migrations.

## API Docs

```sh
make generate-openapi
```

## Full Command List

```sh
make
```
