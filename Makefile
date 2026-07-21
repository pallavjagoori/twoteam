.PHONY: setup infra-up infra-down api-dev web-dev test

setup:
	cd apps/api && composer install
	corepack pnpm install

infra-up:
	docker compose -f infrastructure/compose.yml up -d --wait

infra-down:
	docker compose -f infrastructure/compose.yml down

api-dev:
	cd apps/api && php artisan serve

web-dev:
	corepack pnpm web:dev

test:
	cd apps/api && php artisan test
	corepack pnpm web:test
	bash scripts/validate-governance.sh
	bash scripts/validate-upstream.sh
