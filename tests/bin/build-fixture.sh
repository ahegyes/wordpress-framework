#!/bin/sh
# Builds a fixture: a development install, which scopes the framework into it, its own PHPStan, then its production install.
# Usage: tests/bin/build-fixture.sh tests/Fixtures/consumer-a
set -eu

dir="${1:?Usage: tests/bin/build-fixture.sh <fixture directory>}"

composer validate --strict --working-dir="$dir"
rm -rf "$dir/vendor"
composer install --working-dir="$dir" --no-interaction
"$dir/vendor/bin/phpstan" analyse -c "$dir/phpstan.dist.neon" --memory-limit=1G --no-progress
composer install --working-dir="$dir" --no-dev --no-interaction
