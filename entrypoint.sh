#!/usr/bin/env sh
set -euo pipefail

if [ ! -f /app/vendor/autoload.php ]; then
    echo "Dependencies not installed. Did composer install fail?" >&2
    exit 1
fi

SESSION_FILE_PATH="${SESSION_FILE:-}"
if [ -z "$SESSION_FILE_PATH" ]; then
    echo "SESSION_FILE environment variable is required." >&2
    exit 1
fi

mkdir -p "$(dirname "$SESSION_FILE_PATH")"

php -S 0.0.0.0:${PORT:-8080} -t public &
PHP_SERVER_PID=$!

cleanup() {
    kill "$PHP_SERVER_PID" 2>/dev/null || true
}

trap cleanup INT TERM

php src/Bot/run.php

cleanup
wait "$PHP_SERVER_PID"
