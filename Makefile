# Thin wrappers around the bin/* scripts (Docker is the only prerequisite; PHP is not installed on the host).
.PHONY: up down build migrate test-db check test api-test pairrot logs

up:            ## start the whole stack
	docker compose up -d --build

down:
	docker compose down

migrate:       ## migrate the dev database
	docker compose exec php bin/console doctrine:migrations:migrate -n

test-db:       ## make test-db TOKEN=identity
	bin/test-db $(TOKEN)

check:         ## cs, phpstan, deptrac, warmup, unit, integration, scenario
	bin/check

test: check api-test   ## every layer of tests

api-test:      ## black-box API suite against php-api
	bin/api-test

pairrot:       ## build/start Pairrot and write its keys to backend/.env.local
	bin/pairrot-bootstrap

logs:
	docker compose logs -f --tail=100 php worker
