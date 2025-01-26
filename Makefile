up:
	docker-compose --env-file .env.local up -d

down:
	docker-compose --env-file .env.local down --remove-orphans

composer-install:
	docker-compose --env-file .env.local run --rm php-cli composer install

test:
	docker-compose --env-file .env.local run --rm php-cli php vendor/bin/phpunit