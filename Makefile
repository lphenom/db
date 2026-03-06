.PHONY: up down test lint phpstan shell

## Start Docker environment
up:
	docker compose up -d --build

## Stop Docker environment
down:
	docker compose down

## Run PHPUnit tests inside container
test:
	docker compose run --rm app vendor/bin/phpunit --testdox

## Run php-cs-fixer check (dry-run) inside container
lint:
	docker compose run --rm app vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php

## Auto-fix code style inside container
lint-fix:
	docker compose run --rm app vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php

## Run PHPStan analysis inside container
phpstan:
	docker compose run --rm app vendor/bin/phpstan analyse --no-progress

## Open shell inside app container
shell:
	docker compose run --rm app bash

## Install composer dependencies inside container
composer-install:
	docker compose run --rm app composer install --no-interaction --prefer-dist

