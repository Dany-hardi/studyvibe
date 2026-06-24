#!/usr/bin/env bash
# Sauvegarde quotidienne de la base StudyVibe
# Usage: ./scripts/backup-db.sh
# Cron: 0 2 * * * /path/to/LMS_AGY/scripts/backup-db.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="${ROOT_DIR}/.env"
BACKUP_DIR="${ROOT_DIR}/backups"
DATE=$(date +%Y%m%d_%H%M%S)

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Erreur: .env introuvable" >&2
    exit 1
fi

# shellcheck disable=SC1090
source <(grep -E '^[A-Z_]+=' "$ENV_FILE" | sed 's/^/export /')

mkdir -p "$BACKUP_DIR"
OUTPUT="${BACKUP_DIR}/studyvibe_${DATE}.sql.gz"

mysqldump -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" \
    -u "${DB_USER:-root}" ${DB_PASS:+-p"$DB_PASS"} \
    --single-transaction --routines "${DB_NAME:-studyvibe}" | gzip > "$OUTPUT"

# Conserver 30 jours
find "$BACKUP_DIR" -name 'studyvibe_*.sql.gz' -mtime +30 -delete

echo "Sauvegarde créée: $OUTPUT"
