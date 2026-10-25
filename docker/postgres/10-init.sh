#!/bin/bash
# Runs once, on the first start of an empty data volume (docker-entrypoint-initdb.d).
# To re-run it: docker compose down -v && docker compose up -d postgres
#
# Roles (ADR 013, chapter 03/08):
#   eventor_owner  owns schemas and tables, runs migrations (DATABASE_MIGRATIONS_URL); may create test databases
#   eventor_app    the application role (DATABASE_URL): DML only, append-only tables are protected by grants
#   pairrot        owner of the separate Pairrot database used by the pairrot container
set -euo pipefail

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres <<'SQL'
CREATE ROLE eventor_owner LOGIN CREATEDB PASSWORD 'eventor_owner';
CREATE ROLE eventor_app   LOGIN PASSWORD 'eventor_app';
CREATE ROLE pairrot       LOGIN PASSWORD 'pairrot';

-- Unqualified names resolve to `shared` only; every module table must be schema-qualified.
ALTER ROLE eventor_owner SET search_path = shared;
ALTER ROLE eventor_app   SET search_path = shared;

CREATE DATABASE eventor     OWNER eventor_owner;
CREATE DATABASE eventor_api OWNER eventor_owner;
CREATE DATABASE pairrot     OWNER pairrot;
SQL

for db in eventor eventor_api; do
  psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$db" <<'SQL'
-- Created here (not by a migration) because Doctrine Migrations stores its version table in `shared`
-- before the first migration runs.
CREATE SCHEMA IF NOT EXISTS shared AUTHORIZATION eventor_owner;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
SQL
done
