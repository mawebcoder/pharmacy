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

log() {
    printf '==> %s\n' "$*"
}

artisan() {
    php "$1/artisan" "${@:2}" --no-interaction
}

# Points "current" at a release in one rename, so no request ever sees a half-switched link.
activate() {
    local release="$1"

    ln -sfn "${release}" "${APP_ROOT}/current.next"
    mv -Tf "${APP_ROOT}/current.next" "${CURRENT}"

    # OPcache never re-checks files (deploy/php/production.ini): a reload loads the new release.
    sudo systemctl reload "${PHP_FPM_SERVICE}"
    # Horizon finishes its running jobs and Supervisor starts it again from the new release; "start" also brings it
    # up on the first release (until then Supervisor has nothing to run).
    artisan "${CURRENT}" horizon:terminate || true
    sudo supervisorctl start hutana:hutana-horizon > /dev/null || true
    sudo supervisorctl restart hutana:hutana-scheduler

    log "Live: $(basename "${release}")"
}

deploy() {
    local commit="$1" archive="$2"
    local release="${RELEASES}/${commit}"

    [[ -f "${SHARED}/.env" ]] || { echo "Missing ${SHARED}/.env (see deploy/README.md)." >&2; exit 1; }
    [[ -f "${archive}" ]] || { echo "Missing archive ${archive}." >&2; exit 1; }

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

    log "Release commands"
    artisan "${release}" migrate --force
    # Starting data: each seeder only adds what is missing.
    artisan "${release}" db:seed --force
    artisan "${release}" scout:sync-index-settings
    # Provinces and cities from Tipax (D4.3); a Tipax outage must not block the release.
    artisan "${release}" tipax:sync-cities || echo "warning: tipax:sync-cities failed; run it again later" >&2
    artisan "${release}" optimize
    artisan "${release}" filament:optimize

    activate "${release}"

    log "Keeping the last ${KEEP_RELEASES} releases"
    find "${RELEASES}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
        | sort -rn \
        | tail -n +"$((KEEP_RELEASES + 1))" \
        | cut -d' ' -f2- \
        | while read -r old; do
            [[ "$(readlink -f "${CURRENT}")" == "${old}" ]] || rm -rf "${old}"
        done
}

rollback() {
    local target="${1:-}" live
    live="$(readlink -f "${CURRENT}")"

    if [[ -z "${target}" ]]; then
        target="$(find "${RELEASES}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -rn | cut -d' ' -f2- | grep -vxF "${live}" | head -n 1)"
    else
        target="${RELEASES}/${target}"
    fi

    [[ -n "${target}" && -d "${target}" ]] || { echo "No release to roll back to." >&2; exit 1; }

    log "Rolling back to $(basename "${target}")"
    activate "${target}"
}

case "${1:-}" in
    deploy) deploy "${2:?commit required}" "${3:?archive required}" ;;
    rollback) rollback "${2:-}" ;;
    *) echo "Usage: $0 deploy <commit> <archive.tar.gz> | rollback [<commit>]" >&2; exit 2 ;;
esac
