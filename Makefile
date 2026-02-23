#
# Copyright (c) 2025. Numeric Wave
#
# Affero General Public License (AGPL) v3
#
# For more information, please refer to the LICENSE file at the root of the project.
#

# ——— Variables ————————————————————————————————————————————————————————
DOCKER_COMP = docker compose
PHP_CONT    = $(DOCKER_COMP) exec php
PHP         = $(PHP_CONT) php
CONSOLE     = $(PHP) bin/console
COMPOSER    = $(PHP_CONT) composer

# ——— Help —————————————————————————————————————————————————————————————
.DEFAULT_GOAL = help
.PHONY: help
help: ## Afficher cette aide
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m  %-20s\033[0m %s\n", $$1, $$2}' \
		| sed -e 's/\[32m  ##/[33m/'

# ——— Docker ———————————————————————————————————————————————————————————
## Docker
.PHONY: build start stop restart logs ps

build: ## Construire les images Docker
	$(DOCKER_COMP) build

start: ## Demarrer les containers
	$(DOCKER_COMP) up -d --wait

stop: ## Arreter les containers
	$(DOCKER_COMP) down

restart: stop start ## Redemarrer les containers

logs: ## Afficher les logs (docker compose logs -f)
	$(DOCKER_COMP) logs -f

ps: ## Voir le statut des containers
	$(DOCKER_COMP) ps

# ——— App (Docker) —————————————————————————————————————————————————————
## Application (Docker)
.PHONY: install install-app shell composer-install db-create db-migrate db-fixtures db-init db-seed assets cc

install: build start install-app ## Installation complete (idempotent, ne detruit pas la BDD existante)
	@echo ""
	@echo "=========================================="
	@echo "  Installation terminee !"
	@echo "  Credentials : superadmin / superadmin"
	@echo "  URL : https://lucca.local"
	@echo "=========================================="

install-app: composer-install assets db-create db-migrate db-seed ## Installer l'application (dans un container deja demarre)

shell: ## Ouvrir un shell dans le container PHP
	$(PHP_CONT) bash

composer-install: ## Installer les dependances Composer
	$(COMPOSER) install --no-interaction

db-create: ## Creer la base de donnees si elle n'existe pas
	$(CONSOLE) doctrine:database:create --if-not-exists --no-interaction

db-migrate: ## Lancer les migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

db-fixtures: ## Charger les fixtures (purge la BDD puis recharge)
	$(CONSOLE) doctrine:fixtures:load --no-interaction

db-seed: ## Charger les fixtures sans purge (ajout si BDD vide)
	$(CONSOLE) doctrine:fixtures:load --append --no-interaction || true
	$(CONSOLE) lucca:init:setting --no-interaction || true
	$(CONSOLE) lucca:init:media --no-interaction || true

db-init: ## Reset la BDD from scratch (drop + create + migrate + fixtures + init)
	@echo "\033[33m/!\\ Cette commande va SUPPRIMER et recreer la base de donnees.\033[0m"
	@echo "    Toutes les donnees seront perdues."
	@read -p "    Continuer ? [y/N] " confirm && [ "$$confirm" = "y" ] || (echo "Annule."; exit 1)
	$(CONSOLE) doctrine:database:drop --if-exists --force --no-interaction
	$(CONSOLE) doctrine:database:create --no-interaction
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) doctrine:fixtures:load --no-interaction
	$(CONSOLE) lucca:init:setting --no-interaction || true
	$(CONSOLE) lucca:init:media --no-interaction || true
	@echo ""
	@echo "BDD reinitialisee. Credentials : superadmin / superadmin"

assets: ## Compiler les assets
	$(CONSOLE) fos:js-routing:dump --format=json --target=assets/routes.json
	$(CONSOLE) importmap:install
	$(CONSOLE) asset-map:compile

cc: ## Vider le cache Symfony
	$(CONSOLE) cache:clear

# ——— Tests ————————————————————————————————————————————————————————————
## Tests
.PHONY: tests test-bundle

tests: ## Lancer tous les tests
	$(PHP) -d memory_limit=-1 bin/phpunit src

test-bundle: ## Tester un bundle (usage: make test-bundle BUNDLE=UserBundle)
	$(PHP) -d memory_limit=-1 bin/phpunit src/Lucca/Bundle/$(BUNDLE)

# ——— Reset ————————————————————————————————————————————————————————————
## Maintenance
.PHONY: reset

reset: ## Reset complet Docker (supprime volumes et reinstalle)
	@echo "\033[33m/!\\ Cette commande va SUPPRIMER les volumes Docker (BDD, certificats...).\033[0m"
	@read -p "    Continuer ? [y/N] " confirm && [ "$$confirm" = "y" ] || (echo "Annule."; exit 1)
	$(DOCKER_COMP) down -v
	$(MAKE) install

# ——— Natif (sans Docker) —————————————————————————————————————————————
## Setup natif (Symfony CLI)
.PHONY: install-native serve serve-stop native-db-setup

install-native: ## Installation native (requiert PHP 8.2+, Composer, MariaDB)
	@echo "Verification des prerequis..."
	@php -v | head -1
	@composer --version
	@echo ""
	composer install --no-interaction
	php bin/console fos:js-routing:dump --format=json --target=assets/routes.json
	php bin/console importmap:install
	php bin/console asset-map:compile
	@echo ""
	@echo "Assets compiles. Configurez votre .env.local puis lancez :"
	@echo "  make native-db-setup"
	@echo "  make serve"

native-db-setup: ## Creer et initialiser la BDD (mode natif)
	@echo "\033[33m/!\\ Cette commande va SUPPRIMER et recreer la base de donnees.\033[0m"
	@read -p "    Continuer ? [y/N] " confirm && [ "$$confirm" = "y" ] || (echo "Annule."; exit 1)
	php bin/console doctrine:database:drop --if-exists --force --no-interaction
	php bin/console doctrine:database:create --no-interaction
	php bin/console doctrine:migrations:migrate --no-interaction
	php bin/console doctrine:fixtures:load --no-interaction
	php bin/console lucca:init:setting --no-interaction || true
	php bin/console lucca:init:media --no-interaction || true
	@echo ""
	@echo "BDD initialisee. Credentials : superadmin / superadmin"

serve: ## Demarrer le serveur Symfony (mode natif)
	symfony serve -d

serve-stop: ## Arreter le serveur Symfony (mode natif)
	symfony server:stop
