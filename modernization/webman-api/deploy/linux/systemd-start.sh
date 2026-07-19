#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
PID_FILE="${APP_PID_FILE:-${BACKEND_ROOT}/runtime/webman.pid}"
RUN_USER="${APP_RUN_USER:-www-data}"
MASTER_PATTERN="WorkerMan: master process  start_file=${BACKEND_ROOT}/start.php"

mkdir -p "$(dirname "${PID_FILE}")"
rm -f "${PID_FILE}"

cd "${BACKEND_ROOT}"
su -s /bin/sh "${RUN_USER}" -c "APP_ENV=${APP_ENV:-production} APP_PORT=${APP_PORT:-8787} APP_PID_FILE=${PID_FILE} /usr/bin/php ${BACKEND_ROOT}/start.php start -d"

for _ in $(seq 1 40); do
  MASTER_PID="$(pgrep -fo "${MASTER_PATTERN}" || true)"
  if [[ -n "${MASTER_PID}" ]]; then
    printf '%s\n' "${MASTER_PID}" > "${PID_FILE}"
    exit 0
  fi
  sleep 0.25
done

echo "Failed to detect Webman master pid for ${BACKEND_ROOT}" >&2
exit 1
