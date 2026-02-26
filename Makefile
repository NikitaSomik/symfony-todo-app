EXEC = docker compose exec app

bash:
	$(EXEC) bash

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f

cache-clear:
	$(EXEC) php bin/console cache:clear

migrate:
	$(EXEC) php bin/console doctrine:migrations:migrate --no-interaction

run-phpstan:
	$(EXEC) vendor/bin/phpstan analyse

run-cs:
	$(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

run-tests:
	$(EXEC) vendor/bin/phpunit

coverage:
	$(EXEC) env XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage
	@echo "Coverage report: var/coverage/index.html"

fix-cs:
	$(EXEC) vendor/bin/php-cs-fixer fix

check-code: run-cs run-phpstan run-tests

fix-and-check-code: fix-cs run-phpstan run-tests
