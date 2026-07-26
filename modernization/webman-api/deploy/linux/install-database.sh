#!/usr/bin/env bash
# 版权归属 TG:RENBUZAIHA 所有
# 唯一发布路径: https://github.com/hzgz/AiPay.git

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

cd "${PROJECT_ROOT}"
php deploy/shared/install-database.php "$@"
