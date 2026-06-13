#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 027

# -----------------------------------------------------------------------------
# TRACS production deployment configuration
#
# Override any value through the environment instead of editing this file:
#   REPO_DIR=/srv/tracs-repository WEB_ROOT=/var/www/tracs ./deploy.sh check
#
# WEB_ROOT is the deployed application root. Nginx must use WEB_ROOT/public as
# its document root so config/, core/, modules/, logs/, SQL, and docs stay
# outside direct web access.
# -----------------------------------------------------------------------------

APP_NAME="${APP_NAME:-TRACS}"
APP_VERSION="${APP_VERSION:-}"
REPO_DIR="${REPO_DIR:-/srv/tracs-repository}"
WEB_ROOT="${WEB_ROOT:-/var/www/tracs}"
BRANCH="${BRANCH:-main}"
PHP_VERSION="${PHP_VERSION:-8.3}"
NGINX_SITE_NAME="${NGINX_SITE_NAME:-tracs}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/tracs}"
DB_NAME="${DB_NAME:-tracs}"
DB_USER="${DB_USER:-tracs_app}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DOMAIN="${DOMAIN:-}"
RUN_MIGRATION="${RUN_MIGRATION:-false}"

DB_PORT="${DB_PORT:-3306}"
MYSQL_LOGIN_PATH="${MYSQL_LOGIN_PATH:-}"
MYSQL_DEFAULTS_FILE="${MYSQL_DEFAULTS_FILE:-}"
MIGRATION_FILE="${MIGRATION_FILE:-}"
GIT_REMOTE="${GIT_REMOTE:-origin}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php${PHP_VERSION}-fpm}"
DEPLOY_USER="${DEPLOY_USER:-$(id -un)}"
DEPLOY_GROUP="${DEPLOY_GROUP:-$(id -gn)}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"
HEALTHCHECK_PATH="${HEALTHCHECK_PATH:-/login.php}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-}"
HEALTHCHECK_HOST="${HEALTHCHECK_HOST:-${DOMAIN:-localhost}}"
HEALTHCHECK_RETRIES="${HEALTHCHECK_RETRIES:-5}"
HEALTHCHECK_TIMEOUT="${HEALTHCHECK_TIMEOUT:-10}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-}"
LOCK_FILE="${LOCK_FILE:-/tmp/tracs-deploy-${UID}.lock}"

UPDATE_REPO="${UPDATE_REPO:-true}"
BACKUP_DATABASE="${BACKUP_DATABASE:-true}"
REQUIRE_DB_BACKUP="${REQUIRE_DB_BACKUP:-true}"
ALLOW_DIRTY_REPO="${ALLOW_DIRTY_REPO:-false}"
ALLOW_ROOT="${ALLOW_ROOT:-false}"
ALLOW_UNSUPPORTED_OS="${ALLOW_UNSUPPORTED_OS:-false}"
ALLOW_DESTRUCTIVE_MIGRATION="${ALLOW_DESTRUCTIVE_MIGRATION:-false}"
ASSUME_YES="${ASSUME_YES:-false}"
DRY_RUN="${DRY_RUN:-false}"
SKIP_SERVICE_RELOAD="${SKIP_SERVICE_RELOAD:-false}"

MODE="deploy"
ROLLBACK_ID=""
CURRENT_BACKUP_ID=""
CURRENT_BACKUP_PATH=""
CURRENT_BACKUP_HAS_WEBROOT="false"
TEMP_DIR=""
MYSQL_CONNECTION_READY="false"

PUBLIC_ROOT=""
SOURCE_COMMIT=""

readonly -a PRESERVED_PATHS=(
    ".env"
    "config/.env"
    "config/database.php"
    "public/uploads"
    "public/cache"
    "logs"
    "storage/deployment"
)

readonly -a RUNTIME_DIRS=(
    "public/uploads"
    "public/uploads/avatars"
    "public/uploads/case_attachments"
    "public/uploads/shift_report_attachments"
    "public/uploads/mom"
    "public/cache"
    "public/cache/holidays"
    "logs"
)

readonly -a PRIVATE_READ_DIRS=(
    "storage"
    "storage/deployment"
)

readonly -a REQUIRED_PHP_EXTENSIONS=(
    "curl"
    "fileinfo"
    "gd"
    "json"
    "mbstring"
    "mysqli"
    "openssl"
    "session"
)

readonly -a RSYNC_EXCLUDES=(
    "/.git/"
    "/.cursor/"
    "/.env"
    "/config/.env"
    "/config/database.php"
    "/public/uploads/"
    "/public/cache/"
    "/logs/"
    "/storage/deployment/"
    "/backup/"
    "/backups/"
    "/graphify-out/"
    "/{api,modules/"
    "/{public,modules/"
    "/Dockerfile"
    "/docker-compose.yml"
    ".DS_Store"
)

if [[ -t 1 ]]; then
    COLOR_BLUE=$'\033[34m'
    COLOR_GREEN=$'\033[32m'
    COLOR_YELLOW=$'\033[33m'
    COLOR_RED=$'\033[31m'
    COLOR_RESET=$'\033[0m'
else
    COLOR_BLUE=""
    COLOR_GREEN=""
    COLOR_YELLOW=""
    COLOR_RED=""
    COLOR_RESET=""
fi

timestamp() {
    date '+%Y-%m-%d %H:%M:%S'
}

log_info() {
    printf '%s[%s] INFO:%s %s\n' "$COLOR_BLUE" "$(timestamp)" "$COLOR_RESET" "$*"
}

log_success() {
    printf '%s[%s] OK:%s %s\n' "$COLOR_GREEN" "$(timestamp)" "$COLOR_RESET" "$*"
}

log_warn() {
    printf '%s[%s] WARN:%s %s\n' "$COLOR_YELLOW" "$(timestamp)" "$COLOR_RESET" "$*" >&2
}

log_error() {
    printf '%s[%s] ERROR:%s %s\n' "$COLOR_RED" "$(timestamp)" "$COLOR_RESET" "$*" >&2
}

die() {
    log_error "$*"
    exit 1
}

is_true() {
    case "$1" in
        1|true|TRUE|True|yes|YES|Yes|y|Y|on|ON|On) return 0 ;;
        *) return 1 ;;
    esac
}

print_command() {
    local arg
    printf '  +'
    for arg in "$@"; do
        printf ' %q' "$arg"
    done
    printf '\n'
}

run() {
    if is_true "$DRY_RUN"; then
        print_command "$@"
        return 0
    fi
    "$@"
}

run_privileged() {
    if [[ "$(id -u)" -eq 0 ]]; then
        run "$@"
    else
        run sudo "$@"
    fi
}

cleanup() {
    if [[ -n "$TEMP_DIR" && -d "$TEMP_DIR" ]]; then
        rm -rf -- "$TEMP_DIR"
    fi
}

on_error() {
    local line="$1"
    local status="$2"
    log_error "Deployment stopped at line ${line} with status ${status}."
    if [[ -n "$CURRENT_BACKUP_ID" && "$CURRENT_BACKUP_HAS_WEBROOT" == "true" ]]; then
        log_error "Application rollback command: $0 rollback ${CURRENT_BACKUP_ID}"
    fi
    if [[ -n "$CURRENT_BACKUP_PATH" && -f "$CURRENT_BACKUP_PATH/database.sql.gz" ]]; then
        log_error "Database rollback is intentionally manual; review ${CURRENT_BACKUP_PATH}/database.sql.gz."
    fi
    exit "$status"
}

trap cleanup EXIT
trap 'on_error "$LINENO" "$?"' ERR

usage() {
    cat <<'EOF'
TRACS production deployment

Usage:
  ./deploy.sh check
  ./deploy.sh deploy [--dry-run] [--yes]
  ./deploy.sh deploy --with-migration <config/migrations/file.sql> [--yes]
  ./deploy.sh rollback <backup-id> [--dry-run] [--yes]

Commands:
  check       Validate the host, repository, PHP, database access, and Nginx.
  deploy      Back up and deploy the configured branch. This is the default.
  rollback    Restore application files from a named backup while preserving
              current secrets, uploads, cache, and logs.

Options:
  --dry-run                 Show mutating commands without executing them.
  --yes                     Skip confirmation prompts.
  --with-migration <file>   Run one explicitly selected SQL migration.
  --no-repo-update          Deploy the current checked-out commit.
  -h, --help                Show this help.

Important environment variables:
  REPO_DIR, WEB_ROOT, BRANCH, APP_VERSION, PHP_VERSION, NGINX_SITE_NAME, BACKUP_DIR
  DB_NAME, DB_USER, DB_HOST, DB_PORT, DOMAIN
  MYSQL_LOGIN_PATH or MYSQL_DEFAULTS_FILE

Database passwords are never accepted as command-line arguments. Configure a
MySQL login path, a mode-600 defaults file, or the deploy user's ~/.my.cnf.
EOF
}

parse_args() {
    if [[ $# -gt 0 ]]; then
        case "$1" in
            deploy|check|rollback)
                MODE="$1"
                shift
                ;;
            -h|--help|help)
                usage
                exit 0
                ;;
        esac
    fi

    if [[ "$MODE" == "rollback" ]]; then
        [[ $# -gt 0 && "$1" != --* ]] || die "rollback requires a backup ID."
        ROLLBACK_ID="$1"
        shift
    fi

    while [[ $# -gt 0 ]]; do
        case "$1" in
            --dry-run)
                DRY_RUN="true"
                ;;
            --yes)
                ASSUME_YES="true"
                ;;
            --with-migration)
                [[ $# -ge 2 ]] || die "--with-migration requires a SQL file."
                RUN_MIGRATION="true"
                MIGRATION_FILE="$2"
                shift
                ;;
            --no-repo-update)
                UPDATE_REPO="false"
                ;;
            -h|--help)
                usage
                exit 0
                ;;
            *)
                die "Unknown argument: $1"
                ;;
        esac
        shift
    done
}

confirm() {
    local prompt="$1"
    local answer

    if is_true "$ASSUME_YES"; then
        return 0
    fi
    if [[ ! -t 0 ]]; then
        die "Confirmation required in a non-interactive shell. Re-run with --yes or ASSUME_YES=true."
    fi
    read -r -p "${prompt} [y/N] " answer
    case "$answer" in
        y|Y|yes|YES|Yes) return 0 ;;
        *) die "Cancelled by operator." ;;
    esac
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || die "Required command not found: $1"
}

canonical_path() {
    realpath -m -- "$1"
}

path_is_within() {
    local child="$1"
    local parent="$2"
    [[ "$child" == "$parent" || "$child" == "$parent/"* ]]
}

validate_paths() {
    [[ "$REPO_DIR" == /* ]] || die "REPO_DIR must be an absolute path."
    [[ "$WEB_ROOT" == /* ]] || die "WEB_ROOT must be an absolute path."
    [[ "$BACKUP_DIR" == /* ]] || die "BACKUP_DIR must be an absolute path."

    REPO_DIR="$(canonical_path "$REPO_DIR")"
    WEB_ROOT="$(canonical_path "$WEB_ROOT")"
    BACKUP_DIR="$(canonical_path "$BACKUP_DIR")"
    PUBLIC_ROOT="${WEB_ROOT}/public"

    case "$WEB_ROOT" in
        /|/bin|/boot|/dev|/etc|/home|/lib|/lib64|/opt|/proc|/root|/run|/sbin|/srv|/sys|/tmp|/usr|/var)
            die "Unsafe WEB_ROOT: ${WEB_ROOT}"
            ;;
    esac
    case "$BACKUP_DIR" in
        /|/bin|/boot|/dev|/etc|/home|/lib|/lib64|/opt|/proc|/root|/run|/sbin|/srv|/sys|/tmp|/usr|/var)
            die "Unsafe BACKUP_DIR: ${BACKUP_DIR}"
            ;;
    esac

    [[ "$REPO_DIR" != "$WEB_ROOT" ]] || die "REPO_DIR and WEB_ROOT must be different."
    path_is_within "$WEB_ROOT" "$REPO_DIR" && die "WEB_ROOT cannot be inside REPO_DIR."
    path_is_within "$REPO_DIR" "$WEB_ROOT" && die "REPO_DIR cannot be inside WEB_ROOT."
    path_is_within "$BACKUP_DIR" "$WEB_ROOT" && die "BACKUP_DIR cannot be inside WEB_ROOT."
    path_is_within "$BACKUP_DIR" "$REPO_DIR" && die "BACKUP_DIR cannot be inside REPO_DIR."
}

validate_scalar_config() {
    [[ "$BRANCH" =~ ^[A-Za-z0-9._/-]+$ ]] || die "BRANCH contains unsupported characters."
    [[ "$PHP_VERSION" =~ ^[0-9]+\.[0-9]+$ ]] || die "PHP_VERSION must look like 8.3."
    [[ "$DB_PORT" =~ ^[0-9]+$ ]] || die "DB_PORT must be numeric."
    [[ "$HEALTHCHECK_RETRIES" =~ ^[1-9][0-9]*$ ]] || die "HEALTHCHECK_RETRIES must be positive."
    [[ "$HEALTHCHECK_TIMEOUT" =~ ^[1-9][0-9]*$ ]] || die "HEALTHCHECK_TIMEOUT must be positive."
    if [[ -n "$BACKUP_RETENTION_DAYS" ]]; then
        [[ "$BACKUP_RETENTION_DAYS" =~ ^[1-9][0-9]*$ ]] || die "BACKUP_RETENTION_DAYS must be empty or positive."
    fi
    if [[ -n "$DOMAIN" ]]; then
        [[ "$DOMAIN" != *"://"* && "$DOMAIN" != */* ]] || die "DOMAIN must be a hostname without scheme or path."
    fi
}

check_operator() {
    require_command getent
    if [[ "$(id -u)" -eq 0 ]] && ! is_true "$ALLOW_ROOT"; then
        die "Run as the deploy user, not root. The script uses sudo only for privileged operations."
    fi
    id "$DEPLOY_USER" >/dev/null 2>&1 || die "DEPLOY_USER does not exist: ${DEPLOY_USER}"
    getent group "$DEPLOY_GROUP" >/dev/null 2>&1 || die "DEPLOY_GROUP does not exist: ${DEPLOY_GROUP}"
    id "$WEB_USER" >/dev/null 2>&1 || die "WEB_USER does not exist: ${WEB_USER}"
    getent group "$WEB_GROUP" >/dev/null 2>&1 || die "WEB_GROUP does not exist: ${WEB_GROUP}"
    if [[ "$(id -u)" -eq 0 && ( "$DEPLOY_USER" == "root" || "$DEPLOY_GROUP" == "root" ) ]]; then
        die "When ALLOW_ROOT=true, set DEPLOY_USER and DEPLOY_GROUP to the non-root application owner."
    fi
    if [[ "$(id -u)" -ne 0 ]]; then
        [[ "$DEPLOY_USER" == "$(id -un)" ]] || die "DEPLOY_USER must match the current non-root operator."
        require_command sudo
        if ! sudo -v; then
            die "The deploy user needs working sudo access."
        fi
    fi
}

check_os() {
    local os_id=""
    local os_version=""

    if [[ -r /etc/os-release ]]; then
        # shellcheck disable=SC1091
        source /etc/os-release
        os_id="${ID:-}"
        os_version="${VERSION_ID:-}"
    fi
    if [[ "$os_id" != "ubuntu" || "$os_version" != "24.04" ]]; then
        if is_true "$ALLOW_UNSUPPORTED_OS"; then
            log_warn "Expected Ubuntu 24.04; detected ${os_id:-unknown} ${os_version:-unknown}."
        else
            die "This script targets Ubuntu 24.04. Set ALLOW_UNSUPPORTED_OS=true only after review."
        fi
    fi
}

acquire_lock() {
    require_command flock
    [[ ! -L "$LOCK_FILE" ]] || die "Refusing symlink lock file: ${LOCK_FILE}"
    (umask 077; touch "$LOCK_FILE")
    [[ -f "$LOCK_FILE" && ! -L "$LOCK_FILE" ]] || die "Unable to create a safe lock file."
    exec 9<>"$LOCK_FILE"
    flock -n 9 || die "Another TRACS deployment is already running (${LOCK_FILE})."
}

check_dependencies() {
    local command_name
    local required=(
        awk
        curl
        df
        du
        find
        getent
        git
        grep
        gzip
        head
        mysql
        mysqldump
        nginx
        php
        realpath
        rsync
        sha256sum
        sort
        stat
        systemctl
        tar
        tr
        xargs
    )

    for command_name in "${required[@]}"; do
        require_command "$command_name"
    done
}

check_repository() {
    local current_branch
    local dirty

    [[ -d "$REPO_DIR/.git" ]] || die "REPO_DIR is not a Git working tree: ${REPO_DIR}"
    [[ -f "$REPO_DIR/public/index.php" ]] || die "TRACS entry point not found under REPO_DIR/public."
    [[ -f "$REPO_DIR/config/database.php" ]] || die "TRACS database config not found."
    [[ -d "$REPO_DIR/config/migrations" ]] || die "TRACS migrations directory not found."

    current_branch="$(git -C "$REPO_DIR" branch --show-current)"
    [[ "$current_branch" == "$BRANCH" ]] || die "REPO_DIR is on '${current_branch}', expected '${BRANCH}'."

    dirty="$(git -C "$REPO_DIR" status --porcelain --untracked-files=normal)"
    if [[ -n "$dirty" ]]; then
        if is_true "$ALLOW_DIRTY_REPO"; then
            log_warn "Deploying from a dirty repository because ALLOW_DIRTY_REPO=true."
        else
            die "REPO_DIR has uncommitted or untracked changes. Commit/stash them or set ALLOW_DIRTY_REPO=true after review."
        fi
    fi

    if git -C "$REPO_DIR" ls-files --error-unmatch .env >/dev/null 2>&1; then
        log_warn "The repository tracks .env. It is excluded from deployment, but should be removed from Git history/index."
    fi
}

update_repository() {
    if ! is_true "$UPDATE_REPO"; then
        log_warn "Skipping Git update because UPDATE_REPO=false."
        SOURCE_COMMIT="$(git -C "$REPO_DIR" rev-parse HEAD)"
        return 0
    fi

    log_info "Fetching ${GIT_REMOTE}/${BRANCH}."
    if is_true "$DRY_RUN"; then
        git -C "$REPO_DIR" fetch --dry-run "$GIT_REMOTE" "$BRANCH"
    else
        git -C "$REPO_DIR" fetch "$GIT_REMOTE" "$BRANCH"
        git -C "$REPO_DIR" merge --ff-only "${GIT_REMOTE}/${BRANCH}"
    fi
    SOURCE_COMMIT="$(git -C "$REPO_DIR" rev-parse HEAD)"
    log_success "Source commit: ${SOURCE_COMMIT}"
}

check_php_runtime() {
    local actual_version
    local loaded_modules
    local extension
    local missing=()

    actual_version="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
    [[ "$actual_version" == "$PHP_VERSION" ]] || die "PHP CLI is ${actual_version}; expected ${PHP_VERSION}."

    loaded_modules="$(php -m | tr '[:upper:]' '[:lower:]')"
    for extension in "${REQUIRED_PHP_EXTENSIONS[@]}"; do
        if ! grep -qx "$extension" <<<"$loaded_modules"; then
            missing+=("$extension")
        fi
    done
    if [[ ${#missing[@]} -gt 0 ]]; then
        die "Missing PHP extensions: ${missing[*]}"
    fi
    log_success "PHP ${actual_version} and required extensions are available."
}

lint_php_tree() {
    local root="$1"
    local file
    local count=0

    log_info "Checking PHP syntax under ${root}."
    while IFS= read -r -d '' file; do
        php -l "$file" >/dev/null
        count=$((count + 1))
    done < <(
        find \
            "$root/config" \
            "$root/core" \
            "$root/modules" \
            "$root/public" \
            "$root/bin" \
            -type f -name '*.php' -print0
    )
    [[ "$count" -gt 0 ]] || die "No PHP files found to validate under ${root}."
    log_success "PHP syntax valid for ${count} files."
}

build_mysql_args() {
    MYSQL_ARGS=()

    if [[ -n "$MYSQL_DEFAULTS_FILE" ]]; then
        [[ -f "$MYSQL_DEFAULTS_FILE" ]] || die "MYSQL_DEFAULTS_FILE does not exist."
        local defaults_mode
        local defaults_permissions
        defaults_mode="$(stat -c '%a' "$MYSQL_DEFAULTS_FILE")"
        defaults_permissions=$((8#$defaults_mode))
        if (( defaults_permissions & 077 )); then
            die "MYSQL_DEFAULTS_FILE must use mode 600 or stricter."
        fi
        MYSQL_ARGS+=("--defaults-extra-file=${MYSQL_DEFAULTS_FILE}")
    fi
    if [[ -n "$MYSQL_LOGIN_PATH" ]]; then
        MYSQL_ARGS+=("--login-path=${MYSQL_LOGIN_PATH}")
    fi
    MYSQL_ARGS+=(
        "--host=${DB_HOST}"
        "--port=${DB_PORT}"
        "--user=${DB_USER}"
        "--default-character-set=utf8mb4"
    )
}

check_database_connection() {
    build_mysql_args
    if mysql "${MYSQL_ARGS[@]}" --batch --skip-column-names "$DB_NAME" -e 'SELECT 1' >/dev/null 2>&1; then
        MYSQL_CONNECTION_READY="true"
        log_success "Database connection is available for backup/migration."
        return 0
    fi

    MYSQL_CONNECTION_READY="false"
    if { is_true "$BACKUP_DATABASE" && is_true "$REQUIRE_DB_BACKUP"; } || is_true "$RUN_MIGRATION"; then
        die "Cannot connect to database '${DB_NAME}'. Configure MYSQL_LOGIN_PATH, MYSQL_DEFAULTS_FILE, or ~/.my.cnf."
    fi
    log_warn "Database connection unavailable; database backup will be skipped."
}

check_services_and_nginx() {
    local nginx_site="/etc/nginx/sites-enabled/${NGINX_SITE_NAME}"
    local nginx_dump

    systemctl is-active --quiet nginx || die "Nginx is not active."
    systemctl is-active --quiet "$PHP_FPM_SERVICE" || die "${PHP_FPM_SERVICE} is not active."
    run_privileged nginx -t

    if [[ ! -e "$nginx_site" ]]; then
        log_warn "Expected enabled Nginx site not found: ${nginx_site}"
    fi

    nginx_dump="$(run_privileged nginx -T 2>/dev/null)"
    if grep -Fq "$PUBLIC_ROOT" <<<"$nginx_dump"; then
        log_success "Nginx configuration references ${PUBLIC_ROOT}."
    else
        log_warn "Nginx configuration does not appear to reference ${PUBLIC_ROOT}."
    fi
    if ! grep -Eq 'uploads.*(deny all|\\.php|phtml|phar)' <<<"$nginx_dump"; then
        log_warn "Nginx upload deny rules were not detected. Compare the active site with docs/nginx-tracs.conf.example."
    fi
    if ! grep -Eq '(location.*\\/includes/|location.*\\/modules/)' <<<"$nginx_dump"; then
        log_warn "Nginx internal include/module deny rules were not detected."
    fi
}

check_debug_configuration() {
    local file

    for file in "$WEB_ROOT/.env" "$WEB_ROOT/config/.env"; do
        [[ -f "$file" ]] || continue
        if grep -Eiq '^[[:space:]]*(APP_ENV[[:space:]]*=[[:space:]]*(local|development|dev)|APP_DEBUG[[:space:]]*=[[:space:]]*(1|true|yes|on)|DEBUG[[:space:]]*=[[:space:]]*(1|true|yes|on))' "$file"; then
            log_warn "Debug/development mode appears enabled in ${file}."
        fi
    done
}

validate_migration_file() {
    local candidate
    local migration_root

    is_true "$RUN_MIGRATION" || return 0
    [[ -n "$MIGRATION_FILE" ]] || die "RUN_MIGRATION=true requires MIGRATION_FILE or --with-migration."

    migration_root="$(canonical_path "$REPO_DIR/config/migrations")"
    if [[ "$MIGRATION_FILE" == /* ]]; then
        candidate="$(canonical_path "$MIGRATION_FILE")"
    elif [[ "$MIGRATION_FILE" == config/migrations/* ]]; then
        candidate="$(canonical_path "$REPO_DIR/$MIGRATION_FILE")"
    else
        candidate="$(canonical_path "$migration_root/$MIGRATION_FILE")"
    fi

    [[ -f "$candidate" ]] || die "Migration file not found: ${candidate}"
    path_is_within "$candidate" "$migration_root" || die "Migration must be inside config/migrations."
    [[ "$candidate" == *.sql ]] || die "Migration file must end in .sql."
    MIGRATION_FILE="$candidate"

    if grep -Eiq '(^|[;[:space:]])(DROP[[:space:]]+(DATABASE|TABLE)|TRUNCATE([[:space:]]+TABLE)?|DELETE[[:space:]]+FROM|ALTER[[:space:]]+TABLE[^;]*(DROP[[:space:]]+COLUMN))' "$MIGRATION_FILE"; then
        if is_true "$ALLOW_DESTRUCTIVE_MIGRATION"; then
            log_warn "Migration contains potentially destructive SQL and ALLOW_DESTRUCTIVE_MIGRATION=true."
        else
            die "Migration contains potentially destructive SQL. Review it and set ALLOW_DESTRUCTIVE_MIGRATION=true only if intentional."
        fi
    fi
}

ensure_deployment_directories() {
    run_privileged install -d -m 0700 -o "$DEPLOY_USER" -g "$DEPLOY_GROUP" "$BACKUP_DIR"
    run_privileged install -d -m 0755 -o "$DEPLOY_USER" -g "$WEB_GROUP" "$WEB_ROOT"
}

check_backup_capacity() {
    local available_kb
    local web_kb=0
    local database_bytes=0
    local database_kb=0
    local required_kb

    if is_true "$DRY_RUN" && [[ ! -d "$BACKUP_DIR" ]]; then
        log_warn "Dry-run: backup disk capacity cannot be measured until ${BACKUP_DIR} exists."
        return 0
    fi

    if [[ -d "$WEB_ROOT" ]]; then
        if [[ "$(id -u)" -eq 0 ]]; then
            web_kb="$(du -sk "$WEB_ROOT" | awk '{print $1}')"
        else
            web_kb="$(sudo du -sk "$WEB_ROOT" | awk '{print $1}')"
        fi
    fi

    if is_true "$MYSQL_CONNECTION_READY"; then
        database_bytes="$(
            mysql "${MYSQL_ARGS[@]}" --batch --skip-column-names "$DB_NAME" \
                -e "SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE();" \
                2>/dev/null || printf '0'
        )"
        [[ "$database_bytes" =~ ^[0-9]+$ ]] || database_bytes=0
        database_kb=$(( (database_bytes + 1023) / 1024 ))
    fi

    available_kb="$(df -Pk "$BACKUP_DIR" | awk 'NR == 2 {print $4}')"
    [[ "$available_kb" =~ ^[0-9]+$ ]] || die "Unable to determine free space for ${BACKUP_DIR}."

    # Leave 512 MiB beyond the estimated uncompressed application and database.
    required_kb=$((web_kb + database_kb + 524288))
    if (( available_kb < required_kb )); then
        die "Insufficient backup space: need about ${required_kb} KiB, have ${available_kb} KiB."
    fi
    log_success "Backup filesystem has sufficient estimated free space."
}

create_backup() {
    local backup_id="${1:-$(date '+%Y%m%d-%H%M%S')}"
    local include_database="${2:-$BACKUP_DATABASE}"
    local backup_path="${BACKUP_DIR}/${backup_id}"
    local metadata_file="${backup_path}/metadata.txt"

    log_info "Creating backup ${backup_id}."
    [[ ! -e "$backup_path" && ! -L "$backup_path" ]] || die "Backup path already exists: ${backup_path}"
    if is_true "$DRY_RUN"; then
        CURRENT_BACKUP_ID="$backup_id"
        CURRENT_BACKUP_PATH="$backup_path"
        if [[ -d "$WEB_ROOT" && -n "$(find "$WEB_ROOT" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
            CURRENT_BACKUP_HAS_WEBROOT="true"
        fi
        print_command mkdir -p "$backup_path"
        if [[ -d "$WEB_ROOT" ]]; then
            print_command tar -C "$WEB_ROOT" -czf "${backup_path}/webroot.tar.gz" .
        fi
        if is_true "$include_database"; then
            printf '  + mysqldump [credentials hidden] %q | gzip > %q\n' "$DB_NAME" "${backup_path}/database.sql.gz"
        fi
        return 0
    fi

    mkdir -p "$backup_path"
    chmod 0700 "$backup_path"

    {
        printf 'app=%s\n' "$APP_NAME"
        printf 'created_at=%s\n' "$(date --iso-8601=seconds)"
        printf 'branch=%s\n' "$BRANCH"
        printf 'source_commit=%s\n' "${SOURCE_COMMIT:-unknown}"
        printf 'web_root=%s\n' "$WEB_ROOT"
        printf 'database=%s\n' "$DB_NAME"
        if is_true "$RUN_MIGRATION"; then
            printf 'migration=%s\n' "$(basename "$MIGRATION_FILE")"
        else
            printf 'migration=none\n'
        fi
    } >"$metadata_file"
    chmod 0600 "$metadata_file"

    if [[ -d "$WEB_ROOT" && -n "$(find "$WEB_ROOT" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
        run_privileged tar -C "$WEB_ROOT" -czf "${backup_path}/webroot.tar.gz" .
        run_privileged chown "$DEPLOY_USER:$DEPLOY_GROUP" "${backup_path}/webroot.tar.gz"
        chmod 0600 "${backup_path}/webroot.tar.gz"
        CURRENT_BACKUP_HAS_WEBROOT="true"
    else
        printf 'No previous web root existed.\n' >"${backup_path}/no-webroot.txt"
        chmod 0600 "${backup_path}/no-webroot.txt"
        CURRENT_BACKUP_HAS_WEBROOT="false"
    fi

    if is_true "$include_database"; then
        if is_true "$MYSQL_CONNECTION_READY"; then
            mysqldump \
                "${MYSQL_ARGS[@]}" \
                --single-transaction \
                --quick \
                --routines \
                --triggers \
                --events \
                --hex-blob \
                --no-tablespaces \
                "$DB_NAME" | gzip -9 >"${backup_path}/database.sql.gz"
            chmod 0600 "${backup_path}/database.sql.gz"
        elif is_true "$REQUIRE_DB_BACKUP"; then
            die "Database backup is required but the connection is unavailable."
        else
            log_warn "Skipping database backup because no database connection is available."
        fi
    fi

    (
        cd "$backup_path"
        find . -maxdepth 1 -type f ! -name SHA256SUMS ! -name SHA256SUMS.tmp -print0 |
            sort -z |
            xargs -0 sha256sum
    ) >"${backup_path}/SHA256SUMS.tmp"
    mv "${backup_path}/SHA256SUMS.tmp" "${backup_path}/SHA256SUMS"
    chmod 0600 "${backup_path}/SHA256SUMS"
    CURRENT_BACKUP_ID="$backup_id"
    CURRENT_BACKUP_PATH="$backup_path"
    log_success "Backup stored at ${backup_path}."
}

run_migration() {
    is_true "$RUN_MIGRATION" || {
        log_info "No migration selected. Existing databases may require manual schema review."
        return 0
    }

    log_warn "TRACS has no migration ledger. Confirm this file has not already been applied:"
    log_warn "  ${MIGRATION_FILE}"
    confirm "Run migration $(basename "$MIGRATION_FILE") against ${DB_NAME}?"

    if is_true "$DRY_RUN"; then
        printf '  + mysql [credentials hidden] %q < %q\n' "$DB_NAME" "$MIGRATION_FILE"
        return 0
    fi

    mysql "${MYSQL_ARGS[@]}" "$DB_NAME" <"$MIGRATION_FILE"
    log_success "Migration applied: $(basename "$MIGRATION_FILE")."
}

rsync_exclude_args() {
    local pattern
    RSYNC_EXCLUDE_ARGS=()
    for pattern in "${RSYNC_EXCLUDES[@]}"; do
        RSYNC_EXCLUDE_ARGS+=("--exclude=${pattern}")
    done
}

sync_application() {
    local preserved

    rsync_exclude_args
    log_info "Synchronizing application files to ${WEB_ROOT}."
    for preserved in "${PRESERVED_PATHS[@]}"; do
        log_info "Preserving live path: ${WEB_ROOT}/${preserved}"
    done

    run_privileged rsync \
        -rlpt \
        --safe-links \
        --delete-delay \
        --delay-updates \
        --itemize-changes \
        "${RSYNC_EXCLUDE_ARGS[@]}" \
        "${REPO_DIR}/" \
        "${WEB_ROOT}/"

    if [[ ! -f "$WEB_ROOT/config/database.php" ]]; then
        log_warn "No production config/database.php exists; installing the repository default."
        run_privileged install \
            -m 0640 \
            -o "$DEPLOY_USER" \
            -g "$WEB_GROUP" \
            "$REPO_DIR/config/database.php" \
            "$WEB_ROOT/config/database.php"
    fi
}

ensure_runtime_paths() {
    local relative
    local source_guard
    local target_guard

    for relative in "${RUNTIME_DIRS[@]}"; do
        run_privileged install -d -m 0750 -o "$WEB_USER" -g "$WEB_GROUP" "$WEB_ROOT/$relative"
    done

    for relative in "${PRIVATE_READ_DIRS[@]}"; do
        run_privileged install -d -m 0750 -o "$DEPLOY_USER" -g "$WEB_GROUP" "$WEB_ROOT/$relative"
    done

    # Keep Apache guard/index files current without copying development uploads.
    for relative in \
        "public/uploads/.htaccess" \
        "public/uploads/avatars/.htaccess" \
        "public/uploads/avatars/index.html" \
        "public/uploads/case_attachments/.htaccess" \
        "public/uploads/case_attachments/index.html" \
        "public/uploads/mom/.htaccess" \
        "public/uploads/mom/index.html" \
        "public/uploads/shift_report_attachments/.htaccess" \
        "public/uploads/shift_report_attachments/index.html"
    do
        source_guard="$REPO_DIR/$relative"
        target_guard="$WEB_ROOT/$relative"
        if [[ -f "$source_guard" ]]; then
            run_privileged install -m 0640 -o "$WEB_USER" -g "$WEB_GROUP" "$source_guard" "$target_guard"
        fi
    done
}

set_permissions() {
    local sensitive
    local runtime
    local private_dir

    log_info "Applying scoped production permissions."
    run_privileged chown -R "$DEPLOY_USER:$WEB_GROUP" "$WEB_ROOT"
    run_privileged find "$WEB_ROOT" -type d -exec chmod 0755 {} +
    run_privileged find "$WEB_ROOT" -type f -exec chmod 0644 {} +

    if [[ -f "$WEB_ROOT/deploy.sh" ]]; then
        run_privileged chmod 0750 "$WEB_ROOT/deploy.sh"
    fi

    for sensitive in ".env" "config/.env" "config/database.php"; do
        if [[ -f "$WEB_ROOT/$sensitive" ]]; then
            run_privileged chown "$DEPLOY_USER:$WEB_GROUP" "$WEB_ROOT/$sensitive"
            run_privileged chmod 0640 "$WEB_ROOT/$sensitive"
        fi
    done

    for runtime in "${RUNTIME_DIRS[@]}"; do
        if [[ -d "$WEB_ROOT/$runtime" ]]; then
            run_privileged chown -R "$WEB_USER:$WEB_GROUP" "$WEB_ROOT/$runtime"
            run_privileged find "$WEB_ROOT/$runtime" -type d -exec chmod 0750 {} +
            run_privileged find "$WEB_ROOT/$runtime" -type f -exec chmod 0640 {} +
        fi
    done

    for private_dir in "${PRIVATE_READ_DIRS[@]}"; do
        if [[ -d "$WEB_ROOT/$private_dir" ]]; then
            run_privileged chown -R "$DEPLOY_USER:$WEB_GROUP" "$WEB_ROOT/$private_dir"
            run_privileged find "$WEB_ROOT/$private_dir" -type d -exec chmod 0750 {} +
            run_privileged find "$WEB_ROOT/$private_dir" -type f -exec chmod 0640 {} +
        fi
    done
}

write_deployment_metadata() {
    local metadata_dir="$WEB_ROOT/storage/deployment"
    local metadata_file="$metadata_dir/deployment.meta"
    local temp_file
    local version="$APP_VERSION"

    if [[ -z "$version" && -f "$REPO_DIR/VERSION" ]]; then
        version="$(head -n 1 "$REPO_DIR/VERSION" | tr -cd 'A-Za-z0-9._-')"
    fi
    if [[ -z "$version" && -f "$REPO_DIR/core/build_signature.php" ]]; then
        version="$(
            php -r \
                'require $argv[1]; echo defined("TRACS_BUILD_VERSION") ? TRACS_BUILD_VERSION : "";' \
                "$REPO_DIR/core/build_signature.php"
        )"
    fi
    if [[ -z "$version" ]]; then
        version="unknown"
    fi
    [[ "$version" =~ ^[A-Za-z0-9._-]{1,80}$ ]] || die "APP_VERSION contains unsupported characters."
    [[ "${SOURCE_COMMIT:-}" =~ ^[a-f0-9]{40}$ ]] || die "Source commit is unavailable for deployment metadata."

    if is_true "$DRY_RUN"; then
        print_command install -m 0640 -o "$DEPLOY_USER" -g "$WEB_GROUP" "[safe deployment metadata]" "$metadata_file"
        return 0
    fi

    temp_file="$(mktemp)"
    {
        printf 'deployed_at=%s\n' "$(date --iso-8601=seconds)"
        printf 'commit=%s\n' "$SOURCE_COMMIT"
        printf 'version=%s\n' "$version"
    } >"$temp_file"
    run_privileged install -m 0640 -o "$DEPLOY_USER" -g "$WEB_GROUP" "$temp_file" "$metadata_file"
    rm -f -- "$temp_file"
}

security_scan() {
    local dangerous
    local executable_upload
    local world_writable
    local invalid_runtime_modes
    local relative
    local actual

    if is_true "$DRY_RUN"; then
        log_info "Dry-run: public-file and permission checks will run after a real sync."
        return 0
    fi

    [[ -d "$PUBLIC_ROOT" ]] || die "Public root missing after sync: ${PUBLIC_ROOT}"

    dangerous="$(
        run_privileged find "$PUBLIC_ROOT" -type f \
            \( -name '.env' -o -name '*.sql' -o -name '*.bak' -o -name '*.backup' \
               -o -name '*.old' -o -name '*.orig' -o -name '*.swp' -o -name 'Dockerfile*' \
               -o -name 'docker-compose*.yml' -o -name '.DS_Store' -o -name '*.tmp' \
               -o -name '*~' -o -name '*.log' -o -name '*.ini' -o -name '*.conf' \
               -o -name '*.yml' -o -name '*.yaml' -o -name '*.md' -o -name '*.sh' \
               -o -iname '*backup*' -o -iname '* copy*' -o -iname 'phpinfo*.php' \
               -o -iname 'info.php' -o -iname 'test*.php' \) \
            -print
    )"
    [[ -z "$dangerous" ]] || die "Dangerous files found under public/: ${dangerous//$'\n'/, }"

    executable_upload="$(
        run_privileged find "$PUBLIC_ROOT/uploads" -type f \
            \( -iname '*.php' -o -iname '*.phtml' -o -iname '*.phar' -o -iname '*.cgi' \
               -o -iname '*.pl' -o -iname '*.py' -o -iname '*.sh' \) \
            -print
    )"
    [[ -z "$executable_upload" ]] || die "Executable files found under uploads/: ${executable_upload//$'\n'/, }"

    world_writable="$(run_privileged find "$WEB_ROOT" -xdev -perm -0002 -print)"
    [[ -z "$world_writable" ]] || die "World-writable paths found: ${world_writable//$'\n'/, }"

    for relative in "${RUNTIME_DIRS[@]}"; do
        [[ -d "$WEB_ROOT/$relative" ]] || die "Runtime directory missing: ${relative}"
        actual="$(run_privileged stat -c '%a:%U:%G' "$WEB_ROOT/$relative")"
        [[ "$actual" == "750:${WEB_USER}:${WEB_GROUP}" ]] || die "Runtime directory permission mismatch for ${relative}: ${actual}"
        invalid_runtime_modes="$(
            run_privileged find "$WEB_ROOT/$relative" \
                \( -type d ! -perm 0750 -o -type f ! -perm 0640 \) -print
        )"
        [[ -z "$invalid_runtime_modes" ]] || die "Runtime path mode mismatch under ${relative}: ${invalid_runtime_modes//$'\n'/, }"
    done

    for relative in "${PRIVATE_READ_DIRS[@]}"; do
        [[ -d "$WEB_ROOT/$relative" ]] || die "Private monitoring directory missing: ${relative}"
        actual="$(run_privileged stat -c '%a:%U:%G' "$WEB_ROOT/$relative")"
        [[ "$actual" == "750:${DEPLOY_USER}:${WEB_GROUP}" ]] || die "Private directory permission mismatch for ${relative}: ${actual}"
    done

    if [[ -f "$WEB_ROOT/deploy.sh" ]]; then
        actual="$(run_privileged stat -c '%a:%U:%G' "$WEB_ROOT/deploy.sh")"
        [[ "$actual" == "750:${DEPLOY_USER}:${WEB_GROUP}" ]] || die "deploy.sh permission mismatch: ${actual}"
    fi
    actual="$(run_privileged stat -c '%a:%U:%G' "$BACKUP_DIR")"
    [[ "$actual" == "700:${DEPLOY_USER}:${DEPLOY_GROUP}" ]] || die "Backup directory permission mismatch: ${actual}"

    check_debug_configuration
    log_success "Public-file and permission security checks passed."
}

reload_services() {
    if is_true "$SKIP_SERVICE_RELOAD"; then
        log_warn "Skipping service reload because SKIP_SERVICE_RELOAD=true."
        return 0
    fi

    run_privileged nginx -t
    run_privileged systemctl reload "$PHP_FPM_SERVICE"
    run_privileged systemctl reload nginx
    log_success "Reloaded ${PHP_FPM_SERVICE} and Nginx."
}

health_check() {
    local url
    local host_args=()
    local attempt
    local status="000"

    if [[ -n "$HEALTHCHECK_URL" ]]; then
        url="$HEALTHCHECK_URL"
    elif [[ -n "$DOMAIN" ]]; then
        url="https://${DOMAIN}${HEALTHCHECK_PATH}"
    else
        url="http://127.0.0.1${HEALTHCHECK_PATH}"
        host_args=(-H "Host: ${HEALTHCHECK_HOST}")
    fi

    if is_true "$DRY_RUN"; then
        print_command curl --max-time "$HEALTHCHECK_TIMEOUT" "${host_args[@]}" "$url"
        return 0
    fi

    log_info "Running health check: ${url}"
    for ((attempt = 1; attempt <= HEALTHCHECK_RETRIES; attempt++)); do
        status="$(
            curl \
                --silent \
                --show-error \
                --output /dev/null \
                --write-out '%{http_code}' \
                --max-time "$HEALTHCHECK_TIMEOUT" \
                "${host_args[@]}" \
                "$url" || true
        )"
        if [[ "$status" =~ ^[23][0-9][0-9]$ ]]; then
            log_success "Health check passed with HTTP ${status}."
            return 0
        fi
        log_warn "Health check attempt ${attempt}/${HEALTHCHECK_RETRIES} returned HTTP ${status}."
        sleep 2
    done

    die "Health check failed. Review Nginx/PHP-FPM logs and roll back application files if needed."
}

apply_retention() {
    [[ -n "$BACKUP_RETENTION_DAYS" ]] || return 0

    log_warn "Removing timestamped backups older than ${BACKUP_RETENTION_DAYS} days."
    if is_true "$DRY_RUN"; then
        print_command find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name '????????-??????' -mtime "+${BACKUP_RETENTION_DAYS}" -print
        return 0
    fi

    while IFS= read -r -d '' old_backup; do
        log_warn "Removing expired backup: ${old_backup}"
        rm -rf -- "$old_backup"
    done < <(
        find "$BACKUP_DIR" \
            -mindepth 1 \
            -maxdepth 1 \
            -type d \
            -name '????????-??????' \
            -mtime "+${BACKUP_RETENTION_DAYS}" \
            -print0
    )
}

run_check() {
    check_dependencies
    check_repository
    check_php_runtime
    validate_migration_file
    lint_php_tree "$REPO_DIR"
    check_database_connection
    check_services_and_nginx
    check_debug_configuration
    log_success "Preflight checks completed."
}

run_deploy() {
    check_dependencies
    check_repository
    check_php_runtime
    check_database_connection
    check_services_and_nginx
    update_repository
    validate_migration_file
    lint_php_tree "$REPO_DIR"
    ensure_deployment_directories
    check_backup_capacity

    log_info "Deployment target: ${WEB_ROOT} (Nginx root: ${PUBLIC_ROOT})."
    confirm "Deploy ${APP_NAME} commit ${SOURCE_COMMIT:0:12} to ${WEB_ROOT}?"

    create_backup
    run_migration
    sync_application
    ensure_runtime_paths
    set_permissions
    write_deployment_metadata
    security_scan
    reload_services
    health_check
    apply_retention

    log_success "${APP_NAME} deployment completed."
    log_info "Backup ID: ${CURRENT_BACKUP_ID}"
    if [[ "$CURRENT_BACKUP_HAS_WEBROOT" == "true" ]]; then
        log_info "Rollback: $0 rollback ${CURRENT_BACKUP_ID}"
    else
        log_warn "This was an initial deployment; no previous application tree exists for rollback."
    fi
}

validate_rollback_id() {
    [[ "$ROLLBACK_ID" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*$ ]] || die "Invalid backup ID."
    CURRENT_BACKUP_ID="$ROLLBACK_ID"
    CURRENT_BACKUP_PATH="$(canonical_path "$BACKUP_DIR/$ROLLBACK_ID")"
    path_is_within "$CURRENT_BACKUP_PATH" "$BACKUP_DIR" || die "Backup path escaped BACKUP_DIR."
    [[ -d "$CURRENT_BACKUP_PATH" ]] || die "Backup not found: ${CURRENT_BACKUP_PATH}"
    [[ -f "$CURRENT_BACKUP_PATH/webroot.tar.gz" ]] || die "Backup has no webroot.tar.gz."
}

validate_backup_archive() {
    local archive="$1"
    local unsafe_entry

    tar -tzf "$archive" >/dev/null || die "Backup archive is unreadable: ${archive}"
    unsafe_entry="$(
        tar -tzf "$archive" |
            grep -E '(^/|(^|/)\.\.(/|$))' |
            head -n 1 || true
    )"
    [[ -z "$unsafe_entry" ]] || die "Backup archive contains an unsafe path: ${unsafe_entry}"
}

restore_application_backup() {
    local safety_id
    local restore_id
    local restore_path

    validate_rollback_id
    restore_id="$ROLLBACK_ID"
    restore_path="$CURRENT_BACKUP_PATH"
    validate_backup_archive "$restore_path/webroot.tar.gz"

    if [[ -f "$restore_path/SHA256SUMS" ]]; then
        (
            cd "$restore_path"
            sha256sum -c SHA256SUMS
        )
    fi

    confirm "Restore application files from backup ${restore_id}?"
    safety_id="pre-rollback-$(date '+%Y%m%d-%H%M%S')"
    create_backup "$safety_id" "false"

    TEMP_DIR="$(mktemp -d)"
    if is_true "$DRY_RUN"; then
        print_command tar -xzf "$restore_path/webroot.tar.gz" -C "$TEMP_DIR"
        log_info "Dry-run rollback stops before validating the unextracted backup tree."
        return 0
    fi

    tar -xzf "$restore_path/webroot.tar.gz" -C "$TEMP_DIR"
    lint_php_tree "$TEMP_DIR"

    rsync_exclude_args
    run_privileged rsync \
        -rlpt \
        --safe-links \
        --delete-delay \
        --delay-updates \
        --itemize-changes \
        "${RSYNC_EXCLUDE_ARGS[@]}" \
        "${TEMP_DIR}/" \
        "${WEB_ROOT}/"

    ensure_runtime_paths
    set_permissions
    security_scan
    reload_services
    health_check

    log_success "Application files restored from ${restore_id}."
    log_warn "Current secrets, uploads, cache, logs, and database were not rolled back."
}

run_rollback() {
    check_dependencies
    check_php_runtime
    check_services_and_nginx
    ensure_deployment_directories
    check_backup_capacity
    restore_application_backup
}

main() {
    parse_args "$@"
    require_command realpath
    validate_scalar_config
    validate_paths
    check_os
    check_operator
    acquire_lock

    case "$MODE" in
        check) run_check ;;
        deploy) run_deploy ;;
        rollback) run_rollback ;;
        *) die "Unsupported mode: ${MODE}" ;;
    esac
}

main "$@"
