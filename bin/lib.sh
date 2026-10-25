#!/usr/bin/env bash
# Shared helpers of the bin/* scripts (sourced, not executed).
# Everything PHP runs in Docker; PHP is not installed on the host.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Git Bash on Windows rewrites leading-slash arguments; docker needs them verbatim.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

dc() { docker compose "$@"; }

# psql as the Postgres superuser inside the postgres container.
pg() { dc exec -T postgres psql -v ON_ERROR_STOP=1 -U postgres "$@"; }

# One-off php container without starting dependencies (vendor/ comes from the shared `vendor` volume).
php_run() { dc run --rm --no-deps -T php "$@"; }

ensure_stack_base() {
  dc up -d --wait postgres >/dev/null
  # vendor/ lives in a named volume: install it once.
  php_run sh -c 'test -f vendor/autoload.php || composer install --no-interaction --prefer-dist' >/dev/null
}

# Recreates an empty database owned by eventor_owner with the `shared` schema (migrations need it first).
recreate_database() {
  local db="$1"
  pg -d postgres -c "DROP DATABASE IF EXISTS \"$db\" WITH (FORCE)" -c "CREATE DATABASE \"$db\" OWNER eventor_owner" >/dev/null
  pg -d "$db" -c "CREATE SCHEMA shared AUTHORIZATION eventor_owner" -c "REVOKE ALL ON SCHEMA public FROM PUBLIC" >/dev/null
}
