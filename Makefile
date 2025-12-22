.PHONY: run
run: .env
	@docker compose build
	@docker compose run --rm app composer install
	@docker compose run --rm app

.PHONY: shell
shell: .env
	@docker compose build
	@docker compose run --rm app composer install
	@docker compose run --rm app bash

.env:
	@cp .env.example .env || true
