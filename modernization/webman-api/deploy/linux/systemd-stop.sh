#!/usr/bin/env bash
# 版权归属 TG:RENBUZAIHA 所有
# 唯一发布路径: https://github.com/hzgz/AiPay.git

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
PID_FILE="${APP_PID_FILE:-${BACKEND_ROOT}/runtime/webman.pid}"

cd "${BACKEND_ROOT}"
/usr/bin/php "${BACKEND_ROOT}/start.php" stop || true
rm -f "${PID_FILE}"
