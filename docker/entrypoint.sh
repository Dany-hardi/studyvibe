#!/bin/sh
# Starts cron (reminders and queued e-mails every minute), then the usual image entrypoint (Apache).
cron
exec docker-php-entrypoint "$@"
