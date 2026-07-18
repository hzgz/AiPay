#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
PACKAGE_ROOT="$(cd "${BACKEND_ROOT}/.." && pwd)"
CONSOLE_ROOT="${PACKAGE_ROOT}/console"

DOMAIN=""
SITE_NAME=""
BACKEND_PORT=""
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
INSTALL_DEPS=0
NON_INTERACTIVE=0

usage() {
  cat <<'TEXT'
Usage:
  bash deploy/linux/install-oneclick.sh [options]

Options:
  --domain=DOMAIN              Public domain. Required in non-interactive mode.
  --site-name=NAME             systemd / nginx site name. Default: derived from domain.
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
  --certbot-email=EMAIL        Enable HTTPS with certbot using this email.
  --certbot-no-email           Enable HTTPS with certbot and no email.
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
    raw='aipay'
  fi
  printf '%s' "${raw}"
}

generate_secret() {
  php -r "echo bin2hex(random_bytes(${1}));"
}

ensure_package_layout() {
  [[ -d "${BACKEND_ROOT}" ]] || fail "Backend root not found: ${BACKEND_ROOT}"
  [[ -d "${CONSOLE_ROOT}" ]] || fail "Console root not found: ${CONSOLE_ROOT}"
  [[ -f "${BACKEND_ROOT}/start.php" ]] || fail "start.php not found under ${BACKEND_ROOT}"
  [[ -f "${CONSOLE_ROOT}/index.html" ]] || fail "index.html not found under ${CONSOLE_ROOT}"
}

install_dependencies_apt() {
  export DEBIAN_FRONTEND=noninteractive
  apt-get update
  apt-get install -y \
    nginx \
    mariadb-server \
    mariadb-client \
    unzip \
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
    php-gd
}

ensure_dependencies() {
  local missing=()
  local required=(php nginx systemctl)

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

ensure_database_service() {
  if systemctl list-unit-files | grep -q '^mariadb\.service'; then
    systemctl enable --now mariadb >/dev/null 2>&1 || true
    return
  fi

  if systemctl list-unit-files | grep -q '^mysql\.service'; then
    systemctl enable --now mysql >/dev/null 2>&1 || true
  fi
}

ensure_certbot_if_requested() {
  if [[ -z "${CERTBOT_EMAIL}" && "${CERTBOT_NO_EMAIL}" -eq 0 ]]; then
    return
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
  [[ "${BACKEND_PORT}" =~ ^[0-9]+$ ]] || fail 'backend port must be numeric.'
  [[ "${DB_PORT}" =~ ^[0-9]+$ ]] || fail 'database port must be numeric.'

  if [[ "${DB_USER}" == *"'"* || "${DB_PASSWORD}" == *"'"* ]]; then
    fail "Database user and password cannot contain a single quote (')."
  fi
}

write_env_file() {
  local frontend_scheme='http'
  if [[ -n "${CERTBOT_EMAIL}" || "${CERTBOT_NO_EMAIL}" -eq 1 ]]; then
    frontend_scheme='https'
  fi

  cat > "${BACKEND_ROOT}/.env" <<TEXT
APP_ENV=production
APP_DEBUG=false
APP_HOST=127.0.0.1
APP_PORT=${BACKEND_PORT}
APP_WORKER_COUNT=1
APP_FILE_MONITOR=false
APP_MEMORY_MONITOR=false

DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

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

run_production_install() {
  local args=(
    "--domain=${DOMAIN}"
    "--site-name=${SITE_NAME}"
    "--backend-port=${BACKEND_PORT}"
  )

  if [[ -n "${CERTBOT_EMAIL}" ]]; then
    args+=("--certbot-email=${CERTBOT_EMAIL}")
  fi

  if [[ "${CERTBOT_NO_EMAIL}" -eq 1 ]]; then
    args+=('--certbot-no-email')
  fi

  (
    cd "${BACKEND_ROOT}"
    bash deploy/linux/install-production.sh "${args[@]}"
  )
}

run_verification() {
  local frontend_scheme='http'
  if [[ -n "${CERTBOT_EMAIL}" || "${CERTBOT_NO_EMAIL}" -eq 1 ]]; then
    frontend_scheme='https'
  fi

  (
    cd "${BACKEND_ROOT}"
    bash deploy/linux/verify-deployment.sh \
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
    --certbot-email=*)
      CERTBOT_EMAIL="${argument#*=}"
      ;;
    --certbot-no-email)
      CERTBOT_NO_EMAIL=1
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

prompt_value DOMAIN 'Public domain' ''
prompt_value BACKEND_PORT 'Webman backend port' '8787'
if [[ -z "${SITE_NAME}" ]]; then
  SITE_NAME="$(sanitize_site_name "${DOMAIN}")"
fi
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

log 'Installing systemd service, Nginx site, and optional HTTPS'
run_production_install

log 'Running deployment verification'
run_verification

frontend_scheme='http'
if [[ -n "${CERTBOT_EMAIL}" || "${CERTBOT_NO_EMAIL}" -eq 1 ]]; then
  frontend_scheme='https'
fi

printf '\n'
printf '========================================\n'
printf 'AiPay installation completed successfully\n'
printf '========================================\n'
printf 'Public URL      : %s://%s/\n' "${frontend_scheme}" "${DOMAIN}"
printf 'Merchant Login  : %s://%s/#/merchant/login\n' "${frontend_scheme}" "${DOMAIN}"
printf 'Merchant Register: %s://%s/#/merchant/register\n' "${frontend_scheme}" "${DOMAIN}"
printf 'Admin Login     : %s://%s/#/auth/login\n' "${frontend_scheme}" "${DOMAIN}"
printf 'Backend Health  : http://127.0.0.1:%s/api/health\n' "${BACKEND_PORT}"
printf 'Admin Account   : %s\n' "${ADMIN_USER}"
printf 'Admin Password  : %s\n' "${ADMIN_PASSWORD}"
if [[ "${MERCHANT_ENABLED}" -eq 1 ]]; then
  printf 'Demo Merchant   : %s\n' "${MERCHANT_USER}"
  printf 'Merchant Password: %s\n' "${MERCHANT_PASSWORD}"
fi
printf 'DB Name         : %s\n' "${DB_NAME}"
printf 'DB User         : %s\n' "${DB_USER}"
printf 'DB Password     : %s\n' "${DB_PASSWORD}"
