#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
PACKAGE_ROOT="$(cd "${BACKEND_ROOT}/.." && pwd)"
CONSOLE_ROOT="${PACKAGE_ROOT}/console"

DOMAIN=""
SITE_NAME="aipay"
BACKEND_USER="www-data"
BACKEND_GROUP="www-data"
BACKEND_PORT="8787"
CERTBOT_EMAIL=""
CERTBOT_NO_EMAIL=0
SKIP_SYSTEMD=0
SKIP_NGINX=0

usage() {
  cat <<'TEXT'
Usage:
  bash deploy/linux/install-production.sh --domain=portal.example.com [options]

Options:
  --domain=DOMAIN          Public domain used by the frontend shell. Required.
  --site-name=NAME         Nginx site / systemd unit prefix. Default: aipay
  --backend-user=USER      Backend runtime user. Default: www-data
  --backend-group=GROUP    Backend runtime group. Default: www-data
  --backend-port=PORT      Local Webman listen port. Default: 8787
  --certbot-email=EMAIL    If provided, run certbot --nginx after HTTP config is loaded.
  --certbot-no-email       Run certbot without email and still enable HTTPS redirect.
  --skip-systemd           Skip systemd service installation / restart.
  --skip-nginx             Skip Nginx site rendering / reload.
  --help                   Show this help message.
TEXT
}

require_root() {
  if [[ "${EUID}" -ne 0 ]]; then
    echo "[FAIL] Please run this script as root." >&2
    exit 1
  fi
}

log() {
  printf '[INFO] %s\n' "$1"
}

fail() {
  printf '[FAIL] %s\n' "$1" >&2
  exit 1
}

for argument in "$@"; do
  case "${argument}" in
    --domain=*)
      DOMAIN="${argument#*=}"
      ;;
    --site-name=*)
      SITE_NAME="${argument#*=}"
      ;;
    --backend-user=*)
      BACKEND_USER="${argument#*=}"
      ;;
    --backend-group=*)
      BACKEND_GROUP="${argument#*=}"
      ;;
    --backend-port=*)
      BACKEND_PORT="${argument#*=}"
      ;;
    --certbot-email=*)
      CERTBOT_EMAIL="${argument#*=}"
      ;;
    --certbot-no-email)
      CERTBOT_NO_EMAIL=1
      ;;
    --skip-systemd)
      SKIP_SYSTEMD=1
      ;;
    --skip-nginx)
      SKIP_NGINX=1
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

[[ -n "${DOMAIN}" ]] || fail "--domain is required"
[[ -d "${BACKEND_ROOT}" ]] || fail "Backend root not found: ${BACKEND_ROOT}"
[[ -d "${CONSOLE_ROOT}" ]] || fail "Console root not found: ${CONSOLE_ROOT}"
[[ -f "${BACKEND_ROOT}/start.php" ]] || fail "start.php not found under ${BACKEND_ROOT}"
[[ -f "${CONSOLE_ROOT}/index.html" ]] || fail "console index.html not found under ${CONSOLE_ROOT}"
[[ "${BACKEND_PORT}" =~ ^[0-9]+$ ]] || fail "--backend-port must be numeric"
if [[ -n "${CERTBOT_EMAIL}" && "${CERTBOT_NO_EMAIL}" -eq 1 ]]; then
  fail "--certbot-email and --certbot-no-email cannot be used together"
fi

require_root

log "Preparing runtime and upload directories"
install -d -m 775 -o "${BACKEND_USER}" -g "${BACKEND_GROUP}" \
  "${BACKEND_ROOT}/runtime" \
  "${BACKEND_ROOT}/runtime/cache" \
  "${BACKEND_ROOT}/runtime/logs" \
  "${BACKEND_ROOT}/runtime/payment-plugins" \
  "${BACKEND_ROOT}/upload-assets" \
  "${BACKEND_ROOT}/upload-assets/images" \
  "${BACKEND_ROOT}/upload-assets/news" \
  "${BACKEND_ROOT}/upload-assets/payment-accounts" \
  "${BACKEND_ROOT}/upload-assets/plugins" \
  "${BACKEND_ROOT}/upload-assets/qrcode"

log "Applying filesystem ownership"
chown -R "${BACKEND_USER}:${BACKEND_GROUP}" "${BACKEND_ROOT}"
chown -R "${BACKEND_USER}:${BACKEND_GROUP}" "${CONSOLE_ROOT}"

log "Normalizing filesystem permissions"
find "${BACKEND_ROOT}" -type d -exec chmod 755 {} +
find "${BACKEND_ROOT}" -type f -exec chmod 644 {} +
find "${CONSOLE_ROOT}" -type d -exec chmod 755 {} +
find "${CONSOLE_ROOT}" -type f -exec chmod 644 {} +
find "${BACKEND_ROOT}/deploy" -type f -name '*.sh' -exec chmod 755 {} +
chmod 775 \
  "${BACKEND_ROOT}/runtime" \
  "${BACKEND_ROOT}/runtime/cache" \
  "${BACKEND_ROOT}/runtime/logs" \
  "${BACKEND_ROOT}/runtime/payment-plugins" \
  "${BACKEND_ROOT}/upload-assets" \
  "${BACKEND_ROOT}/upload-assets/images" \
  "${BACKEND_ROOT}/upload-assets/news" \
  "${BACKEND_ROOT}/upload-assets/payment-accounts" \
  "${BACKEND_ROOT}/upload-assets/plugins" \
  "${BACKEND_ROOT}/upload-assets/qrcode"

SERVICE_PATH="/etc/systemd/system/${SITE_NAME}-webman.service"
if [[ "${SKIP_SYSTEMD}" -eq 0 ]]; then
  log "Rendering systemd service: ${SERVICE_PATH}"
  cat > "${SERVICE_PATH}" <<TEXT
[Unit]
Description=AiPay Webman Backend (${SITE_NAME})
After=network.target mariadb.service

[Service]
Type=simple
User=${BACKEND_USER}
Group=${BACKEND_GROUP}
WorkingDirectory=${BACKEND_ROOT}
Environment=APP_ENV=production
Environment=APP_PORT=${BACKEND_PORT}
ExecStart=/usr/bin/php ${BACKEND_ROOT}/start.php start
ExecStop=/usr/bin/php ${BACKEND_ROOT}/start.php stop
ExecReload=/usr/bin/php ${BACKEND_ROOT}/start.php reload
Restart=always
RestartSec=5
KillSignal=SIGINT
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
TEXT

  systemctl daemon-reload
  systemctl enable "${SITE_NAME}-webman.service" >/dev/null
  systemctl restart "${SITE_NAME}-webman.service"
  systemctl --no-pager --full status "${SITE_NAME}-webman.service" || fail "systemd service failed to start"
fi

if [[ "${SKIP_NGINX}" -eq 0 ]]; then
  NGINX_CONF="/etc/nginx/sites-available/${SITE_NAME}.conf"
  NGINX_LINK="/etc/nginx/sites-enabled/${SITE_NAME}.conf"

  log "Rendering Nginx site: ${NGINX_CONF}"
  cat > "${NGINX_CONF}" <<TEXT
upstream ${SITE_NAME}_console_backend {
    server 127.0.0.1:${BACKEND_PORT};
    keepalive 32;
}

server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    root ${CONSOLE_ROOT};
    index index.html;
    client_max_body_size 32m;

    location ^~ /.well-known/acme-challenge/ {
        root /var/www/html;
        default_type "text/plain";
    }

    location ^~ /theme-assets/ {
        proxy_pass http://${SITE_NAME}_console_backend;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /upload/ {
        proxy_pass http://${SITE_NAME}_console_backend;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /static/ {
        proxy_pass http://${SITE_NAME}_console_backend;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /web/ {
        proxy_pass http://${SITE_NAME}_console_backend;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /Deal/ {
        proxy_pass http://${SITE_NAME}_console_backend;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ^~ /deal/ {
        proxy_pass http://${SITE_NAME}_console_backend;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ~ ^/(Api|api|Pay|pay|User|user|My|my|Deal|deal|Index|index|News|news|Doc|doc|Demo|demo|Notify|notify)(/|\$) {
        proxy_pass http://${SITE_NAME}_console_backend;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location = /favicon.ico {
        proxy_pass http://${SITE_NAME}_console_backend/favicon.ico;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    location ~ ^/(submit\.php|mapi\.php|entry|index\.php|admin\.php)\$ {
        proxy_pass http://${SITE_NAME}_console_backend;
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
}
TEXT

  ln -sfn "${NGINX_CONF}" "${NGINX_LINK}"
  nginx -t
  systemctl reload nginx
fi

if [[ -n "${CERTBOT_EMAIL}" ]]; then
  command -v certbot >/dev/null 2>&1 || fail "certbot is not installed"
  [[ "${SKIP_NGINX}" -eq 0 ]] || fail "--certbot-email requires Nginx setup"

  log "Requesting HTTPS certificate for ${DOMAIN}"
  certbot --nginx --non-interactive --agree-tos -m "${CERTBOT_EMAIL}" -d "${DOMAIN}" --redirect
  nginx -t
  systemctl reload nginx
fi

if [[ "${CERTBOT_NO_EMAIL}" -eq 1 ]]; then
  command -v certbot >/dev/null 2>&1 || fail "certbot is not installed"
  [[ "${SKIP_NGINX}" -eq 0 ]] || fail "--certbot-no-email requires Nginx setup"

  log "Requesting HTTPS certificate for ${DOMAIN} without email"
  certbot --nginx --non-interactive --agree-tos --register-unsafely-without-email -d "${DOMAIN}" --redirect
  nginx -t
  systemctl reload nginx
fi

log "Production bootstrap completed"
log "Backend root: ${BACKEND_ROOT}"
log "Console root: ${CONSOLE_ROOT}"
log "Public site: http://${DOMAIN}/"
log "Merchant: http://${DOMAIN}/#/merchant/login"
log "Admin:   http://${DOMAIN}/#/auth/login"
if [[ -n "${CERTBOT_EMAIL}" || "${CERTBOT_NO_EMAIL}" -eq 1 ]]; then
  log "HTTPS expected: https://${DOMAIN}/"
fi
