.PHONY: help install test lint phpstan phplint eslint fixtures build watch clear migrate audit check deploy-check

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

## ——— Installation ———

install: ## Installe les dépendances (composer + npm)
	composer install
	npm install

## ——— Tests ———

test: ## Lance tous les tests
	vendor/bin/phpunit

test-unit: ## Lance les tests unitaires
	vendor/bin/phpunit --testsuite Unit

test-func: ## Lance les tests fonctionnels
	vendor/bin/phpunit --testsuite Functional

test-verbose: ## Lance les tests avec affichage détaillé
	vendor/bin/phpunit --testdox

## ——— Qualité de code ———

lint: phplint phpstan eslint ## Lance tous les linters

phplint: ## Vérifie la syntaxe PHP
	vendor/bin/parallel-lint src/ --exclude vendor

phpstan: ## Analyse statique PHP (level 7)
	vendor/bin/phpstan analyse

eslint: ## Lint JavaScript/JSX
	npx eslint assets/

check: lint test ## Lance linters + tests (pré-commit)

## ——— Base de données ———

migrate: ## Applique les migrations
	php bin/console doctrine:migrations:migrate -n

fixtures: ## Recharge les fixtures (PURGE LA BDD)
	php bin/console doctrine:fixtures:load -n

fixtures-test: ## Recharge les fixtures de test
	php bin/console doctrine:fixtures:load -n --env=test

## ——— Assets ———

build: ## Build les assets pour la production
	npm run build

watch: ## Build les assets en mode watch (dev)
	npm run watch

## ——— Commandes métier ———

report: ## Envoie le rapport journalier
	php bin/console app:send-daily-report

reminders: ## Envoie les rappels J-2
	php bin/console app:send-reminders

anonymize: ## Anonymise les anciennes réservations (RGPD)
	php bin/console app:anonymize-reservations

## ——— Utilitaires ———

clear: ## Vide le cache
	php bin/console cache:clear

deploy-check: lint test build ## Vérifie que tout est prêt pour le déploiement
	@echo "\033[32m✅ Prêt pour le déploiement\033[0m"
