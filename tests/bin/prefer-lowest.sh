#!/bin/sh
# Locks a fixture's runtime dependencies at the lowest versions its constraints allow, so its rows prove those minimums.
# Usage: tests/bin/prefer-lowest.sh consumer-a | persona-core | persona-settings | persona-woocommerce
set -eu

case "$1" in
	consumer-a)
		dir=tests/Fixtures/consumer-a
		set -- 'ahegyes/wp-framework-*' php-di/php-di
		;;
	persona-*)
		name="${1#persona-}"
		dir="tests/Fixtures/personas/$name"
		tested="ahegyes/wp-framework-$name"
		# Only the tested package keeps its path, so the rest of the closure resolves from the persona's vcs entries.
		jq --arg name "$name" '.repositories[0].url |= sub("\\*$"; $name)' "$dir/composer.json" > "$dir/composer.json.tmp"
		mv "$dir/composer.json.tmp" "$dir/composer.json"
		set -- "$tested"
		;;
	*)
		echo "Unknown fixture: $1" >&2
		exit 1
		;;
esac

# Composer's pool optimizer drops a shared dependency, such as psr/log, when the lock and a lower tag constrain it differently.
COMPOSER_POOL_OPTIMIZER=0 composer update --prefer-lowest --prefer-stable --with-all-dependencies --no-install --no-interaction --working-dir="$dir" "$@"

if [ -n "${tested:-}" ] && ! jq -e --arg tested "$tested" '.["packages-dev"] | map(select(.name | startswith("ahegyes/wp-framework-"))) | all(if .name == $tested then .dist.type == "path" else .source.type == "git" and (.version | test("^v?[0-9]+\\.[0-9]+\\.[0-9]+$")) end)' "$dir/composer.lock" > /dev/null; then
	echo "Every framework package but $tested must lock from its mirror at a tag." >&2
	exit 1
fi
