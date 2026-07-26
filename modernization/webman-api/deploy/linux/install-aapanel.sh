#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
PACKAGE_ROOT="$(cd "${BACKEND_ROOT}/.." && pwd)"
SOURCE_CONSOLE_ROOT="${PACKAGE_ROOT}/console"

DOMAIN=""
SITE_NAME=""
BACKEND_PORT=""
PUBLIC_ROOT=""
NGINX_CONF_PATH=""
REWRITE_CONF_PATH=""
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME=""
DB_USER=""
DB_PASSWORD=""
ADMIN_USER="adminroot"
ADMIN_PASSWORD=""
ADMIN_NICKNAME="AiPayAdmin"
MERCHANT_ENABLED=1
MERCHANT_USER="merchantdemo"
MERCHANT_PASSWORD=""
MERCHANT_EMAIL=""
MERCHANT_NAME="Demo Merchant"
CERTBOT_EMAIL=""
CERTBOT_NO_EMAIL=0
APPLY_NGINX=1
INSTALL_DEPS=0
NON_INTERACTIVE=0
BACKEND_USER=""
BACKEND_GROUP=""
FRONTEND_SCHEME=""

usage() {
  cat <<'TEXT'
Usage:
  bash deploy/linux/install-aapanel.sh [options]

Options:
  --domain=DOMAIN              Public domain. Required in non-interactive mode.
  --public-root=PATH           aaPanel public directory. Default: /www/wwwroot/<domain>
  --nginx-conf=PATH            aaPanel nginx conf path. Default: /www/server/panel/vhost/nginx/<domain>.conf
  --rewrite-conf=PATH          aaPanel rewrite conf path. Default: /www/server/panel/vhost/rewrite/<domain>.conf
  --site-name=NAME             systemd service name prefix. Default: derived from domain.
  --backend-port=PORT          Local Webman port. Default: 8787
  --db-host=HOST               Database host. Default: 127.0.0.1
  --db-port=PORT               Database port. Default: 3306
  --db-name=NAME               Database name. Default: pay
  --db-user=USER               Database user. Default: same as db name
  --db-password=PASS           Database password. Default: auto-generate
  --admin-user=NAME            Admin username. Default: adminroot
  --admin-password=PASS        Admin password. Default: auto-generate
  --admin-nickname=NAME        Admin nickname. Default: AiPayAdmin
  --merchant-user=NAME         Demo merchant username. Default: merchantdemo
  --merchant-password=PASS     Demo merchant password. Default: auto-generate
  --merchant-email=EMAIL       Demo merchant email. Default: <username>@aipay.local
  --merchant-name=NAME         Demo merchant name. Default: Demo Merchant
  --skip-merchant              Do not create a demo merchant account.
  --skip-nginx-apply           Only generate aaPanel nginx conf, do not overwrite live conf.
  --certbot-email=EMAIL        Enable HTTPS with certbot using this email.
  --certbot-no-email           Enable HTTPS with certbot and no email.
  --frontend-scheme=SCHEME     Frontend public scheme written into .env and verification URLs. Allowed: http, https.
  --install-deps               Auto-install Debian/Ubuntu dependencies when missing.
  --non-interactive            Fail instead of prompting for missing values.
  --help                       Show this help message.
TEXT
}

log() {
  printf '[INFO] %s\n' "$1"
}

fail() {
  printf '[FAIL] %s\n' "$1" >&2
  exit 1
}

require_root() {
  if [[ "${EUID}" -ne 0 ]]; then
    fail 'Please run this script as root.'
  fi
}

command_exists() {
  command -v "$1" >/dev/null 2>&1
}

prompt_value() {
  local variable_name="$1"
  local prompt_text="$2"
  local default_value="$3"
  local current_value="${!variable_name}"

  if [[ -n "${current_value}" ]]; then
    return
  fi

  if [[ "${NON_INTERACTIVE}" -eq 1 ]]; then
    if [[ -z "${default_value}" ]]; then
      fail "${variable_name} is required in non-interactive mode."
    fi
    printf -v "${variable_name}" '%s' "${default_value}"
    return
  fi

  local answer=""
  if [[ -n "${default_value}" ]]; then
    read -r -p "${prompt_text} [${default_value}]: " answer
    answer="${answer:-${default_value}}"
  else
    read -r -p "${prompt_text}: " answer
  fi
  printf -v "${variable_name}" '%s' "${answer}"
}

prompt_secret() {
  local variable_name="$1"
  local prompt_text="$2"
  local current_value="${!variable_name}"

  if [[ -n "${current_value}" ]]; then
    return
  fi

  if [[ "${NON_INTERACTIVE}" -eq 1 ]]; then
    return
  fi

  local answer=""
  read -r -s -p "${prompt_text} (leave empty to auto-generate): " answer
  printf '\n'
  printf -v "${variable_name}" '%s' "${answer}"
}

prompt_yes_no() {
  local variable_name="$1"
  local prompt_text="$2"
  local default_value="$3"
  local current_value="${!variable_name}"

  if [[ "${NON_INTERACTIVE}" -eq 1 ]]; then
    return
  fi

  if [[ -n "${current_value}" ]]; then
    return
  fi

  local answer=""
  read -r -p "${prompt_text} [${default_value}]: " answer
  answer="${answer:-${default_value}}"
  printf -v "${variable_name}" '%s' "${answer}"
}

sanitize_site_name() {
  local raw="$1"
  raw="${raw,,}"
  raw="${raw//./-}"
  raw="$(printf '%s' "${raw}" | tr -cd 'a-z0-9-_')"
  if [[ -z "${raw}" ]]; then
    raw='aipay-aapanel'
  fi
  printf '%s' "${raw}"
}

generate_secret() {
  php -r "echo bin2hex(random_bytes(${1}));"
}

ensure_package_layout() {
  [[ -d "${BACKEND_ROOT}" ]] || fail "Backend root not found: ${BACKEND_ROOT}"
  [[ -d "${SOURCE_CONSOLE_ROOT}" ]] || fail "Console root not found: ${SOURCE_CONSOLE_ROOT}"
  [[ -f "${BACKEND_ROOT}/start.php" ]] || fail "start.php not found under ${BACKEND_ROOT}"
  [[ -f "${SOURCE_CONSOLE_ROOT}/index.html" ]] || fail "index.html not found under ${SOURCE_CONSOLE_ROOT}"
}

install_dependencies_apt() {
  export DEBIAN_FRONTEND=noninteractive
  apt-get update
  apt-get install -y \
    nginx \
    mariadb-server \
    mariadb-client \
    redis-server \
    rsync \
    certbot \
    python3-certbot-nginx \
    php-cli \
    php-mysql \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    php-bcmath \
    php-intl \
    php-gd \
    php-redis \
    php-opcache
}

ensure_dependencies() {
  local missing=()
  local required=(php nginx systemctl rsync redis-cli)

  for command_name in "${required[@]}"; do
    if ! command_exists "${command_name}"; then
      missing+=("${command_name}")
    fi
  done

  if ! command_exists mariadb && ! command_exists mysql; then
    missing+=('mariadb/mysql-client')
  fi

  if [[ "${missing[*]-}" == "" ]]; then
    return
  fi

  if [[ "${INSTALL_DEPS}" -eq 1 ]]; then
    if command_exists apt-get; then
      log "Installing missing dependencies: ${missing[*]}"
      install_dependencies_apt
      return
    fi
    fail "Missing dependencies (${missing[*]}) and automatic installation is only implemented for apt-based systems."
  fi

  if [[ "${NON_INTERACTIVE}" -eq 0 && "$(command -v apt-get || true)" != "" ]]; then
    local answer=""
    read -r -p "Missing dependencies: ${missing[*]}. Auto-install now? [Y/n]: " answer
    answer="${answer:-Y}"
    if [[ "${answer}" =~ ^[Yy]$ ]]; then
      install_dependencies_apt
      return
    fi
  fi

  fail "Missing dependencies: ${missing[*]}. Re-run with --install-deps or install them manually first."
}

db_client() {
  if command_exists mariadb; then
    printf '%s' 'mariadb'
    return
  fi

  if command_exists mysql; then
    printf '%s' 'mysql'
    return
  fi

  fail 'Neither mariadb nor mysql client is available.'
}

systemd_unit_exists() {
  local unit_name="$1"
  local unit_listing=""
  unit_listing="$(systemctl list-unit-files "${unit_name}" --no-legend 2>/dev/null || true)"
  [[ -n "${unit_listing//[[:space:]]/}" ]]
}

ensure_database_service() {
  if systemd_unit_exists 'mariadb.service'; then
    systemctl enable --now mariadb >/dev/null 2>&1 || true
    return
  fi

  if systemd_unit_exists 'mysql.service'; then
    systemctl enable --now mysql >/dev/null 2>&1 || true
  fi
}

ensure_redis_service() {
  local redis_unit=''

  if systemd_unit_exists 'redis-server.service'; then
    redis_unit='redis-server.service'
  elif systemd_unit_exists 'redis.service'; then
    redis_unit='redis.service'
  else
    fail 'Redis service is required but no redis systemd unit was found. Install redis-server first.'
  fi

  systemctl enable --now "${redis_unit}" >/dev/null 2>&1 || true

  if ! systemctl is-active --quiet "${redis_unit}"; then
    fail "Redis service failed to start: ${redis_unit}. If this is Debian 13 and redis reports a libjemalloc.so.2 mapping error, apply the systemd override documented in docs/aapanel-install.md and try again."
  fi

  if command_exists redis-cli; then
    redis-cli ping >/dev/null 2>&1 || fail 'Redis service is active but redis-cli ping failed. Check local bind/auth settings before continuing.'
  fi
}

detect_backend_runtime_user() {
  if [[ -n "${BACKEND_USER}" && -n "${BACKEND_GROUP}" ]]; then
    return
  fi

  if id -u www >/dev/null 2>&1; then
    BACKEND_USER="www"
    BACKEND_GROUP="www"
    return
  fi

  BACKEND_USER="www-data"
  BACKEND_GROUP="www-data"
}

is_aapanel_managed_nginx() {
  local nginx_bin=""
  nginx_bin="$(command -v nginx || true)"
  if [[ -z "${nginx_bin}" ]]; then
    return 1
  fi

  nginx_bin="$(readlink -f "${nginx_bin}" 2>/dev/null || printf '%s' "${nginx_bin}")"
  [[ "${nginx_bin}" == /www/server/nginx/* ]]
}

ensure_certbot_if_requested() {
  if [[ -z "${CERTBOT_EMAIL}" && "${CERTBOT_NO_EMAIL}" -eq 0 ]]; then
    return
  fi

  if is_aapanel_managed_nginx; then
    fail 'aaPanel managed Nginx does not support the installer certbot flow. Use aaPanel SSL, a Cloudflare origin certificate, or complete SSL after deployment.'
  fi

  if command_exists certbot; then
    return
  fi

  if [[ "${INSTALL_DEPS}" -eq 1 && "$(command -v apt-get || true)" != "" ]]; then
    log 'Installing certbot for HTTPS setup'
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y certbot python3-certbot-nginx
    return
  fi

  fail 'HTTPS was requested but certbot is not installed. Re-run with --install-deps or install certbot manually.'
}

validate_inputs() {
  [[ -n "${DOMAIN}" ]] || fail 'domain is required.'
  [[ -n "${PUBLIC_ROOT}" ]] || fail 'public root is required.'
  [[ -n "${NGINX_CONF_PATH}" ]] || fail 'nginx conf path is required.'
  [[ "${BACKEND_PORT}" =~ ^[0-9]+$ ]] || fail 'backend port must be numeric.'
  [[ "${DB_PORT}" =~ ^[0-9]+$ ]] || fail 'database port must be numeric.'
  if [[ -n "${FRONTEND_SCHEME}" && "${FRONTEND_SCHEME}" != 'http' && "${FRONTEND_SCHEME}" != 'https' ]]; then
    fail 'frontend scheme must be http or https.'
  fi

  if [[ "${DB_USER}" == *"'"* || "${DB_PASSWORD}" == *"'"* ]]; then
    fail "Database user and password cannot contain a single quote (')."
  fi
}

resolve_frontend_scheme() {
  if [[ -n "${FRONTEND_SCHEME}" ]]; then
    printf '%s' "${FRONTEND_SCHEME}"
    return
  fi

  if [[ -n "${CERTBOT_EMAIL}" || "${CERTBOT_NO_EMAIL}" -eq 1 ]]; then
    printf '%s' 'https'
    return
  fi

  printf '%s' 'http'
}

write_env_file() {
  local frontend_scheme=''
  local session_secure='false'
  frontend_scheme="$(resolve_frontend_scheme)"
  if [[ "${frontend_scheme}" == 'https' ]]; then
    session_secure='true'
  fi

  cat > "${BACKEND_ROOT}/.env" <<TEXT
APP_ENV=production
APP_DEBUG=false
APP_HOST=127.0.0.1
APP_PORT=${BACKEND_PORT}
APP_WORKER_COUNT=2
ENABLE_ORDER_CALLBACK_WORKER=true
ORDER_CALLBACK_WORKER_COUNT=2
ORDER_CALLBACK_POLL_INTERVAL=0.2
ORDER_CALLBACK_BATCH_SIZE=10
ENABLE_ORDER_RECONCILE_WORKER=true
ORDER_RECONCILE_WORKER_COUNT=1
ORDER_RECONCILE_POLL_INTERVAL=0.5
ORDER_RECONCILE_SEED_BATCH=50
ORDER_RECONCILE_PROCESS_BATCH=5
CALLBACK_HTTP_CONNECT_TIMEOUT=3
CALLBACK_HTTP_TIMEOUT=8
APP_FILE_MONITOR=false
APP_MEMORY_MONITOR=false

DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_POOL_MAX_CONNECTIONS=20
DB_POOL_MIN_CONNECTIONS=2
DB_POOL_WAIT_TIMEOUT=10
DB_POOL_IDLE_TIMEOUT=60
DB_POOL_HEARTBEAT_INTERVAL=50
PAYMENT_PLUGIN_CACHE_TTL=5
SYSTEM_CONFIG_CACHE_TTL=5
HOT_PATH_REDIS_ENABLE=true
HOT_PATH_REDIS_HOST=127.0.0.1
HOT_PATH_REDIS_PORT=6379
HOT_PATH_REDIS_PASSWORD=
HOT_PATH_REDIS_DB=1
HOT_PATH_REDIS_TIMEOUT=1
HOT_PATH_REDIS_PERSISTENT=true
HOT_PATH_REDIS_PREFIX=aipay:hot:
HOT_PATH_FILE_FALLBACK_ENABLE=false
SESSION_TYPE=redis
SESSION_REDIS_HOST=127.0.0.1
SESSION_REDIS_PORT=6379
SESSION_REDIS_PASSWORD=
SESSION_REDIS_DB=1
SESSION_REDIS_PREFIX=aipay:session:
SESSION_REDIS_TIMEOUT=1
SESSION_SECURE=${session_secure}
SESSION_SAME_SITE=lax
SESSION_REDIS_POOL_MAX_CONNECTIONS=20
SESSION_REDIS_POOL_MIN_CONNECTIONS=2
SESSION_REDIS_POOL_WAIT_TIMEOUT=10
SESSION_REDIS_POOL_IDLE_TIMEOUT=60
SESSION_REDIS_POOL_HEARTBEAT_INTERVAL=50
SOFTWARE_NONCE_REDIS_HOST=127.0.0.1
SOFTWARE_NONCE_REDIS_PORT=6379
SOFTWARE_NONCE_REDIS_PASSWORD=
SOFTWARE_NONCE_REDIS_DB=1
SOFTWARE_NONCE_REDIS_PREFIX=aipay:software:nonce:
SOFTWARE_NONCE_REDIS_TIMEOUT=1
SOFTWARE_NONCE_REDIS_PERSISTENT=true
SHARED_REDIS_RETRY_SECONDS=5
PUBLIC_AUTH_RATE_LIMIT_MAX=30
PUBLIC_AUTH_RATE_LIMIT_WINDOW=60
MERCHANT_LOGIN_RATE_LIMIT_MAX=20
MERCHANT_LOGIN_RATE_LIMIT_WINDOW=60
COMPAT_CODE_COOLDOWN_SECONDS=60
AIPAY_BIZ_TABLE_PREFIX=aipay_

AIPAY_ADMIN_FRONTEND_URL=${frontend_scheme}://${DOMAIN}
AIPAY_MERCHANT_FRONTEND_URL=${frontend_scheme}://${DOMAIN}
AIPAY_PUBLIC_FRONTEND_URL=${frontend_scheme}://${DOMAIN}
TEXT
}

create_database_and_user() {
  local db_cmd
  db_cmd="$(db_client)"

  "${db_cmd}" -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
}

initialize_database() {
  (
    cd "${BACKEND_ROOT}"
    bash deploy/linux/install-database.sh --with-base-schema
  )
}

bootstrap_accounts() {
  local args=(
    "deploy/shared/bootstrap-installation.php"
    "--admin-user=${ADMIN_USER}"
    "--admin-password=${ADMIN_PASSWORD}"
    "--admin-nickname=${ADMIN_NICKNAME}"
  )

  if [[ "${MERCHANT_ENABLED}" -eq 1 ]]; then
    args+=(
      "--merchant-user=${MERCHANT_USER}"
      "--merchant-password=${MERCHANT_PASSWORD}"
      "--merchant-email=${MERCHANT_EMAIL}"
      "--merchant-name=${MERCHANT_NAME}"
    )
  fi

  (
    cd "${BACKEND_ROOT}"
    php "${args[@]}"
  )
}

sync_public_root() {
  mkdir -p "${PUBLIC_ROOT}"
  rsync -a --delete "${SOURCE_CONSOLE_ROOT}/" "${PUBLIC_ROOT}/"
}

render_aapanel_conf() {
  cat <<TEXT
server
{
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    index index.html index.htm;
    root ${PUBLIC_ROOT};

    access_log  /www/wwwlogs/${DOMAIN}.log;
    error_log  /www/wwwlogs/${DOMAIN}.error.log;

    client_max_body_size 32m;

    location ^~ /.well-known/acme-challenge/ {
        root /var/www/html;
        default_type "text/plain";
    }

    location ~* ^/(\.git|\.vscode|\.env|\.aws|console|setup|trace\.axd|info\.php|server-status|actuator|debug|telescope|ecp|graphql|api/graphql|api/gql|v2/_catalog|___proxy_subdomain|_internal|\.well-known/security\.txt|config\.json|\.DS_Store|settings\.js|js/env\.js|robots\.txt) {
        access_log off;
        return 404;
    }

    if (\$http_user_agent ~* (l9scan|leakix|FAST-WebCrawler|rust_sniffer|CMS-Checker)) {
        return 403;
    }

    location ^~ /theme-assets/ {
        proxy_pass http://127.0.0.1:${BACKEND_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /upload/ {
        proxy_pass http://127.0.0.1:${BACKEND_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /static/ {
        proxy_pass http://127.0.0.1:${BACKEND_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /web/ {
        proxy_pass http://127.0.0.1:${BACKEND_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /Deal/ {
        proxy_pass http://127.0.0.1:${BACKEND_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /deal/ {
        proxy_pass http://127.0.0.1:${BACKEND_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ~ ^/(Api|api|Pay|pay|User|user|My|my|Deal|deal|Index|index|News|news|Doc|doc|Demo|demo|Notify|notify)(/|\$) {
        proxy_pass http://127.0.0.1:${BACKEND_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location = /favicon.ico {
        proxy_pass http://127.0.0.1:${BACKEND_PORT}/favicon.ico;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ~ ^/(submit\.php|mapi\.php|entry|index\.php|admin\.php|qrcode\.php)\$ {
        proxy_pass http://127.0.0.1:${BACKEND_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /assets/ {
        expires 7d;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    location = /index.html {
        add_header Cache-Control "no-store, no-cache, must-revalidate";
    }

    location / {
        add_header Cache-Control "no-store, no-cache, must-revalidate";
        try_files \$uri \$uri/ /index.html;
    }

    location ~ /\.(ht|svn|git) {
        deny all;
    }
}
TEXT
}

render_aapanel_rewrite() {
  cat <<TEXT
# AiPay aaPanel rewrite template
# This file is meant for: Website -> URL Rewrite
# Prerequisites:
# 1. The site must be created as a Static site in aaPanel.
# 2. The main site config must still include:
#    include /www/server/panel/vhost/rewrite/<your-domain>.conf;
# 3. SSL/443 must be enabled from aaPanel's SSL page.

# Optional: force visitors onto HTTPS after SSL is enabled.
# If you use Cloudflare, keep SSL mode at Full or Full (strict).
if (\$scheme != "https") {
    return 301 https://\$host\$request_uri;
}

location ~* ^/(\.git|\.vscode|\.env|\.aws|console|setup|trace\.axd|info\.php|server-status|actuator|debug|telescope|ecp|graphql|api/graphql|api/gql|v2/_catalog|___proxy_subdomain|_internal|\.well-known/security\.txt|config\.json|\.DS_Store|settings\.js|js/env\.js|robots\.txt) {
    access_log off;
    return 404;
}

if (\$http_user_agent ~* (l9scan|leakix|FAST-WebCrawler|rust_sniffer|CMS-Checker)) {
    return 403;
}

location ^~ /assets/ {
    expires 7d;
    add_header Cache-Control "public, immutable";
    try_files \$uri =404;
}

location ^~ /theme-assets/ {
    proxy_pass http://127.0.0.1:${BACKEND_PORT};
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Connection "";
}

location ^~ /upload/ {
    proxy_pass http://127.0.0.1:${BACKEND_PORT};
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Connection "";
}

location ^~ /static/ {
    proxy_pass http://127.0.0.1:${BACKEND_PORT};
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Connection "";
}

location ^~ /web/ {
    proxy_pass http://127.0.0.1:${BACKEND_PORT};
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Connection "";
}

location ^~ /Deal/ {
    proxy_pass http://127.0.0.1:${BACKEND_PORT};
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Connection "";
}

location ^~ /deal/ {
    proxy_pass http://127.0.0.1:${BACKEND_PORT};
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Connection "";
}

location ~ ^/(Api|api|Pay|pay|User|user|My|my|Deal|deal|Index|index|News|news|Doc|doc|Demo|demo|Notify|notify)(/|\$) {
    proxy_pass http://127.0.0.1:${BACKEND_PORT};
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Connection "";
}

location = /favicon.ico {
    proxy_pass http://127.0.0.1:${BACKEND_PORT}/favicon.ico;
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Connection "";
}

location ~ ^/(submit\.php|mapi\.php|entry|index\.php|admin\.php|qrcode\.php)\$ {
    proxy_pass http://127.0.0.1:${BACKEND_PORT};
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Connection "";
}

location = /index.html {
    add_header Cache-Control "no-store, no-cache, must-revalidate";
}

location / {
    add_header Cache-Control "no-store, no-cache, must-revalidate";
    try_files \$uri \$uri/ /index.html;
}
TEXT
}

write_rendered_conf() {
  mkdir -p "$(dirname "${NGINX_CONF_PATH}")"
  render_aapanel_conf > "${NGINX_CONF_PATH}.aipay.new"
}

write_rendered_rewrite() {
  mkdir -p "$(dirname "${REWRITE_CONF_PATH}")"
  render_aapanel_rewrite > "${REWRITE_CONF_PATH}.aipay.rewrite.new"
}

reload_nginx_service() {
  if systemctl list-unit-files | grep -q '^nginx\.service'; then
    if systemctl is-active --quiet nginx; then
      systemctl reload nginx
      return
    fi
  fi

  if [[ -x /etc/init.d/nginx ]]; then
    /etc/init.d/nginx reload
    return
  fi

  nginx -s reload
}

apply_nginx_conf() {
  local backup_path="${NGINX_CONF_PATH}.bak.$(date +%Y%m%d%H%M%S)"

  if [[ -f "${NGINX_CONF_PATH}" ]]; then
    cp "${NGINX_CONF_PATH}" "${backup_path}"
  fi

  cp "${NGINX_CONF_PATH}.aipay.new" "${NGINX_CONF_PATH}"
  nginx -t
  reload_nginx_service
}

run_backend_install() {
  (
    cd "${BACKEND_ROOT}"
    bash deploy/linux/install-production.sh \
      "--domain=${DOMAIN}" \
      "--site-name=${SITE_NAME}" \
      "--backend-port=${BACKEND_PORT}" \
      "--backend-user=${BACKEND_USER}" \
      "--backend-group=${BACKEND_GROUP}" \
      --skip-nginx
  )
}

run_certbot_if_needed() {
  if [[ -z "${CERTBOT_EMAIL}" && "${CERTBOT_NO_EMAIL}" -eq 0 ]]; then
    return
  fi

  command -v certbot >/dev/null 2>&1 || fail 'certbot is not installed.'

  if [[ -n "${CERTBOT_EMAIL}" ]]; then
    certbot --nginx --non-interactive --agree-tos -m "${CERTBOT_EMAIL}" -d "${DOMAIN}" --redirect
  else
    certbot --nginx --non-interactive --agree-tos --register-unsafely-without-email -d "${DOMAIN}" --redirect
  fi

  nginx -t
  reload_nginx_service
}

run_verification() {
  local frontend_scheme=''
  frontend_scheme="$(resolve_frontend_scheme)"

  (
    cd "${BACKEND_ROOT}"
    bash deploy/linux/verify-deployment.sh \
      "--site-name=${SITE_NAME}" \
      "--backend-url=http://127.0.0.1:${BACKEND_PORT}" \
      "--console-url=${frontend_scheme}://${DOMAIN}" \
      "--merchant-url=${frontend_scheme}://${DOMAIN}" \
      "--public-url=${frontend_scheme}://${DOMAIN}" \
      "--admin-user=${ADMIN_USER}" \
      "--admin-password=${ADMIN_PASSWORD}"
  )
}

for argument in "$@"; do
  case "${argument}" in
    --domain=*)
      DOMAIN="${argument#*=}"
      ;;
    --public-root=*)
      PUBLIC_ROOT="${argument#*=}"
      ;;
    --nginx-conf=*)
      NGINX_CONF_PATH="${argument#*=}"
      ;;
    --rewrite-conf=*)
      REWRITE_CONF_PATH="${argument#*=}"
      ;;
    --site-name=*)
      SITE_NAME="${argument#*=}"
      ;;
    --backend-port=*)
      BACKEND_PORT="${argument#*=}"
      ;;
    --db-host=*)
      DB_HOST="${argument#*=}"
      ;;
    --db-port=*)
      DB_PORT="${argument#*=}"
      ;;
    --db-name=*)
      DB_NAME="${argument#*=}"
      ;;
    --db-user=*)
      DB_USER="${argument#*=}"
      ;;
    --db-password=*)
      DB_PASSWORD="${argument#*=}"
      ;;
    --admin-user=*)
      ADMIN_USER="${argument#*=}"
      ;;
    --admin-password=*)
      ADMIN_PASSWORD="${argument#*=}"
      ;;
    --admin-nickname=*)
      ADMIN_NICKNAME="${argument#*=}"
      ;;
    --merchant-user=*)
      MERCHANT_USER="${argument#*=}"
      ;;
    --merchant-password=*)
      MERCHANT_PASSWORD="${argument#*=}"
      ;;
    --merchant-email=*)
      MERCHANT_EMAIL="${argument#*=}"
      ;;
    --merchant-name=*)
      MERCHANT_NAME="${argument#*=}"
      ;;
    --skip-merchant)
      MERCHANT_ENABLED=0
      ;;
    --skip-nginx-apply)
      APPLY_NGINX=0
      ;;
    --certbot-email=*)
      CERTBOT_EMAIL="${argument#*=}"
      ;;
    --certbot-no-email)
      CERTBOT_NO_EMAIL=1
      ;;
    --frontend-scheme=*)
      FRONTEND_SCHEME="${argument#*=}"
      ;;
    --install-deps)
      INSTALL_DEPS=1
      ;;
    --non-interactive)
      NON_INTERACTIVE=1
      ;;
    --help)
      usage
      exit 0
      ;;
    *)
      fail "Unknown argument: ${argument}"
      ;;
  esac
done

require_root
ensure_package_layout
ensure_dependencies
ensure_database_service
ensure_redis_service

prompt_value DOMAIN 'aaPanel domain' ''
prompt_value BACKEND_PORT 'Webman backend port' '8787'
if [[ -z "${SITE_NAME}" ]]; then
  SITE_NAME="$(sanitize_site_name "${DOMAIN}")"
fi
if [[ -z "${PUBLIC_ROOT}" ]]; then
  if [[ -d "/www/wwwroot/${DOMAIN}" ]]; then
    PUBLIC_ROOT="/www/wwwroot/${DOMAIN}"
  else
    PUBLIC_ROOT="${SOURCE_CONSOLE_ROOT}"
  fi
fi
if [[ -z "${NGINX_CONF_PATH}" ]]; then
  NGINX_CONF_PATH="/www/server/panel/vhost/nginx/${DOMAIN}.conf"
fi
if [[ -z "${REWRITE_CONF_PATH}" ]]; then
  REWRITE_CONF_PATH="/www/server/panel/vhost/rewrite/${DOMAIN}.conf"
fi

prompt_value PUBLIC_ROOT 'aaPanel public root' "${PUBLIC_ROOT}"
prompt_value NGINX_CONF_PATH 'aaPanel nginx conf path' "${NGINX_CONF_PATH}"
prompt_value REWRITE_CONF_PATH 'aaPanel rewrite conf path' "${REWRITE_CONF_PATH}"
prompt_value DB_NAME 'Database name' 'pay'
prompt_value DB_USER 'Database user' "${DB_NAME}"
prompt_secret DB_PASSWORD 'Database password'
prompt_value ADMIN_USER 'Admin username' 'adminroot'
prompt_secret ADMIN_PASSWORD 'Admin password'
prompt_value ADMIN_NICKNAME 'Admin nickname' 'AiPayAdmin'

if [[ "${MERCHANT_ENABLED}" -eq 1 && "${NON_INTERACTIVE}" -eq 0 ]]; then
  local_enable_merchant=""
  prompt_yes_no local_enable_merchant 'Create demo merchant account?' 'Y'
  if [[ ! "${local_enable_merchant}" =~ ^[Yy]$ ]]; then
    MERCHANT_ENABLED=0
  fi
fi

if [[ "${MERCHANT_ENABLED}" -eq 1 ]]; then
  prompt_value MERCHANT_USER 'Demo merchant username' 'merchantdemo'
  prompt_secret MERCHANT_PASSWORD 'Demo merchant password'
  prompt_value MERCHANT_EMAIL 'Demo merchant email' "${MERCHANT_USER}@aipay.local"
  prompt_value MERCHANT_NAME 'Demo merchant display name' 'Demo Merchant'
fi

if [[ -z "${CERTBOT_EMAIL}" && "${CERTBOT_NO_EMAIL}" -eq 0 && "${NON_INTERACTIVE}" -eq 0 ]]; then
  local_enable_https=""
  prompt_yes_no local_enable_https 'Enable HTTPS with certbot now?' 'Y'
  if [[ "${local_enable_https}" =~ ^[Yy]$ ]]; then
    local_email=""
    read -r -p 'Certbot email (leave empty to use no-email mode): ' local_email
    if [[ -n "${local_email}" ]]; then
      CERTBOT_EMAIL="${local_email}"
    else
      CERTBOT_NO_EMAIL=1
    fi
  fi
fi

if [[ -n "${CERTBOT_EMAIL}" && "${CERTBOT_NO_EMAIL}" -eq 1 ]]; then
  fail '--certbot-email and --certbot-no-email cannot be used together.'
fi

if [[ -z "${DB_PASSWORD}" ]]; then
  DB_PASSWORD="$(generate_secret 12)"
fi

if [[ -z "${ADMIN_PASSWORD}" ]]; then
  ADMIN_PASSWORD="$(generate_secret 12)"
fi

if [[ "${MERCHANT_ENABLED}" -eq 1 && -z "${MERCHANT_PASSWORD}" ]]; then
  MERCHANT_PASSWORD="$(generate_secret 10)"
fi

detect_backend_runtime_user
validate_inputs
ensure_certbot_if_requested

log 'Writing backend .env'
write_env_file

log 'Creating database and database user'
create_database_and_user

log 'Installing base schema and migrations'
initialize_database

log 'Creating admin account and base payment methods'
bootstrap_accounts

log 'Syncing frontend shell to aaPanel public root'
sync_public_root
chown -R "${BACKEND_USER}:${BACKEND_GROUP}" "${PUBLIC_ROOT}" || true

log 'Installing backend systemd service'
run_backend_install

log 'Rendering aaPanel rewrite conf'
write_rendered_rewrite

log 'Rendering aaPanel nginx conf'
write_rendered_conf

if [[ "${APPLY_NGINX}" -eq 1 ]]; then
  log 'Applying aaPanel nginx conf'
  apply_nginx_conf
else
  log "aaPanel nginx conf generated but not applied: ${NGINX_CONF_PATH}.aipay.new"
fi

log 'Running optional certbot setup'
run_certbot_if_needed

log 'Running deployment verification'
run_verification

frontend_scheme="$(resolve_frontend_scheme)"

printf '\n'
printf '========================================\n'
printf 'AiPay aaPanel installation completed\n'
printf '========================================\n'
printf 'Public URL       : %s://%s/\n' "${frontend_scheme}" "${DOMAIN}"
printf 'Merchant Login   : %s://%s/#/merchant/login\n' "${frontend_scheme}" "${DOMAIN}"
printf 'Merchant Register: %s://%s/#/merchant/register\n' "${frontend_scheme}" "${DOMAIN}"
printf 'Admin Login      : %s://%s/#/auth/login\n' "${frontend_scheme}" "${DOMAIN}"
printf 'Backend Health   : http://127.0.0.1:%s/api/health\n' "${BACKEND_PORT}"
printf 'Public Root      : %s\n' "${PUBLIC_ROOT}"
printf 'Nginx Conf       : %s\n' "${NGINX_CONF_PATH}"
printf 'Rewrite Conf     : %s\n' "${REWRITE_CONF_PATH}"
printf 'Rendered Conf    : %s.aipay.new\n' "${NGINX_CONF_PATH}"
printf 'Rendered Rewrite : %s.aipay.rewrite.new\n' "${REWRITE_CONF_PATH}"
printf 'Admin Account    : %s\n' "${ADMIN_USER}"
printf 'Admin Password   : %s\n' "${ADMIN_PASSWORD}"
if [[ "${MERCHANT_ENABLED}" -eq 1 ]]; then
  printf 'Demo Merchant    : %s\n' "${MERCHANT_USER}"
  printf 'Merchant Password: %s\n' "${MERCHANT_PASSWORD}"
fi
printf 'DB Name          : %s\n' "${DB_NAME}"
printf 'DB User          : %s\n' "${DB_USER}"
printf 'DB Password      : %s\n' "${DB_PASSWORD}"
