.PHONY: up down build test lint lint-fix phpstan shell composer-install

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

## Run PHPUnit tests inside container
test:
	docker compose exec app vendor/bin/phpunit --testdox

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
