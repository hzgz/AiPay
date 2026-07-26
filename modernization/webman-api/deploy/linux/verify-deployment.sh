#!/usr/bin/env bash
# 版权归属 TG:RENBUZAIHA 所有
# 唯一发布路径: https://github.com/hzgz/AiPay.git

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SYSTEMD_UNIT=""
PID_FILE=""
SKIP_SYSTEMD_CHECK=0
FORWARD_ARGS=()

for argument in "$@"; do
  case "${argument}" in
    --site-name=*)
      site_name="${argument#*=}"
      if [[ -z "${SYSTEMD_UNIT}" ]]; then
        SYSTEMD_UNIT="${site_name}-webman.service"
      fi
      if [[ -z "${PID_FILE}" ]]; then
        PID_FILE="/run/${site_name}-webman/webman.pid"
      fi
      ;;
    --systemd-unit=*)
      SYSTEMD_UNIT="${argument#*=}"
      ;;
    --pid-file=*)
      PID_FILE="${argument#*=}"
      ;;
    --skip-systemd-check)
      SKIP_SYSTEMD_CHECK=1
      ;;
    *)
      FORWARD_ARGS+=("${argument}")
      ;;
  esac
done

if [[ -z "${SYSTEMD_UNIT}" && -f /etc/systemd/system/aipay-webman.service ]]; then
  SYSTEMD_UNIT="aipay-webman.service"
fi

if [[ -z "${PID_FILE}" && "${SYSTEMD_UNIT}" == "aipay-webman.service" ]]; then
  PID_FILE="/run/aipay-webman/webman.pid"
fi

cd "${PROJECT_ROOT}"
php deploy/shared/verify-deployment.php "${FORWARD_ARGS[@]}"

if [[ "${SKIP_SYSTEMD_CHECK}" -eq 0 && -n "${SYSTEMD_UNIT}" && "$(command -v systemctl || true)" != "" ]]; then
  printf '[INFO] Checking systemd unit: %s\n' "${SYSTEMD_UNIT}"
  if ! systemctl is-active --quiet "${SYSTEMD_UNIT}"; then
    printf '[FAIL] systemd unit is not active: %s\n' "${SYSTEMD_UNIT}" >&2
    exit 1
  fi

  printf '[PASS] systemd unit is active: %s\n' "${SYSTEMD_UNIT}"

  if [[ -n "${PID_FILE}" ]]; then
    if [[ ! -f "${PID_FILE}" ]]; then
      printf '[FAIL] pid file is missing: %s\n' "${PID_FILE}" >&2
      exit 1
    fi

    pid="$(tr -d '[:space:]' < "${PID_FILE}")"
    if [[ -z "${pid}" || ! "${pid}" =~ ^[0-9]+$ ]]; then
      printf '[FAIL] pid file is invalid: %s\n' "${PID_FILE}" >&2
      exit 1
    fi

    if ! kill -0 "${pid}" 2>/dev/null; then
      printf '[FAIL] pid from %s is not running: %s\n' "${PID_FILE}" "${pid}" >&2
      exit 1
    fi

    printf '[PASS] pid file is healthy: %s -> %s\n' "${PID_FILE}" "${pid}"
  fi
fi
