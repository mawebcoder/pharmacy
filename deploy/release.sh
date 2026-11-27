#!/usr/bin/env bash
# Zero-downtime release on the production server (roadmap D19.4), run by CI over SSH as the deploy user:
#   release.sh deploy <commit> <archive.tar.gz>   unpack a release built by CI, prepare it, switch to it
#   release.sh rollback [<commit>]                switch back to a kept release (the previous one by default)
#
# Layout under /var/www/hutana (APP_ROOT):
#   releases/<commit>/   one folder per release (code, vendor without dev packages, built assets)
#   shared/.env          the production environment, written by hand once
#   shared/storage/      uploads, logs, cache: kept across releases
#   current -> releases/<commit>   what Nginx, PHP-FPM, Horizon and the scheduler run
#   releases/<commit>/.went-live   set when a release goes live; a rollback only goes back to these
#
# Migrations run before the switch and are not undone by a rollback: keep each migration compatible with the
# release before it.
set -euo pipefail
umask 002

APP_ROOT="${APP_ROOT:-/var/www/hutana}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.5-fpm}"

RELEASES="${APP_ROOT}/releases"
SHARED="${APP_ROOT}/shared"
CURRENT="${APP_ROOT}/current"

# Runs detached from the SSH session that started it. On a 1 GB, single-core server a slow step can outlast the
# connection, and a dropped connection used to kill a migration halfway: MySQL cannot undo a CREATE TABLE, so the
# table stayed without its row in "migrations" and every later deploy failed on "Table already exists". The caller
# still sees the output and the exit status; if the connection drops, the release carries on, logged in release.log.
if [[ -z "${HUTANA_RELEASE_DETACHED:-}" ]]; then
    release_log="${SHARED}/storage/logs/release.log"
    : > "${release_log}"
    HUTANA_RELEASE_DETACHED=1 setsid "${BASH_SOURCE[0]}" "$@" >> "${release_log}" 2>&1 < /dev/null &
    release_pid=$!
    tail -n +1 --pid="${release_pid}" -f "${release_log}"
    wait "${release_pid}"
    exit $?
fi

# One release at a time: a retry must not migrate while an earlier, detached run is still going.
exec 9> "${APP_ROOT}/release.lock"
flock -n 9 || { echo "Another release.sh is still running: see ${SHARED}/storage/logs/release.log." >&2; exit 1; }

# Each line shows the seconds since the script started, so a slow command stands out in the CI log.
log() {
    printf '==> [%3ss] %s\n' "${SECONDS}" "$*"
}

artisan() {
    php "$1/artisan" "${@:2}" --no-interaction
}

# The release that went live most recently, other than the one live now: what a rollback goes back to. A release
# that failed before its switch never went live (it may lack migrations' code paths, caches or assets).
previous_live_release() {
    local live
    live="$(readlink -f "${CURRENT}" || true)"
    find "${RELEASES}" -mindepth 2 -maxdepth 2 -name .went-live -printf '%T@ %h\n' \
        | sort -rn \
        | cut -d' ' -f2- \
        | { grep -vxF "${live}" || true; } \
        | head -n 1
}

# Points "current" at a release in one rename, so no request ever sees a half-switched link.
activate() {
    local release="$1"

    ln -sfn "${release}" "${APP_ROOT}/current.next"
    mv -Tf "${APP_ROOT}/current.next" "${CURRENT}"
    touch "${release}/.went-live"
    log "Switched; reloading PHP-FPM and the scheduler (Horizon stays off)"

    # OPcache never re-checks files (deploy/php/production.ini): a reload loads the new release.
    sudo systemctl reload "${PHP_FPM_SERVICE}"
    # Horizon is not started by a deploy or a rollback: on the 1 GB server it alone took about 430 MB. Start it by
    # hand when wanted: sudo supervisorctl start hutana:hutana-horizon (it then runs "current").
    sudo supervisorctl restart hutana:hutana-scheduler

    log "Live: $(basename "${release}")"
}

# On a 1 GB, single-core server Horizon, the scheduler and Meilisearch do not fit in memory next to the migrations:
# the server swaps and every statement crawls. Horizon is stopped and stays off (see activate); the scheduler is
# stopped for the whole deploy (activate starts it from the new release), Meilisearch only for the migrations. If a
# step fails, start_services brings the scheduler and Meilisearch back on the release still live.
stop_services() {
    log "Stopping Horizon, the scheduler and Meilisearch for the deploy"
    trap start_services EXIT
    sudo -n supervisorctl stop hutana:hutana-horizon hutana:hutana-scheduler > /dev/null
    sudo -n systemctl stop meilisearch
}

start_services() {
    sudo -n systemctl start meilisearch || true
    sudo -n supervisorctl restart hutana:hutana-scheduler > /dev/null 2>&1 || true
}

start_meilisearch() {
    log "Starting Meilisearch"
    sudo -n systemctl start meilisearch
    for _ in $(seq 30); do
        curl --silent --fail http://127.0.0.1:7700/health > /dev/null && return 0
        sleep 1
    done
    echo "Meilisearch did not answer within 30 seconds." >&2
    return 1
}

deploy() {
    local commit="$1" archive="$2"
    local release="${RELEASES}/${commit}"

    [[ -f "${SHARED}/.env" ]] || { echo "Missing ${SHARED}/.env (see deploy/README.md)." >&2; exit 1; }
    [[ -f "${archive}" ]] || { echo "Missing archive ${archive}." >&2; exit 1; }

    stop_services

    log "Unpacking ${commit}"
    rm -rf "${release}"
    mkdir -p "${release}"
    tar -xzf "${archive}" -C "${release}"
    rm -f "${archive}"

    # Shared state: the environment and storage live outside the release.
    rm -rf "${release}/storage"
    ln -s "${SHARED}/storage" "${release}/storage"
    ln -sfn "${SHARED}/.env" "${release}/.env"
    ln -sfn ../storage/app/public "${release}/public/storage"
    mkdir -p "${release}/bootstrap/cache"

    log "Migrations"
    artisan "${release}" migrate --force
    # Seeders index into Meilisearch straight away when Scout does not queue (SCOUT_QUEUE=false).
    start_meilisearch
    log "Seeders"
    # Starting data: each seeder only adds what is missing.
    artisan "${release}" db:seed --force
    log "Search index settings"
    artisan "${release}" scout:sync-index-settings
    log "Caches"
    artisan "${release}" optimize
    artisan "${release}" filament:optimize

    activate "${release}"
    trap - EXIT

    # Provinces and cities from Tipax (D4.3) after the switch and in the background: a slow or unreachable Tipax
    # must not hold up the release. The scheduler syncs them again every night; the result is in tipax-sync.log.
    log "Tipax cities sync started in the background"
    setsid nohup php "${CURRENT}/artisan" tipax:sync-cities --no-interaction \
        >> "${SHARED}/storage/logs/tipax-sync.log" 2>&1 < /dev/null 9>&- &

    log "Keeping the last ${KEEP_RELEASES} releases"
    local keep_live
    keep_live="$(previous_live_release)"
    find "${RELEASES}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
        | sort -rn \
        | tail -n +"$((KEEP_RELEASES + 1))" \
        | cut -d' ' -f2- \
        | while read -r old; do
            # Never the live release, nor the one a rollback would go back to.
            [[ "$(readlink -f "${CURRENT}")" == "${old}" || "${keep_live}" == "${old}" ]] || rm -rf "${old}"
        done
}

rollback() {
    local target="${1:-}" live
    live="$(readlink -f "${CURRENT}")"

    if [[ -z "${target}" ]]; then
        target="$(previous_live_release)"
    else
        target="${RELEASES}/${target}"
        [[ -f "${target}/.went-live" ]] || { echo "$(basename "${target}") never went live: not rolling back to it." >&2; exit 1; }
    fi

    [[ -n "${target}" && -d "${target}" ]] || { echo "No release that went live before to roll back to." >&2; exit 1; }

    log "Rolling back to $(basename "${target}")"
    # The release being left failed: it is no longer a rollback target itself.
    [[ -z "${live}" ]] || rm -f "${live}/.went-live"
    activate "${target}"
}

case "${1:-}" in
    deploy) deploy "${2:?commit required}" "${3:?archive required}" ;;
    rollback) rollback "${2:-}" ;;
    *) echo "Usage: $0 deploy <commit> <archive.tar.gz> | rollback [<commit>]" >&2; exit 2 ;;
esac
