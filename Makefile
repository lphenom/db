.PHONY: up down build test test-unit test-integration lint lint-fix phpstan shell composer-install

## Build Docker images
build:
	docker compose build

## Start Docker environment (build if needed)
up:
	docker compose up -d --build
	docker compose exec app composer install --no-interaction --prefer-dist

## Stop Docker environment
down:
	docker compose down

## Run all PHPUnit tests (unit + integration) inside container
test:
	docker compose exec app vendor/bin/phpunit --testdox

## Run unit tests only (no real DB required)
test-unit:
	docker compose exec app vendor/bin/phpunit --testsuite unit --testdox

## Run integration tests (requires MySQL container to be healthy)
test-integration:
	docker compose exec \
		-e DB_HOST=mysql \
		-e DB_PORT=3306 \
		-e DB_NAME=lphenom \
		-e DB_USER=lphenom \
		-e DB_PASSWORD=secret \
		-e SKIP_FFI_TESTS=1 \
		app vendor/bin/phpunit --testsuite integration --testdox

## Run php-cs-fixer check (dry-run) inside container
lint:
	docker compose exec app vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php

## Auto-fix code style inside container
lint-fix:
	docker compose exec app vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php

## Run PHPStan analysis inside container
phpstan:
	docker compose exec app vendor/bin/phpstan analyse --no-progress

## Open shell inside app container
shell:
	docker compose exec app bash

## Install composer dependencies inside container
composer-install:
	docker compose exec app composer install --no-interaction --prefer-dist
