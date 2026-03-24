.DEFAULT_GOAL := help
.PHONY: help bash build up down logs cache-clear migrate \
        db-create db-drop db-wipe db-reset db-seed db-fresh db-dump db-restore \
        run-phpstan run-cs fix-cs run-tests coverage check-code fix-and-check-code \
        generate-openapi

EXEC = docker compose exec app

## —— Docker ——————————————————————————————————————
bash:           ## Connect to PHP container
	$(EXEC) bash

build:          ## Rebuild Docker images
	docker compose build

up:             ## Start containers
	docker compose up -d

down:           ## Stop containers
	docker compose down

logs:           ## View container logs
	docker compose logs -f

## —— Symfony ——————————————————————————————————————
cache-clear:    ## Clear Symfony cache
	$(EXEC) php bin/console cache:clear

migrate:        ## Run database migrations
	$(EXEC) php bin/console doctrine:migrations:migrate --no-interaction

## —— Database —————————————————————————————————————
db-create:      ## Create database
	$(EXEC) php bin/console doctrine:database:create --if-not-exists

db-drop:        ## Drop database
	$(EXEC) php bin/console doctrine:database:drop --force --if-exists

db-wipe:        ## Drop all tables (keeps database)
	$(EXEC) php bin/console doctrine:schema:drop --force --full-database

db-reset:       ## Wipe + migrate
db-reset: db-wipe migrate

db-seed:        ## Load fixtures (10k users + tasks)
	$(EXEC) php -d memory_limit=512M bin/console foundry:load-fixtures --append --no-debug --no-interaction

db-fresh:       ## Reset + seed (clean DB with test data)
db-fresh: db-reset db-seed

db-dump:        ## Save DB dump (name=mybackup)
	docker compose exec db pg_dump -U todo_app -Fc todo_app > $(or $(name),dump).dump
	@echo "Dump saved: $(or $(name),dump).dump"

db-restore:     ## Restore from dump (name=mybackup)
	docker compose exec -T db pg_restore -U todo_app -d todo_app --clean --if-exists < $(or $(name),dump).dump
	@echo "Database restored from $(or $(name),dump).dump"

## —— Code Quality —————————————————————————————————
run-phpstan:    ## Run PHPStan static analysis
	$(EXEC) vendor/bin/phpstan analyse

run-cs:         ## Check code style
	$(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

fix-cs:         ## Fix code style
	$(EXEC) vendor/bin/php-cs-fixer fix

run-tests:      ## Run tests
	$(EXEC) vendor/bin/phpunit

coverage:       ## Generate coverage report
	$(EXEC) env XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage
	@echo "Coverage report: var/coverage/index.html"

check-code:     ## Run cs + phpstan + tests
check-code: run-cs run-phpstan run-tests

fix-and-check-code: ## Fix cs, then phpstan + tests
fix-and-check-code: fix-cs run-phpstan run-tests

## —— Docs —————————————————————————————————————————
generate-openapi: ## Generate OpenAPI spec
	$(EXEC) php bin/console nelmio:apidoc:dump --format=json > docs/openapi.json
	@echo "OpenAPI spec: docs/openapi.json"

## —— Help —————————————————————————————————————————
help:           ## Show this help
	@grep -E '(^[a-zA-Z_-]+:.*##|^## ——)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*##"}; /^## ——/{print "\n" $$0; next} {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'