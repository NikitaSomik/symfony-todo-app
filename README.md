# symfony-todo-app

Symfony 8 + Docker

## Services

| Service | Description |
|---------|-------------|
| `app` | PHP 8.4-fpm-alpine + Composer + Xdebug |
| `nginx` | nginx:alpine |
| `db` | PostgreSQL 17 |

## Commands

```shell
make up       # start containers
make down     # stop containers
make build    # rebuild images
make bash     # connect to php container
make logs     # view logs
```