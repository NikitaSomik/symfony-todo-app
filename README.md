# symfony-todo-app

![CI](https://github.com/NikitaSomik/symfony-todo-app/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.0-000000?logo=symfony&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen)

Symfony 8 + Docker

## Services

| Service | Description |
|---------|-------------|
| `app` | PHP 8.4-fpm-alpine + Xdebug |
| `nginx` | nginx:alpine |
| `db` | PostgreSQL 17 |

## Quick Start

```shell
make build   # build Docker images
make up      # start containers
make down    # stop containers
```

## Commands

Run `make` to see all available commands.