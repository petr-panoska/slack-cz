#!/usr/bin/env bash
set -Eeuo pipefail

umask 077

readonly CONTAINER="${LEGACY_DB_CONTAINER:-slack-cz-legacy-db-1}"
readonly CONFIG_DIR="${BACKUP_CONFIG_DIR:-/etc/slack-cz-backup}"
readonly RCLONE_CONFIG="$CONFIG_DIR/rclone.conf"
readonly AGE_RECIPIENT_FILE="$CONFIG_DIR/age-recipient.txt"
readonly REMOTE="${BACKUP_REMOTE:-gdrive:slack-cz-backups/legacy-db}"
readonly RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-90}"
readonly LOCK_FILE="/run/lock/slack-cz-legacy-db-backup.lock"

die() {
    echo "ERROR: $*" >&2
    exit 1
}

for command in age docker flock gzip rclone; do
    command -v "$command" >/dev/null || die "Required command not found: $command"
done

[[ -r "$RCLONE_CONFIG" ]] || die "Missing rclone config: $RCLONE_CONFIG"
[[ -r "$AGE_RECIPIENT_FILE" ]] || die "Missing age recipient: $AGE_RECIPIENT_FILE"
[[ "$RETENTION_DAYS" =~ ^[1-9][0-9]*$ ]] || die "BACKUP_RETENTION_DAYS must be a positive integer"

exec 9>"$LOCK_FILE"
flock -n 9 || die "Another legacy DB backup is already running"

docker inspect "$CONTAINER" >/dev/null 2>&1 || die "Container does not exist: $CONTAINER"
[[ "$(docker inspect -f '{{.State.Running}}' "$CONTAINER")" == "true" ]] || die "Container is not running: $CONTAINER"

database="$(docker exec "$CONTAINER" printenv MYSQL_DATABASE)"
[[ "$database" =~ ^[A-Za-z0-9_]+$ ]] || die "Unsafe or empty MYSQL_DATABASE value"

recipient="$(<"$AGE_RECIPIENT_FILE")"
[[ "$recipient" == age1* ]] || die "Invalid age recipient"

work_dir="$(mktemp -d /var/tmp/slack-cz-legacy-backup.XXXXXX)"
trap 'rm -rf -- "$work_dir"' EXIT

timestamp="$(date -u +%Y-%m-%dT%H%M%SZ)"
archive_name="${database}-${timestamp}.sql.gz.age"
archive_path="$work_dir/$archive_name"

echo "Creating encrypted legacy DB dump: $database ($timestamp)"
docker exec "$CONTAINER" sh -c '
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump \
        --user=root \
        --single-transaction \
        --quick \
        --routines \
        --events \
        --triggers \
        --hex-blob \
        "$MYSQL_DATABASE"
' | gzip -9 | age --recipient "$recipient" --output "$archive_path"

[[ -s "$archive_path" ]] || die "Encrypted dump is empty"

echo "Uploading $archive_name"
rclone --config "$RCLONE_CONFIG" copyto "$archive_path" "$REMOTE/$archive_name"
rclone --config "$RCLONE_CONFIG" check "$work_dir" "$REMOTE" \
    --include "$archive_name" \
    --one-way

echo "Removing encrypted dumps older than $RETENTION_DAYS days"
rclone --config "$RCLONE_CONFIG" delete "$REMOTE" \
    --include '*.sql.gz.age' \
    --min-age "${RETENTION_DAYS}d"

echo "Legacy DB backup completed: $REMOTE/$archive_name"
