.PHONY: up down restart logs ps shell db-shell migrate seed test analyse lint typecheck build clean

up:
	docker compose up -d --build

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f

ps:
	docker compose ps

shell:
	docker compose exec app sh

db-shell:
	docker compose exec db mariadb -u wp_monitor -psecret wp_monitor

migrate:
	docker compose exec app php bin/migrate

seed:
	docker compose exec app php bin/seed

test:
	docker compose exec app composer test
	cd frontend && npm run test

analyse:
	docker compose exec app composer analyse

lint:
	docker compose exec frontend npm run lint

typecheck:
	docker compose exec frontend npm run typecheck

build:
	docker compose exec frontend npm run build

clean:
	docker compose down -v
	docker compose up -d --build
