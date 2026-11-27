#!/usr/bin/env bash
#
# Copies the Zarinpal, payment, SMS (Melli Payamak, Kavenegar) and Tipax settings from the local .env to the
# server's shared .env. It prints key names only, never values.
#
#   deploy/push-integration-env.sh            dry run: lists what would be added or changed
#   deploy/push-integration-env.sh --apply    backs up the server .env, writes the keys, refreshes the config
#                                             cache and restarts Horizon
#
# Local-only switches are never copied (set them on the server by hand): the fake payment gateway, Zarinpal's
# sandbox and callback URL, and SMS "pretend" mode. Values pointing at localhost are skipped too.
#
# Settings: SERVER (default root@185.221.237.81), APP_ROOT (default /var/www/hutana), LOCAL_ENV (default .env).

set -euo pipefail

SERVER="${SERVER:-root@185.221.237.81}"
APP_ROOT="${APP_ROOT:-/var/www/hutana}"
LOCAL_ENV="${LOCAL_ENV:-.env}"
PREFIXES='^(ZARINPAL|PAYMENT|SMS|MELIPAYAMAK|KAVENEGAR|TIPAX)_[A-Z0-9_]*$'
LOCAL_ONLY='^(PAYMENT_GATEWAY|PAYMENT_FAKE_ENABLED|ZARINPAL_SANDBOX|ZARINPAL_CALLBACK_URL|SMS_PRETEND)$'

apply=0
[[ "${1:-}" == "--apply" ]] && apply=1

[[ -f "${LOCAL_ENV}" ]] || { echo "No ${LOCAL_ENV} here: run this from the project root." >&2; exit 1; }

pairs="$(mktemp)"
chmod 600 "${pairs}"
trap 'rm -f "${pairs}"' EXIT

while IFS= read -r line || [[ -n "${line}" ]]; do
    [[ "${line}" =~ ^[[:space:]]*# || "${line}" != *=* ]] && continue
    key="${line%%=*}"
    value="${line#*=}"
    [[ "${key}" =~ ${PREFIXES} ]] || continue
    if [[ "${key}" =~ ${LOCAL_ONLY} ]]; then
        echo "skip     ${key} (local-only switch)"
        continue
    fi
    if [[ "${value}" == *localhost* || "${value}" == *127.0.0.1* ]]; then
        echo "skip     ${key} (points at localhost)"
        continue
    fi
    [[ -z "${value}" ]] && { echo "skip     ${key} (empty locally)"; continue; }
    printf '%s=%s\n' "${key}" "${value}" >> "${pairs}"
done < "${LOCAL_ENV}"

# The pairs travel inside the script that bash reads from stdin over SSH (base64), never on a command line. Python
# merges them into the server .env in place, so the file keeps its owner (deploy:www-data) and mode.
{
    printf 'PAIRS_B64=%q\n' "$(base64 -w0 < "${pairs}")"
    cat <<'REMOTE'
set -euo pipefail
env_file="${APP_ROOT}/shared/.env"
current="${APP_ROOT}/current"

if [[ "${APPLY}" == 1 ]]; then
    cp -p "${env_file}" "${env_file}.bak-$(date +%Y%m%d%H%M%S)"
fi

APPLY="${APPLY}" ENV_FILE="${env_file}" python3 - 3< <(base64 -d <<< "${PAIRS_B64}") <<'PY'
import os

pairs = []
for raw in os.fdopen(3, encoding="utf-8").read().splitlines():
    key, _, value = raw.partition("=")
    pairs.append((key, value))

path = os.environ["ENV_FILE"]
lines = open(path, encoding="utf-8").read().splitlines()
index = {line.partition("=")[0].strip(): i for i, line in enumerate(lines) if "=" in line and not line.lstrip().startswith("#")}

changed = 0
for key, value in pairs:
    if key in index:
        if lines[index[key]].partition("=")[2] == value:
            print(f"same     {key}")
            continue
        lines[index[key]] = f"{key}={value}"
        print(f"change   {key}")
    else:
        lines.append(f"{key}={value}")
        print(f"add      {key}")
    changed += 1

if os.environ["APPLY"] == "1" and changed:
    with open(path, "w", encoding="utf-8") as handle:
        handle.write("\n".join(lines) + "\n")

print(f"{changed} key(s) to add or change" + ("" if os.environ["APPLY"] == "1" else " (dry run: nothing written)"))
PY

if [[ "${APPLY}" == 1 ]]; then
    runuser -u deploy -- php "${current}/artisan" optimize --no-interaction
    runuser -u deploy -- php "${current}/artisan" horizon:terminate --no-interaction || true
    echo "Config cache refreshed; Horizon restarts under Supervisor."
fi
REMOTE
} | ssh "${SERVER}" "APPLY=${apply} APP_ROOT='${APP_ROOT}' bash -s"
