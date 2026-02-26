.PHONY: up up-d up-b up-r down composer composer-install composer-update run cache-clear setup commit-a

setup:
	@test -f .env || cp .env.dist .env

-include .env

# Змінні для Docker
PROJECT_NAME ?= $(shell grep -m 1 'PROJECT_NAME' .env.local | cut -d '=' -f2-)

DOCKER_COMPOSE = docker-compose
PHP_BASH = docker exec -it php_$(PROJECT_NAME) /bin/bash
DOCKER_EXEC = $(PHP_BASH) -c

# Docker Compose Commands
up: setup
	$(DOCKER_COMPOSE) up

up-d: setup
	$(DOCKER_COMPOSE) up -d

up-b: setup
	$(DOCKER_COMPOSE) up --build

up-r: setup
	$(DOCKER_COMPOSE) up --force-recreate

down: setup
	$(DOCKER_COMPOSE) down --remove-orphans

exec: setup
	$(DOCKER_EXEC) "echo -e '\033[32m'; /bin/bash"

# Composer Commands
composer: setup
	$(DOCKER_EXEC) "composer $(CMD)"

composer-install: CMD = install
composer-install: composer

composer-update: CMD = update
composer-update: composer

composer-i: composer-install
composer-u: composer-update

commit-a:
	@printf "\033[33mПідтвердити push з amend? (y/N): \033[0m"; \
	read CONF && [ "$$CONF" = "y" ] || exit 1; \
	git add .; \
	git commit --no-edit --amend; \
	git push --force; \
		printf "\033[36mІcнуючі теги:\033[0m\n"; \
		TAGS="$$(git tag --sort=-v:refname)"; \
		i=1; \
		while IFS= read -r t; do \
			[ -z "$$t" ] && continue; \
			printf "\033[33m[\033[0m\033[36m%s\033[0m\033[33m]\033[0m \033[35m%s\033[0m\n" "$$i" "$$t"; \
			i=$$((i+1)); \
		done <<< "$$TAGS"; \
		printf ">>> \033[33mВведи \033[0m\033[36mпорядковий номер версії\033[0m\033[33m, \033[35mнову версію\033[0m\033[33m, або натисни Enter щоб пропустити:  \033[0m"; \
		read -r INPUT; \
		[ -z "$$INPUT" ] && INPUT=0; \
		if [[ "$$INPUT" =~ ^[0-9]+$$ ]]; then \
			if [ "$$INPUT" -eq 0 ]; then exit 0; fi; \
			TAG="$$(printf "%s\n" "$$TAGS" | sed -n "$${INPUT}p")"; \
		else \
			TAG="$$INPUT"; \
		fi; \
		git tag -d $$TAG 2>/dev/null || true; \
		git push origin :refs/tags/$$TAG; \
		git tag $$TAG; \
		git push origin $$TAG

# Application Specific Commands
console: setup
	$(DOCKER_EXEC) "php bin/console $(EXEC)"

# Це скидає будь-які аргументи передані до 'run', роблячи їх не-цілями
%:
	@:

