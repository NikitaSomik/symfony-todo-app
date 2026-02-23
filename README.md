# symfony-todo-app

Symfony 8 + Docker

## Services

| Service | Description |
|---------|-------------|
| `app` | PHP 8.4-fpm-alpine + Xdebug |
| `nginx` | nginx:alpine |
| `db` | PostgreSQL 17 |

## Commands

### Docker
```shell
make up       # start containers
make down     # stop containers
make build    # rebuild images
make bash     # connect to php container
make logs     # view logs
```

### Symfony
```shell
make migrate      # run migrations
make cache-clear  # clear cache
```

### Code Quality
```shell
make check-code          # run cs + phpstan + tests
make fix-and-check-code  # fix cs, then run phpstan + tests
make run-cs              # check code style
make fix-cs              # fix code style
make run-phpstan         # static analysis
make run-tests           # run tests
```