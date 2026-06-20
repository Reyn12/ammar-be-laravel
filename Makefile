.PHONY: help setup install dev serve test test-filter pint migrate migrate-fresh seed fresh cache-clear route tinker logs queue build npm-dev

help: ## Lihat semua shortcut
	@grep -E '^[a-zA-Z0-9_-]+:.*##' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

setup: ## Setup project dari nol (composer setup)
	composer run setup

install: ## Install dependency PHP + NPM
	composer install
	npm install

dev: ## Jalankan server, queue, logs, vite sekaligus
	composer run dev

serve: ## Jalankan php artisan serve
	php artisan serve

test: ## Jalankan semua test
	php artisan test --compact

test-filter: ## Jalankan test by filter (make test-filter f=ProductControllerTest)
	php artisan test --compact --filter=$(f)

pint: ## Format kode PHP
	vendor/bin/pint --dirty --format agent

migrate: ## Jalankan migration
	php artisan migrate

migrate-fresh: ## Reset & migrate ulang
	php artisan migrate:fresh

seed: ## Jalankan seeder
	php artisan db:seed

fresh: ## Migrate fresh + seed
	php artisan migrate:fresh --seed

cache-clear: ## Clear config, route, view cache
	php artisan optimize:clear

route: ## Lihat daftar route
	php artisan route:list

tinker: ## Buka Laravel tinker
	php artisan tinker

logs: ## Tail log pakai pail
	php artisan pail

queue: ## Jalankan queue worker
	php artisan queue:listen --tries=1

build: ## Build asset frontend
	npm run build

npm-dev: ## Vite dev server
	npm run dev

fetch-main: ## Pindah Ke Branch Main
	git fetch
	git checkout main
	git pull origin main
