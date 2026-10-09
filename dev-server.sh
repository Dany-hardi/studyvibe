#!/usr/bin/env bash
# Local development server with the same upload limits as production (.user.ini, .htaccess, docker/php-tuning.ini).
# PHP's built-in server ignores .user.ini and .htaccess and starts with php.ini defaults (2 MB upload), so the limits are passed here.
# Usage: ./dev-server.sh [host:port]   (default 127.0.0.1:8123)
cd "$(dirname "$0")" || exit 1
exec php \
  -d upload_max_filesize=512M -d post_max_size=640M -d max_file_uploads=100 \
  -d memory_limit=512M -d max_execution_time=600 -d max_input_time=600 \
  -S "${1:-127.0.0.1:8123}"
