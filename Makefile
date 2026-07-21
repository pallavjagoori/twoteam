.PHONY: setup infra-up infra-down api-dev web-dev test

setup:
	cd apps/api && composer install
	corepack pnpm install --ignore-scripts

infra-up:
	docker compose -f infrastructure/compose.yml up -d --wait

infra-down:
	docker compose -f infrastructure/compose.yml down

reference-reset:
	bash scripts/reference-reset.sh

reference-verify:
	docker compose -f infrastructure/reference/compose.yml run --rm verify

reference-down:
	docker compose -f infrastructure/reference/compose.yml down

contracts-http-test:
	node --test tests/contracts/http-differential.test.mjs

contracts-http-run:
	node scripts/http-differential.mjs

contracts-side-effects:
	node scripts/side-effect-contracts.mjs contracts/side-effects/*.json

api-dev:
	cd apps/api && php artisan serve

web-dev:
	corepack pnpm web:dev

test:
	cd apps/api && php artisan test
	corepack pnpm web:test
	node --test tests/contracts/*.test.mjs
	corepack pnpm web:chatwoot
	bash scripts/validate-governance.sh
	bash scripts/validate-upstream.sh
